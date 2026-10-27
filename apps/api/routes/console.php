<?php

use App\Models\Membership;
use App\Services\Telegram\TelegramClient;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

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

Artisan::command('memberships:expire', function () {
    // Runs outside a tenant, so it covers every gym; all share the platform timezone today.
    $today = Carbon::now(config('gym.timezone'))->toDateString();

    $expired = Membership::withoutGlobalScopes()
        ->where('status', 'active')
        ->where(fn ($q) => $q->whereDate('ends_on', '<', $today)->orWhere('visits_remaining', 0))
        ->update(['status' => 'expired', 'updated_at' => now()]);

    $this->info("Expired {$expired} memberships.");
})->purpose('Mark memberships past their end date or out of visits as expired');

Schedule::command('memberships:expire')->dailyAt('00:05')->timezone(config('gym.timezone'));
