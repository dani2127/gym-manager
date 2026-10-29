<?php

use App\CheckIns\BranchHours;
use App\Memberships\Freezes;
use App\Models\CheckIn;
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

Artisan::command('memberships:freezes', function (Freezes $freezes) {
    // Before memberships:expire, so a freeze ending today never lets the pass lapse.
    $changed = $freezes->activateDue(Carbon::parse(Carbon::now(config('gym.timezone'))->toDateString()));

    $this->info("Started or finished {$changed} freezes.");
})->purpose('Start freezes whose first day has come and end those whose last day has passed');

Schedule::command('memberships:freezes')->dailyAt('00:01')->timezone(config('gym.timezone'));

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

Artisan::command('check-ins:close-day', function (BranchHours $hours) {
    // People who never checked out are signed out at closing time of their day,
    // so "inside right now" starts empty each morning and visit lengths stay sensible.
    $open = CheckIn::withoutGlobalScopes()
        ->with('branch.organization')
        ->whereIn('result', ['allowed', 'overridden'])
        ->whereNull('checked_out_at')
        ->where('checked_in_at', '<', Carbon::now(config('gym.timezone'))->startOfDay()->utc())
        ->get();

    foreach ($open as $checkIn) {
        $tz = $checkIn->branch->organization->timezone ?: config('gym.timezone');
        $localIn = $checkIn->checked_in_at->copy()->setTimezone($tz);
        $closing = $hours->closingTime($checkIn->branch, $localIn);
        $out = $closing ? Carbon::parse($localIn->toDateString().' '.$closing, $tz) : $localIn->copy()->addHours(2);
        // Stored in the app timezone (UTC), like every other timestamp.
        $checkIn->forceFill(['checked_out_at' => ($out->lt($localIn) ? $localIn : $out)->copy()->setTimezone(config('app.timezone'))])->save();
    }

    $this->info("Closed {$open->count()} open check-ins.");
})->purpose('Check out everyone still marked inside from earlier days');

Schedule::command('check-ins:close-day')->dailyAt('00:10')->timezone(config('gym.timezone'));
