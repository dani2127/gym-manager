<?php

namespace Tests\Feature\Auth;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TelegramLinkTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'webhook-secret';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'gym.notifications.telegram.enabled' => true,
            'gym.notifications.telegram.bot_token' => '123:abc',
            'gym.notifications.telegram.bot_username' => 'demo_gym_bot',
            'gym.notifications.telegram.webhook_secret' => self::SECRET,
        ]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->user = User::factory()->owner()->create(['locale' => 'am']);
    }

    private function linkToken(): string
    {
        Sanctum::actingAs($this->user);
        $url = $this->postJson('/api/v1/me/telegram/link')->assertOk()->json('url');
        $this->assertStringStartsWith('https://t.me/demo_gym_bot?start=', $url);

        return substr($url, strlen('https://t.me/demo_gym_bot?start='));
    }

    private function sendStart(string $token, int $chatId = 555, string $secret = self::SECRET)
    {
        return $this->postJson('/api/v1/webhooks/telegram', [
            'update_id' => 1,
            'message' => ['chat' => ['id' => $chatId], 'text' => "/start {$token}"],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $secret]);
    }

    public function test_deep_link_flow_links_the_chat_and_replies_in_users_language(): void
    {
        $this->sendStart($this->linkToken())->assertOk();

        $this->assertSame('555', $this->user->telegramChatId());
        $this->assertTrue(NotificationPreference::where('user_id', $this->user->id)->value('is_preferred'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && $request['chat_id'] === '555'
            && str_contains($request['text'], 'ተገናኝቷል'));

        $this->getJson('/api/v1/me')->assertJsonPath('user.telegram_linked', true);
    }

    public function test_tokens_are_single_use(): void
    {
        $token = $this->linkToken();
        $this->sendStart($token, 111);
        $this->sendStart($token, 222);

        $this->assertSame('111', $this->user->telegramChatId());
    }

    public function test_expired_tokens_are_rejected(): void
    {
        $token = $this->linkToken();
        $this->travel(16)->minutes();

        $this->sendStart($token)->assertOk();
        $this->assertNull($this->user->telegramChatId());
    }

    public function test_webhook_requires_the_secret(): void
    {
        $this->sendStart($this->linkToken(), secret: 'wrong')->assertForbidden();
        $this->assertNull($this->user->telegramChatId());
    }

    public function test_link_fails_cleanly_when_bot_is_not_configured(): void
    {
        config(['gym.notifications.telegram.enabled' => false]);
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/me/telegram/link')->assertJsonValidationErrors('telegram');
    }

    public function test_unlink(): void
    {
        $this->sendStart($this->linkToken());
        $this->deleteJson('/api/v1/me/telegram')->assertOk();

        $this->assertNull($this->user->telegramChatId());
    }
}
