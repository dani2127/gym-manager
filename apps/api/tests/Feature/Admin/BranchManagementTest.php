<?php

namespace Tests\Feature\Admin;

use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchManagementTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Branch $bole;

    private Branch $piassa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->bole = Branch::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Bole']);
        $this->piassa = Branch::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Piassa']);
    }

    private function owner(): User
    {
        return User::factory()->owner()->create(['organization_id' => $this->organization->id]);
    }

    public function test_owner_creates_a_branch_with_default_hours(): void
    {
        Sanctum::actingAs($this->owner());

        $this->postJson('/api/v1/branches', ['name' => 'Megenagna', 'code' => 'MEG', 'phone' => '0911000000'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'MEG')
            ->assertJsonPath('data.phone', '+251911000000')
            ->assertJsonCount(7, 'data.opening_hours')
            ->assertJsonPath('data.opening_hours.0.opens_at', '06:00');

        $this->assertDatabaseHas('audit_logs', ['action' => 'branch.created']);
    }

    public function test_branch_codes_are_unique_per_organization(): void
    {
        Sanctum::actingAs($this->owner());

        $this->postJson('/api/v1/branches', ['name' => 'Dup', 'code' => $this->bole->code])
            ->assertJsonValidationErrors('code');
    }

    public function test_staff_only_see_their_assigned_branches(): void
    {
        Sanctum::actingAs(User::factory()->staffAt($this->bole, BranchRole::Receptionist)->create());

        $this->getJson('/api/v1/branches')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->bole->ulid);
        $this->getJson("/api/v1/branches/{$this->piassa->ulid}")->assertForbidden();
        $this->getJson('/api/v1/me')->assertJsonPath('branches.0.role', 'receptionist');
    }

    public function test_only_owners_create_branches(): void
    {
        Sanctum::actingAs(User::factory()->staffAt($this->bole, BranchRole::Manager)->create());

        $this->postJson('/api/v1/branches', ['name' => 'X', 'code' => 'X'])->assertForbidden();
    }

    public function test_manager_updates_own_branch_but_not_status_or_other_branches(): void
    {
        Sanctum::actingAs(User::factory()->staffAt($this->bole, BranchRole::Manager)->create());

        $this->patchJson("/api/v1/branches/{$this->bole->ulid}", ['name' => 'Bole Medhanialem'])->assertOk();
        $this->patchJson("/api/v1/branches/{$this->bole->ulid}", ['status' => 'inactive'])->assertForbidden();
        $this->patchJson("/api/v1/branches/{$this->piassa->ulid}", ['name' => 'Nope'])->assertForbidden();
    }

    public function test_receptionist_cannot_edit_branch(): void
    {
        Sanctum::actingAs(User::factory()->staffAt($this->bole, BranchRole::Receptionist)->create());

        $this->patchJson("/api/v1/branches/{$this->bole->ulid}", ['name' => 'X'])->assertForbidden();
    }

    public function test_opening_hours_replace_the_week(): void
    {
        Sanctum::actingAs($this->owner());

        $days = collect(range(0, 6))->map(fn ($d) => $d === 0
            ? ['weekday' => 0, 'is_closed' => true]
            : ['weekday' => $d, 'is_closed' => false, 'opens_at' => '05:30', 'closes_at' => '21:00'])->all();

        $this->putJson("/api/v1/branches/{$this->bole->ulid}/opening-hours", ['days' => $days])
            ->assertOk()
            ->assertJsonPath('data.opening_hours.0.is_closed', true)
            ->assertJsonPath('data.opening_hours.0.opens_at', null)
            ->assertJsonPath('data.opening_hours.1.opens_at', '05:30');
    }

    public function test_opening_hours_reject_closing_before_opening(): void
    {
        Sanctum::actingAs($this->owner());

        $days = collect(range(0, 6))->map(fn ($d) => ['weekday' => $d, 'is_closed' => false, 'opens_at' => '22:00', 'closes_at' => '06:00'])->all();

        $this->putJson("/api/v1/branches/{$this->bole->ulid}/opening-hours", ['days' => $days])
            ->assertJsonValidationErrors('days.0.closes_at');
    }

    public function test_closures_can_be_added_and_removed(): void
    {
        Sanctum::actingAs($this->owner());
        $date = now()->addDays(10)->toDateString();

        $id = $this->postJson("/api/v1/branches/{$this->bole->ulid}/closures", ['date' => $date, 'reason' => 'Timkat'])
            ->assertCreated()
            ->json('data.id');

        $this->getJson("/api/v1/branches/{$this->bole->ulid}/closures")->assertJsonCount(1, 'data');
        $this->deleteJson("/api/v1/branches/{$this->piassa->ulid}/closures/{$id}")->assertNotFound();
        $this->deleteJson("/api/v1/branches/{$this->bole->ulid}/closures/{$id}")->assertNoContent();
    }
}
