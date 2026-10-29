<?php

namespace Tests\Feature\CheckIns;

use App\CheckIns\MemberCodes;
use App\Enums\BranchRole;
use App\Events\CheckInChanged;
use App\Models\Branch;
use App\Models\CheckIn;
use App\Models\KioskDevice;
use App\Models\Locker;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LockerKioskTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Branch $bole;

    private User $manager;

    private User $reception;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-27 06:00:00');
        Event::fake([CheckInChanged::class]);

        $this->organization = Organization::factory()->create();
        $this->bole = Branch::factory()->create(['organization_id' => $this->organization->id]);
        $this->manager = User::factory()->staffAt($this->bole, BranchRole::Manager)->create();
        $this->reception = User::factory()->staffAt($this->bole, BranchRole::Receptionist)->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function memberInside(string $gender = 'female'): CheckIn
    {
        $member = Member::factory()->at($this->bole)->create(['gender' => $gender]);
        $plan = Plan::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Monthly'], 'type' => 'time', 'duration_days' => 30, 'price_cents' => 1]);
        Membership::create([
            'organization_id' => $this->organization->id, 'branch_id' => $this->bole->id, 'member_id' => $member->id, 'plan_id' => $plan->id,
            'plan_snapshot' => $plan->snapshot(), 'starts_on' => '2026-10-20', 'ends_on' => '2026-11-18', 'status' => 'active',
        ]);
        Sanctum::actingAs($this->reception);
        $id = $this->postJson("/api/v1/branches/{$this->bole->ulid}/check-ins", ['member' => $member->ulid])->assertJsonPath('data.result', 'allowed')->json('data.id');

        return CheckIn::where('ulid', $id)->first();
    }

    public function test_manager_adds_lockers_in_ranges_and_desk_sees_them(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson("/api/v1/branches/{$this->bole->ulid}/lockers", ['from' => 1, 'to' => 12, 'zone' => 'female'])
            ->assertCreated()
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('data.0.number', '1')
            ->assertJsonPath('data.9.number', '10');
        $this->postJson("/api/v1/branches/{$this->bole->ulid}/lockers", ['from' => 1, 'to' => 5, 'prefix' => 'm', 'zone' => 'male'])->assertJsonPath('meta.total', 17);
        $this->postJson("/api/v1/branches/{$this->bole->ulid}/lockers", ['from' => 10, 'to' => 14, 'zone' => 'female'])->assertJsonValidationErrors('from');

        Sanctum::actingAs($this->reception);
        $this->getJson("/api/v1/branches/{$this->bole->ulid}/lockers")->assertOk()->assertJsonPath('meta.free', 17);
        $this->postJson("/api/v1/branches/{$this->bole->ulid}/lockers", ['from' => 20, 'to' => 21, 'zone' => 'unisex'])->assertForbidden();
    }

    public function test_desk_gives_the_next_free_locker_in_the_members_zone_and_checkout_frees_it(): void
    {
        foreach ([['F1', 'female'], ['F2', 'female'], ['M1', 'male'], ['U1', 'unisex']] as [$number, $zone]) {
            Locker::create(['branch_id' => $this->bole->id, 'number' => $number, 'zone' => $zone]);
        }
        Locker::where('number', 'F1')->update(['status' => 'out_of_order']);

        $woman = $this->memberInside('female');
        $this->postJson("/api/v1/check-ins/{$woman->ulid}/locker")->assertOk()->assertJsonPath('data.locker.number', 'F2');

        $man = $this->memberInside('male');
        $this->postJson("/api/v1/check-ins/{$man->ulid}/locker")->assertJsonPath('data.locker.number', 'M1');

        // Women's lockers full: falls back to unisex, then none left.
        $second = $this->memberInside('female');
        $this->postJson("/api/v1/check-ins/{$second->ulid}/locker")->assertJsonPath('data.locker.number', 'U1');
        $third = $this->memberInside('female');
        $this->postJson("/api/v1/check-ins/{$third->ulid}/locker")->assertJsonValidationErrors('locker');

        // A chosen locker that's taken is refused.
        $f2 = Locker::where('number', 'F2')->first();
        $this->postJson("/api/v1/check-ins/{$third->ulid}/locker", ['locker' => $f2->id])->assertJsonValidationErrors('locker');

        $this->postJson("/api/v1/check-ins/{$woman->ulid}/check-out")->assertOk();
        $this->postJson("/api/v1/check-ins/{$third->ulid}/locker", ['locker' => $f2->id])->assertJsonPath('data.locker.number', 'F2');

        $this->getJson("/api/v1/branches/{$this->bole->ulid}/check-ins")->assertJsonPath('data.0.locker.number', 'F2');

        $this->deleteJson("/api/v1/check-ins/{$third->ulid}/locker")->assertJsonPath('data.locker', null);
        Event::assertDispatched(CheckInChanged::class, fn ($e) => $e->type === 'locker');
    }

    public function test_lockers_in_use_cannot_be_removed(): void
    {
        $locker = Locker::create(['branch_id' => $this->bole->id, 'number' => '7', 'zone' => 'unisex']);
        $visit = $this->memberInside();
        $this->postJson("/api/v1/check-ins/{$visit->ulid}/locker");

        Sanctum::actingAs($this->manager);
        $this->deleteJson("/api/v1/branches/{$this->bole->ulid}/lockers/{$locker->id}")->assertJsonValidationErrors('locker');
        $this->patchJson("/api/v1/branches/{$this->bole->ulid}/lockers/{$locker->id}", ['status' => 'out_of_order'])->assertJsonPath('data.status', 'out_of_order');

        $other = Branch::factory()->create();
        $foreign = Locker::create(['branch_id' => $other->id, 'number' => '1', 'zone' => 'unisex']);
        $this->deleteJson("/api/v1/branches/{$this->bole->ulid}/lockers/{$foreign->id}")->assertNotFound();
    }

    public function test_kiosk_pairs_once_with_a_short_code_and_scans_with_its_token(): void
    {
        Sanctum::actingAs($this->manager);
        $created = $this->postJson("/api/v1/branches/{$this->bole->ulid}/kiosks", ['name' => 'Main door'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pairing');
        $code = $created->json('data.pairing_code');
        $this->assertMatchesRegularExpression('/^[2-9A-HJKMNP-Z]{4}-[2-9A-HJKMNP-Z]{4}$/', $code);

        $this->postJson('/api/v1/kiosk/pair', ['code' => 'WRONG-CODE'])->assertJsonValidationErrors('code');
        $token = $this->postJson('/api/v1/kiosk/pair', ['code' => strtolower($code)])
            ->assertOk()
            ->assertJsonPath('data.branch.id', $this->bole->ulid)
            ->json('data.token');
        // The code works once.
        $this->postJson('/api/v1/kiosk/pair', ['code' => $code])->assertJsonValidationErrors('code');

        $visit = $this->memberInside();
        $visit->update(['checked_out_at' => now()]);
        $member = $visit->member;

        $this->withHeader('X-Kiosk-Token', $token)->postJson('/api/v1/kiosk/scan', ['code' => app(MemberCodes::class)->phoneCode($member)])
            ->assertOk()
            ->assertJsonPath('data.result', 'allowed')
            ->assertJsonPath('data.first_name', $member->first_name)
            ->assertJsonMissingPath('data.member');

        $this->assertDatabaseHas('check_ins', ['member_id' => $member->id, 'method' => 'kiosk', 'handled_by' => null, 'result' => 'allowed']);
        Event::assertDispatched(CheckInChanged::class, fn ($e) => $e->checkIn->method === 'kiosk');
        $this->assertNotNull(KioskDevice::first()->last_seen_at);
    }

    public function test_kiosk_shows_little_on_denial_and_unpaired_tablets_are_refused(): void
    {
        $kiosk = KioskDevice::create(['branch_id' => $this->bole->id, 'name' => 'Door']);
        $kiosk->startPairing();
        $token = $kiosk->completePairing();

        $stranger = Member::factory()->at($this->bole)->create();
        $this->withHeader('X-Kiosk-Token', $token)->postJson('/api/v1/kiosk/scan', ['code' => app(MemberCodes::class)->phoneCode($stranger)])
            ->assertJsonPath('data.result', 'denied')
            ->assertJsonPath('data.reason', 'no_membership')
            ->assertJsonPath('data.membership', null);

        $this->withHeader('X-Kiosk-Token', 'kiosk_nope')->postJson('/api/v1/kiosk/scan', ['code' => 'x'])->assertUnauthorized();

        // Re-pairing signs the old tablet out.
        Sanctum::actingAs($this->manager);
        $this->postJson("/api/v1/branches/{$this->bole->ulid}/kiosks/{$kiosk->ulid}/pair")->assertJsonPath('data.status', 'pairing');
        $this->withHeader('X-Kiosk-Token', $token)->getJson('/api/v1/kiosk')->assertUnauthorized();

        Sanctum::actingAs($this->reception);
        $this->getJson("/api/v1/branches/{$this->bole->ulid}/kiosks")->assertForbidden();
    }
}
