<?php

namespace Tests\Feature\Classes;

use App\Enums\AccountType;
use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\ClassBooking;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClassesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Branch $bole;

    private Branch $piassa;

    private User $owner;

    private User $manager;

    private User $reception;

    private User $trainer;

    private ClassType $spin;

    protected function setUp(): void
    {
        parent::setUp();
        // Monday 2 November 2026, 09:00 in Addis Ababa.
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00', 'Africa/Addis_Ababa'));
        Notification::fake();

        $this->organization = Organization::factory()->create(['timezone' => 'Africa/Addis_Ababa']);
        $this->bole = Branch::factory()->create(['organization_id' => $this->organization->id]);
        $this->piassa = Branch::factory()->create(['organization_id' => $this->organization->id]);
        $this->owner = User::factory()->owner()->create(['organization_id' => $this->organization->id]);
        $this->manager = User::factory()->staffAt($this->bole, BranchRole::Manager)->create();
        $this->reception = User::factory()->staffAt($this->bole, BranchRole::Receptionist)->create();
        $this->trainer = User::factory()->staffAt($this->bole, BranchRole::Trainer)->create();
        $this->spin = ClassType::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Spinning'], 'color' => 'track', 'default_duration_min' => 45, 'default_capacity' => 2]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function member(?int $classCredits = null, ?Branch $home = null, string $scope = 'home_branch'): Member
    {
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'account_type' => AccountType::Member]);
        $member = Member::factory()->at($home ?? $this->bole)->create(['user_id' => $user->id]);
        $plan = Plan::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Plan'], 'type' => 'time', 'duration_days' => 30, 'price_cents' => 100000, 'class_credits' => $classCredits, 'access_scope' => $scope]);
        Membership::create(['organization_id' => $this->organization->id, 'branch_id' => $member->home_branch_id, 'member_id' => $member->id, 'plan_id' => $plan->id, 'plan_snapshot' => $plan->snapshot(), 'starts_on' => '2026-10-20', 'ends_on' => '2026-11-30', 'status' => 'active']);

        return $member;
    }

    private function makeClass(string $local = '2026-11-03 18:00', int $capacity = 2): ClassSession
    {
        $starts = Carbon::parse($local, 'Africa/Addis_Ababa')->utc();

        return ClassSession::create(['organization_id' => $this->organization->id, 'branch_id' => $this->bole->id, 'class_type_id' => $this->spin->id, 'trainer_id' => $this->trainer->id, 'starts_at' => $starts, 'ends_at' => $starts->copy()->addMinutes(45), 'capacity' => $capacity]);
    }

    private function bookAs(Member $member, ClassSession $session)
    {
        Sanctum::actingAs($member->user);

        return $this->postJson("/api/v1/me/member/classes/{$session->ulid}/book");
    }

    public function test_timetable_makes_sessions_and_editing_moves_only_unbooked_ones(): void
    {
        Sanctum::actingAs($this->manager);
        $id = $this->postJson("/api/v1/branches/{$this->bole->ulid}/class-schedules", [
            'class_type' => $this->spin->ulid, 'trainer' => $this->trainer->ulid, 'weekday' => 2, 'start_time' => '18:00', 'duration_min' => 45, 'capacity' => 12,
        ])->assertCreated()->json('data.id');

        // Tuesdays for the next four weeks, at 18:00 Addis time.
        $sessions = ClassSession::orderBy('starts_at')->get();
        $this->assertCount(4, $sessions);
        $this->assertSame('2026-11-03 18:00', $sessions[0]->starts_at->setTimezone('Africa/Addis_Ababa')->format('Y-m-d H:i'));
        $this->artisan('classes:generate')->assertSuccessful();
        $this->assertSame(4, ClassSession::count());

        // Someone books the first one; then the slot moves to 19:00.
        $this->bookAs($this->member(), $sessions[0])->assertCreated();
        Sanctum::actingAs($this->manager);
        $this->patchJson("/api/v1/branches/{$this->bole->ulid}/class-schedules/{$id}", ['start_time' => '19:00'])->assertOk();

        $times = ClassSession::orderBy('starts_at')->get()->map(fn ($s) => $s->starts_at->setTimezone('Africa/Addis_Ababa')->format('m-d H:i'))->all();
        $this->assertSame(['11-03 18:00', '11-10 19:00', '11-17 19:00', '11-24 19:00'], $times);

        // Receptionists can't change the timetable.
        Sanctum::actingAs($this->reception);
        $this->postJson("/api/v1/branches/{$this->bole->ulid}/class-schedules", ['class_type' => $this->spin->ulid, 'weekday' => 1, 'start_time' => '07:00', 'duration_min' => 45, 'capacity' => 10])->assertForbidden();
    }

    public function test_full_class_waitlists_and_a_cancellation_moves_the_first_one_in(): void
    {
        $session = $this->makeClass(capacity: 2);
        [$a, $b, $c, $d] = [$this->member(), $this->member(), $this->member(), $this->member()];

        $this->bookAs($a, $session)->assertCreated()->assertJsonPath('data.status', 'booked');
        $this->bookAs($b, $session)->assertCreated()->assertJsonPath('data.status', 'booked');
        $this->bookAs($c, $session)->assertCreated()->assertJsonPath('data.status', 'waitlisted')->assertJsonPath('data.waitlist_position', 1);
        $this->bookAs($d, $session)->assertJsonPath('data.waitlist_position', 2);
        $this->bookAs($a, $session)->assertUnprocessable();

        Sanctum::actingAs($a->user);
        $this->deleteJson("/api/v1/me/member/classes/{$session->ulid}/booking")->assertOk()->assertJsonPath('data.late_cancel', false);

        $session->refresh();
        $this->assertSame(2, $session->booked_count);
        $this->assertSame(1, $session->waitlist_count);
        $this->assertSame('booked', ClassBooking::where('member_id', $c->id)->value('status'));
        $this->assertSame(1, ClassBooking::where('member_id', $d->id)->value('waitlist_position'));
    }

    public function test_membership_rules(): void
    {
        $session = $this->makeClass(capacity: 10);

        $noClasses = $this->member(classCredits: 0);
        $this->bookAs($noClasses, $session)->assertUnprocessable()->assertJsonPath('errors.member.0', 'Classes aren\'t included in this plan.');

        $other = $this->member(home: $this->piassa);
        Sanctum::actingAs($other->user);
        // A Piassa-only member doesn't even see Bole classes.
        $this->getJson('/api/v1/me/member/classes')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/me/member/classes/{$session->ulid}/book")->assertNotFound();

        $everywhere = $this->member(home: $this->piassa, scope: 'all_branches');
        $this->bookAs($everywhere, $session)->assertCreated();

        $twoCredits = $this->member(classCredits: 2);
        $this->bookAs($twoCredits, $session)->assertCreated();
        $this->bookAs($twoCredits, $this->makeClass('2026-11-04 18:00', 10))->assertCreated();
        $this->bookAs($twoCredits, $this->makeClass('2026-11-05 18:00', 10))->assertUnprocessable()->assertJsonPath('errors.member.0', 'No class credits left on this membership.');

        // More than a week ahead isn't open yet.
        $this->bookAs($this->member(), $this->makeClass('2026-11-12 18:00'))->assertUnprocessable();
    }

    public function test_late_cancel_keeps_the_credit_used(): void
    {
        $member = $this->member(classCredits: 1);
        $session = $this->makeClass('2026-11-02 10:30', 10);
        $this->bookAs($member, $session)->assertCreated();

        // 90 minutes before: inside the 2-hour cutoff.
        $this->deleteJson("/api/v1/me/member/classes/{$session->ulid}/booking")->assertOk()->assertJsonPath('data.late_cancel', true);
        $this->bookAs($member, $this->makeClass('2026-11-03 18:00', 10))->assertUnprocessable();
    }

    public function test_member_app_list_shows_my_bookings_and_why_i_cant_book(): void
    {
        $member = $this->member();
        $mine = $this->makeClass('2026-11-03 18:00', 10);
        $this->makeClass('2026-11-04 18:00', 10);
        $this->bookAs($member, $mine);

        $data = $this->getJson('/api/v1/me/member/classes')->assertOk()->json('data');
        $this->assertSame('booked', $data[0]['my_booking']['status']);
        $this->assertTrue($data[1]['can_book']);
        $this->getJson('/api/v1/me/member/class-bookings')->assertJsonCount(1, 'data');
    }

    public function test_desk_books_and_trainer_takes_attendance(): void
    {
        $member = $this->member();
        $session = $this->makeClass('2026-11-02 09:20', 10);

        Sanctum::actingAs($this->reception);
        $id = $this->postJson("/api/v1/class-sessions/{$session->ulid}/bookings", ['member' => $member->ulid])->assertCreated()->json('data.id');

        // The desk's day view, as the web app asks for it.
        $this->getJson("/api/v1/class-sessions?branch={$this->bole->ulid}&from=2026-11-02&days=1")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/class-sessions?branch={$this->bole->ulid}&from=2026-11-03&days=1")->assertOk()->assertJsonCount(0, 'data');

        Sanctum::actingAs($this->trainer);
        $this->getJson('/api/v1/class-sessions?mine=1&days=7')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/class-sessions/{$session->ulid}")->assertOk()->assertJsonPath('data.bookings.0.member.id', $member->ulid);
        $this->postJson("/api/v1/class-bookings/{$id}/attendance", ['status' => 'attended'])->assertOk()->assertJsonPath('data.status', 'attended');

        // Another trainer can't see this roster.
        Sanctum::actingAs(User::factory()->staffAt($this->piassa, BranchRole::Trainer)->create());
        $this->getJson("/api/v1/class-sessions/{$session->ulid}")->assertForbidden();
    }

    public function test_attendance_opens_shortly_before_the_class(): void
    {
        $member = $this->member();
        $session = $this->makeClass('2026-11-03 18:00', 10);
        $this->bookAs($member, $session);
        Sanctum::actingAs($this->trainer);
        $this->postJson('/api/v1/class-bookings/'.ClassBooking::first()->ulid.'/attendance', ['status' => 'attended'])->assertUnprocessable();
    }

    public function test_cancelling_a_class_frees_everyone(): void
    {
        $session = $this->makeClass(capacity: 1);
        $a = $this->member(classCredits: 1);
        $b = $this->member();
        $this->bookAs($a, $session);
        $this->bookAs($b, $session);

        Sanctum::actingAs($this->reception);
        $this->postJson("/api/v1/class-sessions/{$session->ulid}/cancel", ['reason' => 'Trainer is ill'])->assertForbidden();
        Sanctum::actingAs($this->manager);
        $this->postJson("/api/v1/class-sessions/{$session->ulid}/cancel", ['reason' => 'Trainer is ill'])->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(0, ClassBooking::whereIn('status', ['booked', 'waitlisted'])->count());
        // The credit came back.
        $this->bookAs($a, $this->makeClass('2026-11-04 18:00', 5))->assertCreated();
    }

    public function test_capacity_cannot_drop_below_bookings_and_only_owner_edits_types(): void
    {
        $session = $this->makeClass(capacity: 5);
        $this->bookAs($this->member(), $session);
        $this->bookAs($this->member(), $session);

        Sanctum::actingAs($this->manager);
        $this->patchJson("/api/v1/class-sessions/{$session->ulid}", ['capacity' => 1])->assertUnprocessable();
        $this->patchJson("/api/v1/class-sessions/{$session->ulid}", ['capacity' => 2])->assertOk();
        $this->postJson('/api/v1/class-types', ['name' => ['en' => 'Yoga'], 'color' => 'infield', 'default_duration_min' => 60, 'default_capacity' => 15])->assertForbidden();

        Sanctum::actingAs($this->owner);
        $this->postJson('/api/v1/class-types', ['name' => ['en' => 'Yoga', 'am' => 'ዮጋ'], 'color' => 'infield', 'default_duration_min' => 60, 'default_capacity' => 15])->assertCreated();
    }
}
