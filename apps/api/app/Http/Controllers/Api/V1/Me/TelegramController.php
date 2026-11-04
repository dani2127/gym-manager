<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Models\TelegramLinkToken;
use App\Services\Telegram\TelegramClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Linking works through a bot deep link: the app opens
 * t.me/{bot}?start={token}, the user taps Start, and the webhook matches the
 * token to store their chat id.
 */
class TelegramController extends Controller
{
    public const LINK_TTL_MINUTES = 15;

    public function link(Request $request, TelegramClient $telegram): JsonResponse
    {
        if (! $telegram->isConfigured()) {
            throw ValidationException::withMessages(['telegram' => __('auth.telegram_not_configured')]);
        }

        $user = $request->user();
        TelegramLinkToken::where('user_id', $user->id)->whereNull('used_at')->delete();

        // Telegram start parameters allow up to 64 chars of [A-Za-z0-9_-].
        $token = Str::random(48);
        $link = TelegramLinkToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(self::LINK_TTL_MINUTES),
        ]);

        return response()->json([
            'url' => 'https://t.me/'.$telegram->botUsername().'?start='.$token,
            'expires_at' => $link->expires_at,
        ]);
    }

    public function unlink(Request $request): JsonResponse
    {
        $request->user()->notificationPreferences()->where('channel', 'telegram')->delete();

        return response()->json(['message' => __('auth.telegram_unlinked')]);
    }
}
