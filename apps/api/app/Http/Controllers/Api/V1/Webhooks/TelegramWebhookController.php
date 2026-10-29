<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Models\TelegramLinkToken;
use App\Services\Telegram\TelegramClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Receives bot updates. Telegram signs nothing, so the shared secret set via
 * setWebhook (sent back as X-Telegram-Bot-Api-Secret-Token) is the only proof
 * of origin. Always answers 200 for authentic calls so Telegram does not retry.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramClient $telegram): JsonResponse
    {
        $secret = (string) config('gym.notifications.telegram.webhook_secret');

        abort_if($secret === '' || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token')), 403);

        $chatId = $request->input('message.chat.id');
        $text = trim((string) $request->input('message.text'));

        if ($chatId && preg_match('/^\/start\s+([A-Za-z0-9_-]{16,64})$/', $text, $matches)) {
            $linked = $this->link((string) $chatId, $matches[1]);
            $this->reply($telegram, (string) $chatId, $linked ? 'telegram.linked' : 'telegram.link_invalid', $linked?->user->locale);
        }

        return response()->json(['ok' => true]);
    }

    private function link(string $chatId, string $token): ?TelegramLinkToken
    {
        return DB::transaction(function () use ($chatId, $token) {
            $link = TelegramLinkToken::with('user')
                ->where('token_hash', hash('sha256', $token))
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (! $link) {
                return null;
            }

            $user = $link->user;
            // Telegram becomes the preferred channel unless the user already chose another one.
            $preferredChannel = $user->notificationPreferences()->where('is_preferred', true)->value('channel');

            NotificationPreference::updateOrCreate(
                ['user_id' => $user->id, 'channel' => 'telegram'],
                [
                    'organization_id' => $user->organization_id,
                    'destination' => $chatId,
                    'verified_at' => now(),
                    'opted_out_at' => null,
                    'is_preferred' => $preferredChannel === null || $preferredChannel === 'telegram',
                ],
            );

            $link->update(['used_at' => now()]);

            return $link;
        });
    }

    private function reply(TelegramClient $telegram, string $chatId, string $key, ?string $locale): void
    {
        try {
            $telegram->sendMessage($chatId, __($key, ['app' => config('app.name')], $locale));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
