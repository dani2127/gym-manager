<?php

namespace Tests\Feature\Auth;

use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_reports_abilities_per_role(): void
    {
        $branch = Branch::factory()->create();
        $owner = User::factory()->owner()->create(['organization_id' => $branch->organization_id]);
        $trainer = User::factory()->staffAt($branch, BranchRole::Trainer)->create();

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/me')
            ->assertJsonPath('abilities.manage_branches', true)
            ->assertJsonPath('abilities.manage_staff', true)
            ->assertJsonPath('branches.0.role', null);

        Sanctum::actingAs($trainer);
        $this->getJson('/api/v1/me')
            ->assertJsonPath('abilities.manage_branches', false)
            ->assertJsonPath('abilities.manage_staff', false)
            ->assertJsonPath('branches.0.role', 'trainer');
    }

    public function test_update_locale_and_password(): void
    {
        $user = User::factory()->owner()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/me', ['locale' => 'am'])->assertOk();
        $this->assertSame('am', $user->fresh()->locale);

        $this->putJson('/api/v1/me/password', ['current_password' => 'wrong', 'password' => 'new-password', 'password_confirmation' => 'new-password'])
            ->assertJsonValidationErrors('current_password');

        $this->putJson('/api/v1/me/password', ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])
            ->assertOk();
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_members_cannot_reach_staff_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/me')->assertOk();
        $this->getJson('/api/v1/branches')->assertForbidden();
        $this->getJson('/api/v1/settings')->assertForbidden();
    }
}
