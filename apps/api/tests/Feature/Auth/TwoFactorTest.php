<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountType;
use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use App\Support\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $owner;

    private const SPA = ['Referer' => 'http://localhost:3000', 'Origin' => 'http://localhost:3000'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create(['slug' => 'lion-fitness', 'name' => 'Lion Fitness']);
        $this->owner = User::factory()->owner()->create(['organization_id' => $this->organization->id, 'phone' => '+251911234567']);
    }

    private function credentials(array $overrides = []): array
    {
        return ['organization' => 'lion-fitness', 'phone' => '0911234567', 'password' => 'password', ...$overrides];
    }

    /** The current code the authenticator app would show. */
    private function codeFor(User $user, int $offsetSeconds = 0): string
    {
        $engine = new Google2FA;
        $secret = Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_secret);

        return $engine->oathTotp($secret, $engine->getTimestamp() + intdiv($offsetSeconds, 30));
    }

    /** Turns two-step login on through the API and returns the recovery codes. */
    private function enable(User $user): array
    {
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/me/two-factor', ['current_password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['qr_svg', 'secret']]);

        return $this->postJson('/api/v1/me/two-factor/confirm', ['code' => $this->codeFor($user)])
            ->assertOk()
            ->json('data.recovery_codes');
    }

    private function signOut(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_setting_up_needs_the_password_and_a_working_code(): void
    {
        Sanctum::actingAs($this->owner);
        $this->postJson('/api/v1/me/two-factor', ['current_password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->postJson('/api/v1/me/two-factor', ['current_password' => 'password'])->assertOk();
        $this->getJson('/api/v1/me/two-factor')->assertJsonPath('data.pending', true)->assertJsonPath('data.enabled', false);

        // Not on until a code from the app proves the scan worked.
        $this->postJson('/api/v1/me/two-factor/confirm', ['code' => '000000'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $codes = $this->postJson('/api/v1/me/two-factor/confirm', ['code' => $this->codeFor($this->owner)])->assertOk()->json('data.recovery_codes');

        $this->assertCount(8, $codes);
        $this->getJson('/api/v1/me/two-factor')->assertJsonPath('data.enabled', true)->assertJsonPath('data.recovery_codes_left', 8);
        $this->getJson('/api/v1/me')->assertJsonPath('security.two_factor_enabled', true);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.two_factor_enabled']);
    }

    public function test_login_waits_for_the_code_and_a_challenge_works_once(): void
    {
        $this->enable($this->owner);
        $this->signOut();

        $challenge = $this->withHeaders(self::SPA)->postJson('/api/v1/auth/login', $this->credentials())
            ->assertOk()->assertJsonPath('two_factor', true)->json('challenge');
        // The password alone does not sign in.
        $this->withHeaders(self::SPA)->getJson('/api/v1/me')->assertUnauthorized();

        $this->withHeaders(self::SPA)->postJson('/api/v1/auth/two-factor-challenge', ['challenge' => $challenge, 'code' => '123456'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');

        // The next code from the app (the one used to set up can't be used twice).
        $this->withHeaders(self::SPA)->postJson('/api/v1/auth/two-factor-challenge', ['challenge' => $challenge, 'code' => $this->codeFor($this->owner, 30)])->assertOk();
        $this->withHeaders(self::SPA)->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.id', $this->owner->ulid);

        // The same challenge can't be used again.
        $this->withHeaders(self::SPA)->postJson('/api/v1/auth/two-factor-challenge', ['challenge' => $challenge, 'code' => $this->codeFor($this->owner, 30)])
            ->assertUnprocessable()->assertJsonValidationErrors('challenge');
    }

    public function test_challenge_expires_and_cannot_be_forged(): void
    {
        $this->enable($this->owner);
        $this->signOut();

        $challenge = $this->postJson('/api/v1/auth/login', $this->credentials(['device_name' => 'android']))->json('challenge');
        $this->postJson('/api/v1/auth/two-factor-challenge', ['challenge' => 'made-up', 'code' => '123456'])->assertJsonValidationErrors('challenge');

        $this->travel(6)->minutes();
        $this->postJson('/api/v1/auth/two-factor-challenge', ['challenge' => $challenge, 'code' => $this->codeFor($this->owner)])->assertJsonValidationErrors('challenge');
    }

    public function test_app_clients_get_a_token_after_the_code(): void
    {
        $this->enable($this->owner);
        $this->signOut();

        $challenge = $this->postJson('/api/v1/auth/login', $this->credentials(['device_name' => 'android']))->assertJsonMissingPath('token')->json('challenge');
        $token = $this->postJson('/api/v1/auth/two-factor-challenge', ['challenge' => $challenge, 'code' => $this->codeFor($this->owner, 30)])->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
    }

    public function test_a_recovery_code_works_once(): void
    {
        $codes = $this->enable($this->owner);
        $this->signOut();

        $first = $this->postJson('/api/v1/auth/login', $this->credentials(['device_name' => 'a']))->json('challenge');
        $this->postJson('/api/v1/auth/two-factor-challenge', ['challenge' => $first, 'recovery_code' => $codes[0]])->assertOk()->assertJsonStructure(['token']);

        $second = $this->postJson('/api/v1/auth/login', $this->credentials(['device_name' => 'b']))->json('challenge');
        $this->postJson('/api/v1/auth/two-factor-challenge', ['challenge' => $second, 'recovery_code' => $codes[0]])->assertJsonValidationErrors('code');
        $this->assertCount(8, $this->owner->fresh()->recoveryCodes());
    }

    public function test_gym_can_make_it_compulsory_for_owners_and_managers(): void
    {
        $bole = Branch::factory()->create(['organization_id' => $this->organization->id]);
        $reception = User::factory()->staffAt($bole, BranchRole::Receptionist)->create();
        $manager = User::factory()->staffAt($bole, BranchRole::Manager)->create();

        Sanctum::actingAs($this->owner);
        $this->patchJson('/api/v1/settings', ['settings' => ['security.require_two_factor' => true]])->assertOk();
        // Branches can't switch it off for themselves.
        $this->patchJson("/api/v1/branches/{$bole->ulid}/settings", ['settings' => ['security.require_two_factor' => false]]);
        $this->assertTrue((bool) app(SettingsRepository::class)->get('security.require_two_factor', $this->organization, $bole));

        // Owner without it: staff screens are closed, the account page is open.
        $this->getJson('/api/v1/members')->assertForbidden()->assertJsonPath('code', 'two_factor_required');
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('security.two_factor_required', true);

        Sanctum::actingAs($manager);
        $this->getJson('/api/v1/members')->assertForbidden();

        Sanctum::actingAs($reception);
        $this->getJson('/api/v1/members')->assertOk();

        $this->enable($this->owner);
        $this->getJson('/api/v1/members')->assertOk();
        $this->deleteJson('/api/v1/me/two-factor', ['current_password' => 'password'])->assertUnprocessable();
    }

    public function test_turning_it_off_needs_the_password(): void
    {
        $this->enable($this->owner);
        $this->deleteJson('/api/v1/me/two-factor', ['current_password' => 'wrong'])->assertUnprocessable();
        $this->deleteJson('/api/v1/me/two-factor', ['current_password' => 'password'])->assertOk();
        $this->assertFalse($this->owner->fresh()->hasEnabledTwoFactorAuthentication());
    }

    public function test_members_cannot_turn_it_on(): void
    {
        $member = User::factory()->create(['organization_id' => $this->organization->id, 'account_type' => AccountType::Member]);
        Sanctum::actingAs($member);
        $this->postJson('/api/v1/me/two-factor', ['current_password' => 'password'])->assertForbidden();
    }

    public function test_signed_in_devices_and_signing_out_the_others(): void
    {
        config(['session.driver' => 'database']);
        $this->owner->createToken('old phone');
        $current = $this->postJson('/api/v1/auth/login', $this->credentials(['device_name' => 'this laptop']))->json('token');
        DB::table('sessions')->insert(['id' => 'browser-elsewhere', 'user_id' => $this->owner->id, 'ip_address' => '10.0.0.9', 'user_agent' => 'Mozilla/5.0 (Linux; Android 14) Chrome/120.0 Mobile Safari/537.36', 'payload' => '', 'last_activity' => now()->getTimestamp()]);

        $list = $this->withToken($current)->getJson('/api/v1/me/sessions')->assertOk()->json('data');
        $this->assertCount(3, $list);
        $this->assertContains('Chrome · Android', array_column($list, 'device'));
        $this->assertSame('this laptop', collect($list)->firstWhere('current', true)['device']);

        $this->withToken($current)->postJson('/api/v1/me/sessions/sign-out-others', ['current_password' => 'wrong'])->assertUnprocessable();
        $this->withToken($current)->postJson('/api/v1/me/sessions/sign-out-others', ['current_password' => 'password'])->assertOk()->assertJsonPath('data.signed_out', 2);

        $this->assertSame(1, $this->owner->tokens()->count());
        $this->assertDatabaseMissing('sessions', ['id' => 'browser-elsewhere']);
    }

    public function test_changing_the_password_signs_out_other_devices(): void
    {
        $this->owner->createToken('old phone');
        $current = $this->postJson('/api/v1/auth/login', $this->credentials(['device_name' => 'this laptop']))->json('token');

        $this->withToken($current)->putJson('/api/v1/me/password', ['current_password' => 'password', 'password' => 'n3w-Secret!', 'password_confirmation' => 'n3w-Secret!'])->assertOk();

        $this->assertSame(1, $this->owner->tokens()->count());
    }

    public function test_app_tokens_expire(): void
    {
        $token = $this->postJson('/api/v1/auth/login', $this->credentials(['device_name' => 'android']))->json('token');
        $this->travel(31)->days();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }
}
