<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create(['slug' => 'lion-fitness']);
        $this->owner = User::factory()->owner()->create([
            'organization_id' => $this->organization->id,
            'phone' => '+251911234567',
        ]);
    }

    private function credentials(array $overrides = []): array
    {
        return ['organization' => 'lion-fitness', 'phone' => '0911234567', 'password' => 'password', ...$overrides];
    }

    public function test_token_login_with_local_phone_format(): void
    {
        $token = $this->postJson('/api/v1/auth/login', $this->credentials(['device_name' => 'android']))
            ->assertOk()
            ->json('token');

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('user.id', $this->owner->ulid)
            ->assertJsonPath('organization.slug', 'lion-fitness');

        $this->assertNotNull($this->owner->fresh()->last_login_at);
    }

    public function test_spa_session_login_and_logout(): void
    {
        $spa = ['Referer' => 'http://localhost:3000', 'Origin' => 'http://localhost:3000'];

        $this->withHeaders($spa)->postJson('/api/v1/auth/login', $this->credentials())->assertOk();
        $this->withHeaders($spa)->getJson('/api/v1/me')->assertOk();

        $this->withHeaders($spa)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertGuest('web');
    }

    public function test_non_browser_client_must_send_device_name(): void
    {
        $this->postJson('/api/v1/auth/login', $this->credentials())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('device_name');
    }

    public function test_wrong_password_and_unknown_organization_fail_the_same_way(): void
    {
        $this->postJson('/api/v1/auth/login', $this->credentials(['password' => 'nope']))
            ->assertJsonValidationErrors(['phone' => __('auth.failed')]);

        $this->postJson('/api/v1/auth/login', $this->credentials(['organization' => 'other']))
            ->assertJsonValidationErrors(['phone' => __('auth.failed')]);
    }

    public function test_same_phone_in_another_organization_is_a_different_account(): void
    {
        $other = Organization::factory()->create(['slug' => 'other-gym']);
        User::factory()->owner()->create(['organization_id' => $other->id, 'phone' => '+251911234567', 'password' => 'different-pass']);

        $this->postJson('/api/v1/auth/login', $this->credentials(['organization' => 'other-gym', 'device_name' => 'x']))
            ->assertUnprocessable();

        $this->postJson('/api/v1/auth/login', $this->credentials(['organization' => 'other-gym', 'password' => 'different-pass', 'device_name' => 'x']))
            ->assertOk();
    }

    public function test_disabled_users_and_suspended_organizations_cannot_sign_in(): void
    {
        $this->owner->update(['status' => 'disabled']);
        $this->postJson('/api/v1/auth/login', $this->credentials(['device_name' => 'x']))
            ->assertJsonValidationErrors(['phone' => __('auth.account_disabled')]);

        $this->owner->update(['status' => 'active']);
        $this->organization->update(['status' => 'suspended']);
        $this->postJson('/api/v1/auth/login', $this->credentials(['device_name' => 'x']))
            ->assertJsonValidationErrors(['phone' => __('auth.organization_suspended')]);
    }

    public function test_existing_tokens_stop_working_when_user_is_disabled(): void
    {
        $token = $this->owner->createToken('x')->plainTextToken;
        $this->owner->update(['status' => 'disabled']);

        $this->withToken($token)->getJson('/api/v1/me')->assertForbidden();
    }

    public function test_login_is_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/login', $this->credentials(['password' => 'wrong']));
        }

        $this->postJson('/api/v1/auth/login', $this->credentials(['device_name' => 'x']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.phone.0', fn (string $message) => str_contains($message, 'Too many'));
    }

    public function test_token_logout_revokes_only_that_token(): void
    {
        $first = $this->owner->createToken('phone')->plainTextToken;
        $second = $this->owner->createToken('tablet')->plainTextToken;

        $this->withToken($first)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertSame(['tablet'], $this->owner->tokens()->pluck('name')->all());
        $this->app['auth']->forgetGuards();
        $this->withToken($second)->getJson('/api/v1/me')->assertOk();
    }
}
