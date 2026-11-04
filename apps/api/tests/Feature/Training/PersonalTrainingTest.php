<?php

namespace Tests\Feature\Training;

use App\Enums\AccountType;
use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Member;
use App\Models\Organization;
use App\Models\PtEnrollment;
use App\Models\PtPackage;
use App\Models\PtSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonalTrainingTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Branch $bole;

    private User $owner;

    private User $reception;

    private User $trainer;

    private User $otherTrainer;

    private Member $member;

    private User $memberUser;

    private PtPackage $five;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00', 'Africa/Addis_Ababa'));

        $this->organization = Organization::factory()->create(['timezone' => 'Africa/Addis_Ababa']);
        $this->bole = Branch::factory()->create(['organization_id' => $this->organization->id]);
        $this->owner = User::factory()->owner()->create(['organization_id' => $this->organization->id]);
        $this->reception = User::factory()->staffAt($this->bole, BranchRole::Receptionist)->create();
        $this->trainer = User::factory()->staffAt($this->bole, BranchRole::Trainer)->create();
        $this->otherTrainer = User::factory()->staffAt($this->bole, BranchRole::Trainer)->create();
        $this->memberUser = User::factory()->create(['organization_id' => $this->organization->id, 'account_type' => AccountType::Member]);
        $this->member = Member::factory()->at($this->bole)->create(['user_id' => $this->memberUser->id]);
        $this->five = PtPackage::create(['organization_id' => $this->organization->id, 'name' => ['en' => '5 PT sessions'], 'sessions_count' => 2, 'price_cents' => 400000, 'validity_days' => 30]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function sell(?array $payment = ['method' => 'cash', 'amount_cents' => 400000]): string
    {
        Sanctum::actingAs($this->reception);

        return $this->postJson("/api/v1/branches/{$this->bole->ulid}/pt-sales", [
            'member' => $this->member->ulid, 'package' => $this->five->ulid, 'trainer' => $this->trainer->ulid, 'payment' => $payment,
        ])->assertCreated()->json('data.id');
    }

    private function enrollment(): PtEnrollment
    {
        return PtEnrollment::firstOrFail();
    }

    private function schedule(string $local, ?User $as = null)
    {
        Sanctum::actingAs($as ?? $this->trainer);

        return $this->postJson("/api/v1/pt-enrollments/{$this->enrollment()->ulid}/sessions", ['starts_at' => $local, 'duration_min' => 60]);
    }

    public function test_selling_a_package_invoices_it_and_payment_activates_it(): void
    {
        $invoice = $this->sell(null);
        $this->assertSame('pending_payment', $this->enrollment()->status);
        $this->schedule('2026-11-03T17:00')->assertUnprocessable();

        Sanctum::actingAs($this->reception);
        $this->postJson("/api/v1/invoices/{$invoice}/payments", ['method' => 'cash', 'amount_cents' => 400000])->assertSuccessful();
        $enrollment = $this->enrollment();
        $this->assertSame('active', $enrollment->status);
        $this->assertSame('2026-11-02', $enrollment->starts_on->toDateString());
        $this->assertSame('2026-12-01', $enrollment->ends_on->toDateString());

        // The invoice line is the package.
        $this->getJson("/api/v1/invoices/{$invoice}")->assertJsonPath('data.items.0.description', '5 PT sessions');
    }

    public function test_package_needs_a_trainer_and_a_fixed_trainer_is_kept(): void
    {
        Sanctum::actingAs($this->reception);
        $this->postJson("/api/v1/branches/{$this->bole->ulid}/pt-sales/quote", ['member' => $this->member->ulid, 'package' => $this->five->ulid])->assertJsonValidationErrors('trainer');
        $this->five->update(['trainer_id' => $this->trainer->id]);
        $this->postJson("/api/v1/branches/{$this->bole->ulid}/pt-sales/quote", ['member' => $this->member->ulid, 'package' => $this->five->ulid])->assertOk()->assertJsonPath('data.total_cents', 400000);
        $this->postJson("/api/v1/branches/{$this->bole->ulid}/pt-sales/quote", ['member' => $this->member->ulid, 'package' => $this->five->ulid, 'trainer' => $this->otherTrainer->ulid])->assertJsonValidationErrors('trainer');
    }

    public function test_scheduling_respects_sessions_left_dates_and_clashes(): void
    {
        $this->sell();

        $this->schedule('2026-11-03T17:00')->assertCreated();
        // The trainer is busy then.
        $this->schedule('2026-11-03T17:30')->assertUnprocessable();
        $this->schedule('2026-11-04T17:00')->assertCreated();
        // Two of two are on the calendar.
        $this->schedule('2026-11-05T17:00')->assertUnprocessable();

        // Clashes with a class the trainer leads, and dates past the end.
        PtSession::query()->delete();
        $type = ClassType::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Spin']]);
        ClassSession::create(['organization_id' => $this->organization->id, 'branch_id' => $this->bole->id, 'class_type_id' => $type->id, 'trainer_id' => $this->trainer->id, 'starts_at' => Carbon::parse('2026-11-06 18:00', 'Africa/Addis_Ababa')->utc(), 'ends_at' => Carbon::parse('2026-11-06 18:45', 'Africa/Addis_Ababa')->utc(), 'capacity' => 10]);
        $this->schedule('2026-11-06T18:30')->assertUnprocessable()->assertJsonPath('errors.starts_at.0', 'The trainer already has a session or class at that time.');
        $this->schedule('2026-12-05T10:00')->assertUnprocessable();
    }

    public function test_done_and_no_show_use_sessions_and_the_last_completes_the_package(): void
    {
        $this->sell();
        $first = $this->schedule('2026-11-02T09:10')->json('data.id');
        $second = $this->schedule('2026-11-02T11:00')->json('data.id');

        $this->postJson("/api/v1/pt-sessions/{$second}/status", ['status' => 'completed'])->assertUnprocessable();
        $this->postJson("/api/v1/pt-sessions/{$first}/status", ['status' => 'completed'])->assertOk();
        $this->assertSame(1, $this->enrollment()->sessions_remaining);

        Carbon::setTestNow(Carbon::parse('2026-11-02 11:00', 'Africa/Addis_Ababa'));
        $this->postJson("/api/v1/pt-sessions/{$second}/status", ['status' => 'no_show'])->assertOk();
        $this->assertSame('completed', $this->enrollment()->status);

        // Undo gives the session back.
        $this->postJson("/api/v1/pt-sessions/{$second}/status", ['status' => 'scheduled'])->assertOk();
        $this->assertSame('active', $this->enrollment()->status);
        $this->assertSame(1, $this->enrollment()->sessions_remaining);
    }

    public function test_trainers_only_see_their_own_clients_and_notes_stay_private(): void
    {
        $this->sell();
        $id = $this->schedule('2026-11-03T17:00')->json('data.id');
        $this->patchJson("/api/v1/pt-sessions/{$id}/notes", ['notes' => 'Knee pain, go easy on squats'])->assertOk();

        $this->getJson('/api/v1/pt-clients')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/pt-sessions?mine=1&days=7')->assertJsonPath('data.0.trainer_notes', 'Knee pain, go easy on squats');

        Sanctum::actingAs($this->otherTrainer);
        $this->getJson('/api/v1/pt-clients')->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/pt-sessions/{$id}/cancel")->assertForbidden();
        $this->patchJson("/api/v1/pt-sessions/{$id}/notes", ['notes' => 'x'])->assertForbidden();
        $this->getJson("/api/v1/members/{$this->member->ulid}/pt-enrollments")->assertJsonCount(0, 'data');

        // The desk sees the package but never the notes.
        Sanctum::actingAs($this->reception);
        $this->getJson("/api/v1/members/{$this->member->ulid}/pt-enrollments")->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.upcoming.0.trainer_notes');

        Sanctum::actingAs($this->memberUser);
        $this->getJson('/api/v1/me/member/training')->assertOk()->assertJsonPath('data.0.sessions_remaining', 2)->assertJsonPath('data.0.upcoming.0.status', 'scheduled')->assertJsonMissingPath('data.0.upcoming.0.trainer_notes');
    }

    public function test_voiding_and_expiry(): void
    {
        $invoice = $this->sell();
        $this->schedule('2026-11-03T17:00');

        Carbon::setTestNow(Carbon::parse('2026-12-02 01:00', 'Africa/Addis_Ababa'));
        $this->artisan('training:expire')->assertSuccessful();
        $this->assertSame('expired', $this->enrollment()->status);
        $this->assertSame('cancelled', PtSession::first()->status);

        Sanctum::actingAs(User::factory()->staffAt($this->bole, BranchRole::Manager)->create());
        $this->postJson("/api/v1/invoices/{$invoice}/void", ['reason' => 'Wrong package'])->assertOk();
        $this->assertSame('cancelled', $this->enrollment()->status);
    }

    public function test_trainer_profile_and_owner_only_packages(): void
    {
        Sanctum::actingAs($this->trainer);
        $this->putJson('/api/v1/me/trainer-profile', ['bio' => ['en' => 'Strength coach'], 'specialties' => ['Strength', 'Boxing']])->assertOk()->assertJsonPath('data.specialties.1', 'Boxing');
        $this->postJson('/api/v1/pt-packages', ['name' => ['en' => 'x'], 'sessions_count' => 5, 'price_cents' => 1, 'validity_days' => 30])->assertForbidden();

        Sanctum::actingAs($this->reception);
        $this->getJson('/api/v1/me/trainer-profile')->assertForbidden();
        $this->getJson("/api/v1/branches/{$this->bole->ulid}/trainers")->assertOk()->assertJsonCount(2, 'data');

        Sanctum::actingAs($this->owner);
        $this->postJson('/api/v1/pt-packages', ['name' => ['en' => '10 sessions'], 'sessions_count' => 10, 'price_cents' => 750000, 'validity_days' => 90])->assertCreated();
    }
}
