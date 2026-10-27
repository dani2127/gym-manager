<?php

namespace Tests\Feature\Auth;

use App\Enums\OtpPurpose;
use App\Models\NotificationPreference;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\OneTimeCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $organization = Organization::factory()->create(['slug' => 'lion-fitness']);
        $this->user = User::factory()->owner()->create(['organization_id' => $organization->id, 'phone' => '+251911234567']);
    }

    private function linkTelegram(User $user): void
    {
        NotificationPreference::create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'channel' => 'telegram',
            'destination' => '777',
            'verified_at' => now(),
        ]);
    }

    /** Requests a code and returns it, captured from the faked notification. */
    private function requestCode(): string
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/password/forgot', ['organization' => 'lion-fitness', 'phone' => '0911234567'])
            ->assertStatus(202);

        $code = null;
        Notification::assertSentTo($this->user, OneTimeCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return $notification->purpose === OtpPurpose::PasswordReset;
        });

        return $code;
    }

    private function reset(string $code, string $password = 'new-password')
    {
        return $this->postJson('/api/v1/auth/password/reset', [
            'organization' => 'lion-fitness',
            'phone' => '0911234567',
            'code' => $code,
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    public function test_full_reset_flow_changes_password_and_signs_out_everywhere(): void
    {
        $this->linkTelegram($this->user);
        $this->user->createToken('old-phone');

        $this->reset($this->requestCode())->assertOk();

        $this->assertTrue(Hash::check('new-password', $this->user->fresh()->password));
        $this->assertSame(0, $this->user->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password_reset']);
    }

    public function test_code_is_delivered_over_telegram_in_users_language(): void
    {
        config([
            'gym.notifications.telegram.enabled' => true,
            'gym.notifications.telegram.bot_token' => '123:abc',
            'gym.notifications.telegram.bot_username' => 'demo_gym_bot',
        ]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $this->user->update(['locale' => 'am']);
        $this->linkTelegram($this->user);

        $this->postJson('/api/v1/auth/password/forgot', ['organization' => 'lion-fitness', 'phone' => '0911234567']);

        Http::assertSent(fn ($request) => $request['chat_id'] === '777'
            && preg_match('/^\d{6} /', $request['text'])
            && str_contains($request['text'], 'የይለፍ ቃል'));
    }

    public function test_unknown_phone_and_unlinked_user_get_the_same_answer_and_no_message(): void
    {
        Notification::fake();

        $unknown = $this->postJson('/api/v1/auth/password/forgot', ['organization' => 'lion-fitness', 'phone' => '0999999999']);
        $unlinked = $this->postJson('/api/v1/auth/password/forgot', ['organization' => 'lion-fitness', 'phone' => '0911234567']);

        $unknown->assertStatus(202);
        $this->assertSame($unknown->json(), $unlinked->assertStatus(202)->json());
        Notification::assertNothingSent();
    }

    public function test_wrong_codes_lock_after_five_attempts(): void
    {
        $this->linkTelegram($this->user);
        $code = $this->requestCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, 5) as $attempt) {
            $this->reset($wrong)->assertJsonValidationErrors('code');
        }

        // The right code no longer works once the attempts are used up.
        $this->reset($code)->assertJsonValidationErrors('code');
    }

    public function test_codes_expire_and_are_single_use(): void
    {
        $this->linkTelegram($this->user);

        $code = $this->requestCode();
        $this->travel(6)->minutes();
        $this->reset($code)->assertJsonValidationErrors('code');

        $this->travelBack();
        $code = $this->requestCode();
        $this->reset($code)->assertOk();
        $this->reset($code, 'another-pass')->assertJsonValidationErrors('code');
    }

    public function test_phone_verification_requires_linked_telegram(): void
    {
        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/me/phone/verification')->assertJsonValidationErrors('telegram');

        $this->linkTelegram($this->user);
        Notification::fake();
        $this->postJson('/api/v1/me/phone/verification')->assertStatus(202);

        $code = null;
        Notification::assertSentTo($this->user, OneTimeCodeNotification::class, function ($n) use (&$code) {
            $code = $n->code;

            return true;
        });

        $this->postJson('/api/v1/me/phone/verify', ['code' => $code])->assertOk();
        $this->assertNotNull($this->user->fresh()->phone_verified_at);
    }
}
