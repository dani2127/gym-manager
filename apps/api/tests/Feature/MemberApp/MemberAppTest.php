<?php

namespace Tests\Feature\MemberApp;

use App\Enums\BranchRole;
use App\Events\CheckInChanged;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Membership;
use App\Models\NotificationPreference;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberAppTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Branch $bole;

    private Member $member;

    private User $reception;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-27 06:00:00');
        Event::fake([CheckInChanged::class]);

        $this->organization = Organization::factory()->create(['slug' => 'lion-fitness']);
        $this->bole = Branch::factory()->create(['organization_id' => $this->organization->id]);
        $this->member = Member::factory()->at($this->bole)->create(['first_name' => 'Hana', 'phone' => '+251911223344']);
        $this->reception = User::factory()->staffAt($this->bole, BranchRole::Receptionist)->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function invite(): string
    {
        Sanctum::actingAs($this->reception);

        return str_replace(' ', '', $this->postJson("/api/v1/members/{$this->member->ulid}/app-access")
            ->assertOk()
            ->assertJsonPath('data.status', 'invited')
            ->json('data.setup_code'));
    }

    private function setUpApp(string $code): string
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/auth/member-setup', [
            'organization' => 'lion-fitness',
            'phone' => '0911 223 344',
            'code' => $code,
            'password' => 'my-gym-pass',
            'password_confirmation' => 'my-gym-pass',
            'device_name' => 'Hana phone',
        ])->assertOk()->json('token');
    }

    /** The app's QR, computed the way the phone does it (HMAC of the secret). */
    private function appCode(string $secret, string $ulid, int $at): string
    {
        $window = intdiv($at, 30);

        return "G1.{$ulid}.{$window}.".strtoupper(substr(hash_hmac('sha256', "{$ulid}|phone|{$window}", $secret), 0, 16));
    }

    public function test_desk_invites_and_the_member_sets_their_own_password(): void
    {
        $code = $this->invite();
        $this->assertMatchesRegularExpression('/^\d{8}$/', $code);
        $this->assertSame('member', $this->member->fresh()->user->account_type->value);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/member-setup', ['organization' => 'lion-fitness', 'phone' => '0911223344', 'code' => '00000000', 'password' => 'my-gym-pass', 'password_confirmation' => 'my-gym-pass', 'device_name' => 'x'])
            ->assertJsonValidationErrors('code');

        $token = $this->setUpApp($code);
        $this->withToken($token)->getJson('/api/v1/me/member')
            ->assertOk()
            ->assertJsonPath('data.member.first_name', 'Hana')
            ->assertJsonPath('data.organization.slug', 'lion-fitness')
            ->assertJsonPath('data.current', null);

        // The code works once; the password now signs in.
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/member-setup', ['organization' => 'lion-fitness', 'phone' => '0911223344', 'code' => $code, 'password' => 'another-pass', 'password_confirmation' => 'another-pass', 'device_name' => 'x'])
            ->assertJsonValidationErrors('code');
        $this->postJson('/api/v1/auth/login', ['organization' => 'lion-fitness', 'phone' => '0911223344', 'password' => 'my-gym-pass', 'device_name' => 'x'])->assertOk();

        Sanctum::actingAs($this->reception);
        $this->getJson("/api/v1/members/{$this->member->ulid}/app-access")->assertJsonPath('data.status', 'active');
    }

    public function test_setup_codes_expire_after_three_days(): void
    {
        $code = $this->invite();
        Carbon::setTestNow('2026-10-30 07:00:00');
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/member-setup', ['organization' => 'lion-fitness', 'phone' => '0911223344', 'code' => $code, 'password' => 'my-gym-pass', 'password_confirmation' => 'my-gym-pass', 'device_name' => 'x'])
            ->assertJsonValidationErrors('code');
    }

    public function test_the_offline_app_code_opens_the_door(): void
    {
        $plan = Plan::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Monthly'], 'type' => 'time', 'duration_days' => 30, 'price_cents' => 1]);
        Membership::create(['organization_id' => $this->organization->id, 'branch_id' => $this->bole->id, 'member_id' => $this->member->id, 'plan_id' => $plan->id, 'plan_snapshot' => $plan->snapshot(), 'starts_on' => '2026-10-20', 'ends_on' => '2026-11-18', 'status' => 'active']);

        $token = $this->setUpApp($this->invite());
        $pass = $this->withToken($token)->getJson('/api/v1/me/member/pass')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->json('data');
        $this->assertSame(now()->getTimestamp(), $pass['server_time']);

        $this->withToken($token)->getJson('/api/v1/me/member')->assertJsonPath('data.current.plan.name', 'Monthly')->assertJsonPath('data.current.days_left', 23);

        Sanctum::actingAs($this->reception);
        $this->postJson("/api/v1/branches/{$this->bole->ulid}/check-ins/scan", ['code' => $this->appCode($pass['secret'], $pass['member_id'], now()->getTimestamp())])
            ->assertJsonPath('data.result', 'allowed');
    }

    public function test_members_see_only_their_own_invoices_and_no_staff_screens(): void
    {
        $token = $this->setUpApp($this->invite());
        $mine = Invoice::create(['organization_id' => $this->organization->id, 'branch_id' => $this->bole->id, 'member_id' => $this->member->id, 'number' => 'B-1', 'status' => 'paid', 'subtotal_cents' => 1000, 'discount_cents' => 0, 'tax_cents' => 0, 'total_cents' => 1000, 'paid_cents' => 1000, 'issued_at' => now()]);
        $other = Member::factory()->at($this->bole)->create();
        $theirs = Invoice::create(['organization_id' => $this->organization->id, 'branch_id' => $this->bole->id, 'member_id' => $other->id, 'number' => 'B-2', 'status' => 'paid', 'subtotal_cents' => 1000, 'discount_cents' => 0, 'tax_cents' => 0, 'total_cents' => 1000, 'paid_cents' => 1000, 'issued_at' => now()]);

        $this->withToken($token)->getJson('/api/v1/me/member/invoices')->assertJsonCount(1, 'data')->assertJsonPath('data.0.number', 'B-1');
        $this->withToken($token)->get("/api/v1/me/member/invoices/{$mine->ulid}/receipt")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->withToken($token)->getJson("/api/v1/me/member/invoices/{$theirs->ulid}/receipt")->assertNotFound();

        $this->withToken($token)->getJson('/api/v1/members')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/invoices')->assertForbidden();

        Sanctum::actingAs($this->reception);
        $this->getJson('/api/v1/me/member')->assertForbidden();
    }

    public function test_lost_phone_signs_out_every_device_and_old_codes_stop_working(): void
    {
        $token = $this->setUpApp($this->invite());
        $pass = $this->withToken($token)->getJson('/api/v1/me/member/pass')->json('data');

        Sanctum::actingAs($this->reception);
        $this->deleteJson("/api/v1/members/{$this->member->ulid}/app-access")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'member.app_signed_out']);

        $this->postJson("/api/v1/branches/{$this->bole->ulid}/check-ins/scan", ['code' => $this->appCode($pass['secret'], $pass['member_id'], now()->getTimestamp())])
            ->assertJsonPath('data.reason', 'invalid_code');
        $this->assertSame(2, $this->member->fresh()->card_version);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me/member')->assertUnauthorized();
    }

    public function test_invite_needs_a_phone_not_used_by_another_members_login(): void
    {
        Sanctum::actingAs($this->reception);
        $noPhone = Member::factory()->at($this->bole)->create(['phone' => null]);
        $this->postJson("/api/v1/members/{$noPhone->ulid}/app-access")->assertJsonValidationErrors('phone');

        $this->postJson("/api/v1/members/{$this->member->ulid}/app-access")->assertOk();
        $sibling = Member::factory()->at($this->bole)->create(['phone' => '+251911223344']);
        $this->postJson("/api/v1/members/{$sibling->ulid}/app-access")->assertJsonValidationErrors('phone');

        Sanctum::actingAs(User::factory()->staffAt($this->bole, BranchRole::Trainer)->create());
        $this->postJson("/api/v1/members/{$this->member->ulid}/app-access")->assertForbidden();
    }

    public function test_member_picks_whatsapp_or_a_linked_telegram(): void
    {
        $token = $this->setUpApp($this->invite());

        $this->withToken($token)->getJson('/api/v1/me/notifications')
            ->assertJsonPath('data.channel', null)
            ->assertJsonPath('data.whatsapp_number', '+251911223344');

        $this->withToken($token)->putJson('/api/v1/me/notifications', ['channel' => 'telegram'])->assertJsonValidationErrors('channel');
        $this->withToken($token)->putJson('/api/v1/me/notifications', ['channel' => 'whatsapp', 'whatsapp_number' => '0922 334 455'])
            ->assertOk()
            ->assertJsonPath('data.channel', 'whatsapp')
            ->assertJsonPath('data.whatsapp_number', '+251922334455');

        NotificationPreference::create(['organization_id' => $this->organization->id, 'user_id' => $this->member->fresh()->user_id, 'channel' => 'telegram', 'destination' => '12345', 'verified_at' => now()]);
        $this->withToken($token)->putJson('/api/v1/me/notifications', ['channel' => 'telegram'])
            ->assertJsonPath('data.channel', 'telegram')
            ->assertJsonPath('data.telegram_linked', true);
        $this->assertSame(1, NotificationPreference::where('is_preferred', true)->count());
    }
}
