<?php

use App\Services\Telegram\TelegramClient;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('telegram:set-webhook {url? : Public base URL of the API, defaults to APP_URL}', function (TelegramClient $telegram) {
    $secret = config('gym.notifications.telegram.webhook_secret');

    if (! $telegram->isConfigured() || blank($secret)) {
        $this->error('Set TELEGRAM_ENABLED, TELEGRAM_BOT_TOKEN, TELEGRAM_BOT_USERNAME and TELEGRAM_WEBHOOK_SECRET first.');

        return 1;
    }

    $url = rtrim($this->argument('url') ?? config('app.url'), '/').'/api/v1/webhooks/telegram';
    $telegram->setWebhook($url, $secret);
    $this->info("Telegram webhook set to {$url}");
})->purpose('Register the Telegram bot webhook');
