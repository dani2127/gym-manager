<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/** Minimal Telegram Bot API client. */
class TelegramClient
{
    public function isConfigured(): bool
    {
        return (bool) config('gym.notifications.telegram.enabled')
            && filled(config('gym.notifications.telegram.bot_token'))
            && filled(config('gym.notifications.telegram.bot_username'));
    }

    public function botUsername(): ?string
    {
        return config('gym.notifications.telegram.bot_username');
    }

    /** @throws RequestException */
    public function sendMessage(string $chatId, string $text): void
    {
        $this->call('sendMessage', ['chat_id' => $chatId, 'text' => $text]);
    }

    /** @throws RequestException */
    public function setWebhook(string $url, string $secret): void
    {
        $this->call('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws RequestException
     */
    private function call(string $method, array $payload): void
    {
        $token = config('gym.notifications.telegram.bot_token');

        Http::timeout(10)
            ->post("https://api.telegram.org/bot{$token}/{$method}", $payload)
            ->throw();
    }
}
