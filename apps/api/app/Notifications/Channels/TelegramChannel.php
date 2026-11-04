<?php

namespace App\Notifications\Channels;

use App\Services\Telegram\TelegramClient;
use Illuminate\Notifications\Notification;

/**
 * Delivers notifications that implement toTelegram() to the notifiable's
 * verified Telegram chat. Silently skips users without a linked chat.
 */
class TelegramChannel
{
    public function __construct(private TelegramClient $client) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $chatId = $notifiable->routeNotificationFor('telegram', $notification);

        if (! $chatId || ! $this->client->isConfigured() || ! method_exists($notification, 'toTelegram')) {
            return;
        }

        $this->client->sendMessage($chatId, $notification->toTelegram($notifiable));
    }
}
