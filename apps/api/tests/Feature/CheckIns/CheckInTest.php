<?php

namespace Tests\Feature\CheckIns;

use App\CheckIns\MemberCodes;
use App\Enums\BranchRole;
use App\Events\CheckInChanged;
use App\Models\Branch;
use App\Models\CheckIn;
use App\Models\Member;
use App\Models\Membership;
use App\Models\OpeningHour;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Branch $bole;

    private Branch $piassa;

    private Member $member;

    private User $reception;

    protected function setUp(): void
    {
        parent::setUp();
        // 09:00 in Addis Ababa on a Tuesday; branches open 06:00–22:00.
        Carbon::setTestNow('2026-10-27 06:00:00');
        Event::fake([CheckInChanged::class]);

        $this->organization = Organization::factory()->create();
        $this->bole = Branch::factory()->create(['organization_id' => $this->organization->id, 'capacity' => 80]);
        $this->piassa = Branch::factory()->create(['organization_id' => $this->organization->id]);
        $this->member = Member::factory()->at($this->bole)->create();
        $this->reception = User::factory()->staffAt($this->bole, BranchRole::Receptionist)->create();
        Sanctum::actingAs($this->reception);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function membership(array $plan = [], array $overrides = []): Membership
    {
        $plan = Plan::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Monthly'], 'type' => 'time', 'duration_days' => 30, 'price_cents' => 250000, ...$plan]);

        return Membership::create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->bole->id,
            'member_id' => $this->member->id,
            'plan_id' => $plan->id,
            'plan_snapshot' => $plan->snapshot(),
            'starts_on' => '2026-10-20',
            'ends_on' => '2026-11-18',
            'visits_total' => $plan->visit_count,
            'visits_remaining' => $plan->visit_count,
            'status' => 'active',
            ...$overrides,
        ]);
    }

    private function scan(string $code, ?Branch $branch = null)
    {
        return $this->postJson('/api/v1/branches/'.($branch ?? $this->bole)->ulid.'/check-ins/scan', ['code' => $code]);
    }

    private function phoneCode(?Member $member = null): string
    {
        return app(MemberCodes::class)->phoneCode($member ?? $this->member);
    }

    public function test_phone_qr_lets_a_member_with_a_current_membership_in(): void
    {
        $this->membership();

        $this->scan($this->phoneCode())
            ->assertOk()
            ->assertJsonPath('data.result', 'allowed')
            ->assertJsonPath('data.method', 'qr')
            ->assertJsonPath('data.member.id', $this->member->ulid)
            ->assertJsonPath('data.membership.plan', 'Monthly')
            ->assertJsonPath('data.membership.days_left', 23);

        $this->assertDatabaseHas('check_ins', ['member_id' => $this->member->id, 'result' => 'allowed', 'handled_by' => $this->reception->id]);
        Event::assertDispatched(CheckInChanged::class, fn ($e) => $e->type === 'scanned' && $e->broadcastAs() === 'check-in.scanned');
    }

    public function test_a_second_scan_shows_already_inside_and_records_nothing_new(): void
    {
        $this->membership();
        $this->scan($this->phoneCode())->assertJsonPath('data.result', 'allowed');

        $this->scan($this->phoneCode())->assertJsonPath('data.result', 'already_inside');
        $this->assertSame(1, CheckIn::count());
    }

    public function test_visit_passes_use_one_visit_per_day(): void
    {
        $membership = $this->membership(['type' => 'visits', 'duration_days' => null, 'visit_count' => 10], ['ends_on' => null]);

        $first = $this->scan($this->phoneCode())->assertJsonPath('data.membership.visits_remaining', 9)->json('data.id');
        $this->postJson("/api/v1/check-ins/{$first}/check-out")->assertOk();

        // Back the same afternoon: in again, no second visit used.
        Carbon::setTestNow('2026-10-27 12:00:00');
        $this->scan($this->phoneCode())->assertJsonPath('data.result', 'allowed')->assertJsonPath('data.membership.visits_remaining', 9);

        Carbon::setTestNow('2026-10-28 06:00:00');
        $this->artisan('check-ins:close-day')->assertSuccessful();
        $this->scan($this->phoneCode())->assertJsonPath('data.membership.visits_remaining', 8);

        $membership->update(['visits_remaining' => 0]);
        Carbon::setTestNow('2026-10-29 06:00:00');
        $this->artisan('check-ins:close-day');
        $this->scan($this->phoneCode())->assertJsonPath('data.result', 'denied')->assertJsonPath('data.reason', 'no_visits_left');
    }

    public function test_old_screenshots_and_forged_codes_are_refused_and_not_stored(): void
    {
        $this->membership();
        $screenshot = $this->phoneCode();

        Carbon::setTestNow('2026-10-27 06:02:00');
        $this->scan($screenshot)->assertJsonPath('data.result', 'denied')->assertJsonPath('data.reason', 'expired_code');

        $forged = preg_replace('/\.[0-9A-F]+$/', '.0123456789ABCDEF', $this->phoneCode());
        $this->scan($forged)->assertJsonPath('data.reason', 'invalid_code')->assertJsonPath('data.member', null);
        $this->scan('hello')->assertJsonPath('data.reason', 'invalid_code');

        // Another gym's member is unknown here.
        $stranger = Member::factory()->create();
        $this->scan($this->phoneCode($stranger))->assertJsonPath('data.reason', 'invalid_code');

        $this->assertSame(0, CheckIn::count());
    }

    public function test_printed_cards_work_until_reissued(): void
    {
        $this->membership();
        $old = $this->getJson("/api/v1/members/{$this->member->ulid}/card")->assertOk()->json('data.code');
        $this->assertStringStartsWith('G2.', $old);

        $this->scan($old)->assertJsonPath('data.result', 'allowed')->assertJsonPath('data.method', 'card');

        $new = $this->postJson("/api/v1/members/{$this->member->ulid}/card/reissue")->assertJsonPath('data.version', 2)->json('data.code');
        CheckIn::query()->update(['checked_out_at' => now()]);

        $this->scan($old)->assertJsonPath('data.reason', 'card_replaced');
        $this->scan($new)->assertJsonPath('data.result', 'allowed');
        $this->assertDatabaseHas('audit_logs', ['action' => 'member.card_reissued']);
    }

    public function test_denials_name_the_reason(): void
    {
        $this->scan($this->phoneCode())->assertJsonPath('data.reason', 'no_membership')->assertJsonPath('data.overridable', true);

        $m = $this->membership([], ['status' => 'pending_payment']);
        $this->scan($this->phoneCode())->assertJsonPath('data.reason', 'payment_pending');

        $m->update(['status' => 'frozen']);
        $this->scan($this->phoneCode())->assertJsonPath('data.reason', 'membership_frozen');

        $m->update(['status' => 'active', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-30']);
        $this->scan($this->phoneCode())->assertJsonPath('data.reason', 'not_started');

        $m->update(['starts_on' => '2026-09-01', 'ends_on' => '2026-09-30']);
        $this->scan($this->phoneCode())->assertJsonPath('data.reason', 'membership_expired');

        $this->member->update(['status' => 'banned']);
        $this->scan($this->phoneCode())->assertJsonPath('data.reason', 'member_banned')->assertJsonPath('data.overridable', false);
    }

    public function test_plans_are_valid_at_the_branches_they_were_sold_for(): void
    {
        $piassaDesk = User::factory()->staffAt($this->piassa, BranchRole::Receptionist)->create();
        $home = $this->membership();

        Sanctum::actingAs($piassaDesk);
        $this->scan($this->phoneCode(), $this->piassa)->assertJsonPath('data.reason', 'wrong_branch');

        $home->update(['status' => 'cancelled']);
        $this->membership(['access_scope' => 'all_branches']);
        $this->scan($this->phoneCode(), $this->piassa)->assertJsonPath('data.result', 'allowed');
    }

    public function test_closed_branch_is_refused_but_can_be_overridden_with_a_reason(): void
    {
        $this->membership();
        OpeningHour::where('branch_id', $this->bole->id)->update(['opens_at' => '06:00', 'closes_at' => '08:00']);

        $id = $this->scan($this->phoneCode())->assertJsonPath('data.reason', 'branch_closed')->json('data.id');

        $this->postJson("/api/v1/check-ins/{$id}/override", ['reason' => ''])->assertJsonValidationErrors('reason');
        $this->postJson("/api/v1/check-ins/{$id}/override", ['reason' => 'Staff training session'])
            ->assertOk()
            ->assertJsonPath('data.result', 'overridden')
            ->assertJsonPath('data.override_reason', 'Staff training session');

        $this->assertDatabaseHas('audit_logs', ['action' => 'check_in.overridden']);
        $this->postJson("/api/v1/check-ins/{$id}/override", ['reason' => 'Again'])->assertJsonValidationErrors('reason');
    }

    public function test_banned_members_cannot_be_overridden(): void
    {
        $this->member->update(['status' => 'banned']);
        $id = $this->scan($this->phoneCode())->json('data.id');

        $this->postJson("/api/v1/check-ins/{$id}/override", ['reason' => 'Let them in'])->assertJsonValidationErrors('reason');
    }

    public function test_desk_can_check_in_by_search_and_see_who_is_inside(): void
    {
        $this->membership();
        $other = Member::factory()->at($this->bole)->create();

        $this->postJson("/api/v1/branches/{$this->bole->ulid}/check-ins", ['member' => $this->member->ulid])
            ->assertJsonPath('data.result', 'allowed')
            ->assertJsonPath('data.method', 'search');
        $this->postJson("/api/v1/branches/{$this->bole->ulid}/check-ins", ['member' => $other->ulid])
            ->assertJsonPath('data.reason', 'no_membership');

        $this->getJson("/api/v1/branches/{$this->bole->ulid}/check-ins")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.inside', 1)
            ->assertJsonPath('meta.capacity', 80)
            ->assertJsonPath('meta.visits_today', 1)
            ->assertJsonPath('meta.denied_today', 1);

        $this->getJson("/api/v1/branches/{$this->bole->ulid}/check-ins?scope=recent")->assertJsonCount(2, 'data');
    }

    public function test_only_desk_staff_at_the_branch_work_the_door(): void
    {
        $this->membership();

        Sanctum::actingAs(User::factory()->staffAt($this->bole, BranchRole::Trainer)->create());
        $this->scan($this->phoneCode())->assertForbidden();

        Sanctum::actingAs(User::factory()->staffAt($this->piassa, BranchRole::Receptionist)->create());
        $this->scan($this->phoneCode())->assertForbidden();
        $this->getJson("/api/v1/members/{$this->member->ulid}/card")->assertForbidden();
    }

    public function test_nightly_close_checks_out_people_at_closing_time(): void
    {
        $this->membership();
        $id = $this->scan($this->phoneCode())->json('data.id');

        Carbon::setTestNow('2026-10-28 03:00:00');
        $this->artisan('check-ins:close-day')->assertSuccessful();

        // Closing time 22:00 in Addis is 19:00 UTC.
        $this->assertSame('2026-10-27 19:00:00', CheckIn::where('ulid', $id)->first()->checked_out_at->utc()->toDateTimeString());
    }

    public function test_desk_channel_is_limited_to_the_branch_desk_staff(): void
    {
        // Production uses Reverb; channel rules register on the broadcaster in use.
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'k', 'broadcasting.connections.reverb.secret' => 's', 'broadcasting.connections.reverb.app_id' => 'a']);
        app(BroadcastManager::class)->purge();
        require base_path('routes/channels.php');

        $this->postJson('/api/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-branch.{$this->bole->ulid}.desk"])->assertOk();

        Sanctum::actingAs(User::factory()->staffAt($this->piassa, BranchRole::Receptionist)->create());
        $this->postJson('/api/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-branch.{$this->bole->ulid}.desk"])->assertForbidden();
    }
}
