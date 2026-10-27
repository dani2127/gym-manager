<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return [
            'organization_name' => 'Lion Fitness',
            'organization_slug' => 'lion-fitness',
            'branch_name' => 'Bole',
            'name' => 'Abebe Kebede',
            'phone' => '0911 23 45 67',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'device_name' => 'tests',
            ...$overrides,
        ];
    }

    public function test_creates_organization_first_branch_and_owner(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload())
            ->assertCreated()
            ->assertJsonStructure(['token', 'token_type']);

        $organization = Organization::where('slug', 'lion-fitness')->firstOrFail();
        $owner = User::where('organization_id', $organization->id)->firstOrFail();

        $this->assertSame(AccountType::Owner, $owner->account_type);
        $this->assertSame('+251911234567', $owner->phone);
        $this->assertSame(['MAIN'], $organization->branches()->pluck('code')->all());
        $this->assertCount(7, $organization->branches()->first()->openingHours);
        $this->assertDatabaseHas('audit_logs', ['organization_id' => $organization->id, 'action' => 'organization.registered']);
    }

    public function test_rejects_taken_slug_and_invalid_phone(): void
    {
        Organization::factory()->create(['slug' => 'lion-fitness']);

        $this->postJson('/api/v1/auth/register', $this->payload(['phone' => '0111234567']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['organization_slug', 'phone']);
    }

    public function test_slug_must_be_url_safe(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(['organization_slug' => 'Lion Fitness!']))
            ->assertJsonValidationErrors('organization_slug');
    }
}
