<?php

namespace Tests\Feature\Admin;

use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Branch $bole;

    private Branch $piassa;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->bole = Branch::factory()->create(['organization_id' => $this->organization->id]);
        $this->piassa = Branch::factory()->create(['organization_id' => $this->organization->id]);
        $this->owner = User::factory()->owner()->create(['organization_id' => $this->organization->id]);
    }

    private function newStaff(array $assignments, array $overrides = []): array
    {
        return [
            'name' => 'Selam Tesfaye',
            'phone' => '0922112233',
            'password' => 'password1',
            'assignments' => $assignments,
            ...$overrides,
        ];
    }

    public function test_owner_creates_staff_who_can_then_sign_in(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/v1/staff', $this->newStaff([
            ['branch' => $this->bole->ulid, 'role' => 'manager'],
            ['branch' => $this->piassa->ulid, 'role' => 'trainer'],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.phone', '+251922112233')
            ->assertJsonCount(2, 'data.assignments');

        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/auth/login', [
            'organization' => $this->organization->slug,
            'phone' => '0922112233',
            'password' => 'password1',
            'device_name' => 'x',
        ])->assertOk();
    }

    public function test_phone_must_be_unique_within_the_organization(): void
    {
        Sanctum::actingAs($this->owner);
        User::factory()->create(['organization_id' => $this->organization->id, 'phone' => '+251922112233']);

        $this->postJson('/api/v1/staff', $this->newStaff([['branch' => $this->bole->ulid, 'role' => 'trainer']]))
            ->assertJsonValidationErrors('phone');
    }

    public function test_manager_adds_receptionist_to_own_branch_only(): void
    {
        Sanctum::actingAs(User::factory()->staffAt($this->bole, BranchRole::Manager)->create());

        $this->postJson('/api/v1/staff', $this->newStaff([['branch' => $this->piassa->ulid, 'role' => 'receptionist']]))
            ->assertJsonValidationErrors('assignments.0.branch');

        $this->postJson('/api/v1/staff', $this->newStaff([['branch' => $this->bole->ulid, 'role' => 'manager']]))
            ->assertJsonValidationErrors('assignments.0.role');

        $this->postJson('/api/v1/staff', $this->newStaff([['branch' => $this->bole->ulid, 'role' => 'receptionist']]))
            ->assertCreated();
    }

    public function test_receptionists_and_trainers_cannot_manage_staff(): void
    {
        Sanctum::actingAs(User::factory()->staffAt($this->bole, BranchRole::Receptionist)->create());

        $this->getJson('/api/v1/staff')->assertForbidden();
        $this->postJson('/api/v1/staff', $this->newStaff([['branch' => $this->bole->ulid, 'role' => 'trainer']]))->assertForbidden();
    }

    public function test_manager_sees_only_staff_in_branches_they_manage(): void
    {
        $manager = User::factory()->staffAt($this->bole, BranchRole::Manager)->create();
        $boleTrainer = User::factory()->staffAt($this->bole, BranchRole::Trainer)->create();
        $piassaTrainer = User::factory()->staffAt($this->piassa, BranchRole::Trainer)->create();

        Sanctum::actingAs($manager);

        $ids = collect($this->getJson('/api/v1/staff')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($boleTrainer->ulid));
        $this->assertFalse($ids->contains($piassaTrainer->ulid));
        $this->getJson("/api/v1/staff/{$piassaTrainer->ulid}")->assertForbidden();
    }

    public function test_manager_cannot_edit_other_managers_or_owner(): void
    {
        $manager = User::factory()->staffAt($this->bole, BranchRole::Manager)->create();
        $otherManager = User::factory()->staffAt($this->bole, BranchRole::Manager)->create();

        Sanctum::actingAs($manager);

        $this->patchJson("/api/v1/staff/{$otherManager->ulid}", ['name' => 'X'])->assertForbidden();
        $this->patchJson("/api/v1/staff/{$this->owner->ulid}", ['status' => 'disabled'])->assertForbidden();
    }

    public function test_manager_reassigning_keeps_assignments_in_other_branches(): void
    {
        $manager = User::factory()->staffAt($this->bole, BranchRole::Manager)->create();
        $trainer = User::factory()->staffAt($this->bole, BranchRole::Trainer)->create();
        $trainer->branches()->attach($this->piassa->id, ['role' => 'trainer']);

        Sanctum::actingAs($manager);

        $this->patchJson("/api/v1/staff/{$trainer->ulid}", [
            'assignments' => [['branch' => $this->bole->ulid, 'role' => 'receptionist']],
        ])->assertOk();

        $roles = $trainer->branches()->get()->mapWithKeys(fn ($b) => [$b->id => $b->pivot->role->value]);
        $this->assertSame([$this->bole->id => 'receptionist', $this->piassa->id => 'trainer'], $roles->sortKeys()->all());
    }

    public function test_disabling_staff_revokes_their_tokens(): void
    {
        $trainer = User::factory()->staffAt($this->bole, BranchRole::Trainer)->create();
        $trainer->createToken('phone');

        Sanctum::actingAs($this->owner);

        $this->patchJson("/api/v1/staff/{$trainer->ulid}", ['status' => 'disabled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'disabled');

        $this->assertSame(0, $trainer->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.updated', 'subject_id' => $trainer->id]);
    }

    public function test_owner_cannot_edit_themselves_through_staff_endpoint(): void
    {
        Sanctum::actingAs($this->owner);

        $this->patchJson("/api/v1/staff/{$this->owner->ulid}", ['status' => 'disabled'])->assertForbidden();
    }
}
