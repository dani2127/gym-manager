<?php

namespace Tests\Feature\Memberships;

use App\Enums\AccountType;
use App\Enums\BranchRole;
use App\Events\FreezeChanged;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipFreeze;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Support\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FreezeTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Branch $bole;

    private Plan $monthly;

    private Member $member;

    private User $memberUser;

    private User $reception;

    private User $manager;

    private Membership $membership;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-27 06:00:00');
        Event::fake([FreezeChanged::class]);
        Notification::fake();

        $this->organization = Organization::factory()->create();
        $this->bole = Branch::factory()->create(['organization_id' => $this->organization->id, 'code' => 'BOLE']);
        $this->monthly = Plan::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Monthly'], 'type' => 'time', 'duration_days' => 30, 'price_cents' => 250000, 'freeze_allowed' => true, 'max_freeze_days' => 10, 'max_freezes' => 2]);
        $this->memberUser = User::factory()->create(['organization_id' => $this->organization->id, 'account_type' => AccountType::Member]);
        $this->member = Member::factory()->at($this->bole)->create(['user_id' => $this->memberUser->id]);
        $this->reception = User::factory()->staffAt($this->bole, BranchRole::Receptionist)->create();
        $this->manager = User::factory()->staffAt($this->bole, BranchRole::Manager)->create();
        app(SettingsRepository::class)->put($this->organization, ['freeze.enabled' => true]);
        $this->membership = $this->membershipFrom('2026-10-20', '2026-11-18');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function membershipFrom(string $starts, string $ends, string $status = 'active'): Membership
    {
        return Membership::create([
            'organization_id' => $this->organization->id, 'branch_id' => $this->bole->id, 'member_id' => $this->member->id,
            'plan_id' => $this->monthly->id, 'plan_snapshot' => $this->monthly->snapshot(), 'starts_on' => $starts, 'ends_on' => $ends, 'status' => $status,
        ]);
    }

    private function freeze(array $overrides = [], ?User $as = null)
    {
        Sanctum::actingAs($as ?? $this->reception);

        return $this->postJson("/api/v1/memberships/{$this->membership->ulid}/freezes", [...['starts_on' => '2026-10-27', 'days' => 7], ...$overrides]);
    }

    private function scan()
    {
        Sanctum::actingAs($this->reception);

        return $this->postJson("/api/v1/branches/{$this->bole->ulid}/check-ins", ['member' => $this->member->ulid]);
    }

    public function test_desk_freeze_starting_today_freezes_now_and_moves_the_end_and_queued_renewal(): void
    {
        $renewal = $this->membershipFrom('2026-11-19', '2026-12-18');

        $this->freeze()->assertCreated()->assertJsonPath('data.status', 'active')->assertJsonPath('data.back_on', '2026-11-03');

        $this->membership->refresh();
        $this->assertSame('frozen', $this->membership->status);
        $this->assertSame('2026-11-25', $this->membership->ends_on->toDateString());
        $renewal->refresh();
        $this->assertSame('2026-11-26', $renewal->starts_on->toDateString());
        $this->assertSame('2026-12-25', $renewal->ends_on->toDateString());

        $this->scan()->assertJsonPath('data.reason', 'membership_frozen');
        Event::assertDispatched(FreezeChanged::class);
    }

    public function test_nightly_run_starts_and_finishes_freezes(): void
    {
        $this->freeze(['starts_on' => '2026-10-29', 'days' => 3])->assertCreated()->assertJsonPath('data.status', 'approved');
        $this->assertSame('active', $this->membership->refresh()->status);

        Carbon::setTestNow('2026-10-29 00:01:00');
        $this->artisan('memberships:freezes')->assertSuccessful();
        $this->assertSame('frozen', $this->membership->refresh()->status);

        Carbon::setTestNow('2026-11-01 00:01:00');
        $this->artisan('memberships:freezes')->assertSuccessful();
        $this->assertSame('active', $this->membership->refresh()->status);
        $this->assertSame('completed', MembershipFreeze::first()->status);
        $this->assertSame('2026-11-21', $this->membership->ends_on->toDateString());
    }

    public function test_ending_early_gives_back_the_unused_days(): void
    {
        $id = $this->freeze(['days' => 10])->assertCreated()->json('data.id');
        $this->assertSame('2026-11-28', $this->membership->refresh()->ends_on->toDateString());

        Carbon::setTestNow('2026-10-30 09:00:00');
        $this->postJson("/api/v1/freezes/{$id}/end")->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.days', 3);

        $this->membership->refresh();
        $this->assertSame('active', $this->membership->status);
        $this->assertSame('2026-11-21', $this->membership->ends_on->toDateString());
        $this->scan()->assertJsonPath('data.result', 'allowed');

        // Three of ten days used; seven are left for another freeze.
        $this->getJson("/api/v1/members/{$this->member->ulid}/memberships")->assertJsonPath('data.0.freeze_policy.days_left', 7);
    }

    public function test_cancelling_an_upcoming_freeze_restores_the_end_date(): void
    {
        $id = $this->freeze(['starts_on' => '2026-11-05', 'days' => 5])->json('data.id');
        $this->assertSame('2026-11-23', $this->membership->refresh()->ends_on->toDateString());

        $this->deleteJson("/api/v1/freezes/{$id}")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame('2026-11-18', $this->membership->refresh()->ends_on->toDateString());
    }

    public function test_plan_limits_hold_for_the_desk_but_a_manager_can_override(): void
    {
        $this->freeze(['days' => 11])->assertUnprocessable()->assertJsonValidationErrors('days');
        $this->freeze(['days' => 11, 'override' => true])->assertForbidden();

        $this->freeze(['days' => 11, 'override' => true], $this->manager)->assertCreated()->assertJsonPath('data.over_limit', true);
    }

    public function test_one_freeze_at_a_time_and_the_count_limit(): void
    {
        $this->freeze(['starts_on' => '2026-10-28', 'days' => 2])->assertCreated();
        $this->freeze(['starts_on' => '2026-11-05', 'days' => 2])->assertUnprocessable()->assertJsonValidationErrors('membership');

        MembershipFreeze::query()->update(['status' => 'completed']);
        $this->freeze(['starts_on' => '2026-11-05', 'days' => 2])->assertCreated();
        MembershipFreeze::query()->update(['status' => 'completed']);
        $this->freeze(['starts_on' => '2026-11-10', 'days' => 2])->assertUnprocessable()->assertJsonValidationErrors('membership');
    }

    public function test_freezing_respects_the_gym_switch_and_the_plan(): void
    {
        app(SettingsRepository::class)->put($this->organization, ['freeze.enabled' => false]);
        $this->freeze()->assertUnprocessable()->assertJsonValidationErrors('membership');

        app(SettingsRepository::class)->put($this->organization, ['freeze.enabled' => true]);
        $this->freeze(['starts_on' => '2026-10-26'])->assertUnprocessable()->assertJsonValidationErrors('starts_on');
        $this->freeze(['starts_on' => '2026-11-19'])->assertUnprocessable()->assertJsonValidationErrors('starts_on');
    }

    public function test_member_requests_and_the_desk_approves_or_rejects(): void
    {
        Sanctum::actingAs($this->memberUser);
        $id = $this->postJson("/api/v1/me/member/memberships/{$this->membership->ulid}/freezes", ['starts_on' => '2026-10-28', 'days' => 5, 'reason' => 'Travelling'])
            ->assertCreated()->assertJsonPath('data.status', 'requested')->json('data.id');
        $this->assertSame('2026-11-18', $this->membership->refresh()->ends_on->toDateString());

        // Over the limit from the app is refused outright.
        MembershipFreeze::query()->update(['status' => 'rejected']);
        $this->postJson("/api/v1/me/member/memberships/{$this->membership->ulid}/freezes", ['starts_on' => '2026-10-28', 'days' => 20])->assertUnprocessable();
        MembershipFreeze::query()->update(['status' => 'requested']);

        Sanctum::actingAs($this->reception);
        $this->getJson("/api/v1/freezes?branch={$this->bole->ulid}")->assertOk()->assertJsonPath('meta.requested', 1)->assertJsonPath('data.0.reason', 'Travelling');

        // The request waited a day: it starts today instead.
        Carbon::setTestNow('2026-10-29 08:00:00');
        $this->postJson("/api/v1/freezes/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'active')->assertJsonPath('data.starts_on', '2026-10-29');
        $this->assertSame('2026-11-23', $this->membership->refresh()->ends_on->toDateString());

        Sanctum::actingAs($this->memberUser);
        $this->getJson('/api/v1/me/member')->assertJsonPath('data.current.state', 'frozen')->assertJsonPath('data.current.freezes.0.back_on', '2026-11-03');
    }

    public function test_rejected_request_and_member_cancel(): void
    {
        Sanctum::actingAs($this->memberUser);
        $id = $this->postJson("/api/v1/me/member/memberships/{$this->membership->ulid}/freezes", ['starts_on' => '2026-10-28', 'days' => 5])->json('data.id');

        Sanctum::actingAs($this->reception);
        $this->postJson("/api/v1/freezes/{$id}/reject", ['reason' => 'Talk to the manager'])->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->postJson("/api/v1/freezes/{$id}/approve")->assertUnprocessable();

        Sanctum::actingAs($this->memberUser);
        $second = $this->postJson("/api/v1/me/member/memberships/{$this->membership->ulid}/freezes", ['starts_on' => '2026-10-28', 'days' => 2])->assertCreated()->json('data.id');
        $this->deleteJson("/api/v1/me/member/freezes/{$second}")->assertOk()->assertJsonPath('data.status', 'cancelled');

        // Someone else's membership is invisible.
        $other = Member::factory()->at($this->bole)->create();
        $foreign = Membership::create([...$this->membership->only('organization_id', 'branch_id', 'plan_id', 'plan_snapshot', 'status'), 'member_id' => $other->id, 'starts_on' => '2026-10-20', 'ends_on' => '2026-11-18']);
        $this->postJson("/api/v1/me/member/memberships/{$foreign->ulid}/freezes", ['starts_on' => '2026-10-28', 'days' => 2])->assertNotFound();
    }

    public function test_voiding_the_sale_cancels_open_freezes(): void
    {
        Membership::query()->delete();
        Sanctum::actingAs($this->reception);
        $invoice = $this->postJson("/api/v1/branches/{$this->bole->ulid}/sales", [
            'member' => $this->member->ulid, 'plan' => $this->monthly->ulid, 'payment' => ['method' => 'cash', 'amount_cents' => 250000],
        ])->assertCreated()->json('data.id');
        $this->membership = Membership::firstOrFail();
        $this->freeze(['starts_on' => '2026-11-01', 'days' => 3])->assertCreated();

        Sanctum::actingAs($this->manager);
        $this->postJson("/api/v1/invoices/{$invoice}/void", ['reason' => 'Wrong plan'])->assertOk();
        $this->assertSame('cancelled', MembershipFreeze::firstOrFail()->status);
    }
}
