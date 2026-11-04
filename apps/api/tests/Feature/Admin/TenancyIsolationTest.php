<?php

namespace Tests\Feature\Admin;

use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Nothing belonging to one gym may be visible to or changeable by another. */
class TenancyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerA;

    private Branch $branchB;

    private User $staffB;

    protected function setUp(): void
    {
        parent::setUp();

        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $this->ownerA = User::factory()->owner()->create(['organization_id' => $orgA->id]);
        Branch::factory()->create(['organization_id' => $orgA->id]);
        $this->branchB = Branch::factory()->create(['organization_id' => $orgB->id]);
        $this->staffB = User::factory()->staffAt($this->branchB, BranchRole::Receptionist)->create();

        Sanctum::actingAs($this->ownerA);
    }

    public function test_branch_list_only_contains_own_branches(): void
    {
        $this->getJson('/api/v1/branches')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissing(['id' => $this->branchB->ulid]);
    }

    public function test_other_organizations_branch_is_not_found(): void
    {
        $this->getJson("/api/v1/branches/{$this->branchB->ulid}")->assertNotFound();
        $this->patchJson("/api/v1/branches/{$this->branchB->ulid}", ['name' => 'Hacked'])->assertNotFound();
        $this->putJson("/api/v1/branches/{$this->branchB->ulid}/opening-hours", ['days' => []])->assertNotFound();
        $this->patchJson("/api/v1/branches/{$this->branchB->ulid}/settings", ['settings' => ['tax.enabled' => true]])->assertNotFound();

        $this->assertNotSame('Hacked', $this->branchB->fresh()->name);
    }

    public function test_other_organizations_staff_is_invisible(): void
    {
        $this->getJson('/api/v1/staff')->assertOk()->assertJsonMissing(['id' => $this->staffB->ulid]);
        $this->getJson("/api/v1/staff/{$this->staffB->ulid}")->assertNotFound();
        $this->patchJson("/api/v1/staff/{$this->staffB->ulid}", ['status' => 'disabled'])->assertNotFound();
    }

    public function test_cannot_assign_staff_to_another_organizations_branch(): void
    {
        $this->postJson('/api/v1/staff', [
            'name' => 'Spy',
            'phone' => '0922000000',
            'password' => 'password1',
            'assignments' => [['branch' => $this->branchB->ulid, 'role' => 'receptionist']],
        ])->assertJsonValidationErrors('assignments.0.branch');
    }

    public function test_settings_are_per_organization(): void
    {
        $this->patchJson('/api/v1/settings', ['settings' => ['tax.enabled' => true]])->assertOk();

        Sanctum::actingAs(User::factory()->owner()->create(['organization_id' => $this->branchB->organization_id]));

        $this->assertFalse($this->getJson('/api/v1/settings')->json('settings')['tax.enabled']);
    }

    public function test_audit_log_only_shows_own_organization(): void
    {
        $this->patchJson('/api/v1/settings', ['settings' => ['lockers.enabled' => true]]);

        Sanctum::actingAs(User::factory()->owner()->create(['organization_id' => $this->branchB->organization_id]));

        $this->getJson('/api/v1/audit-logs')->assertOk()->assertJsonCount(0, 'data');
    }
}
