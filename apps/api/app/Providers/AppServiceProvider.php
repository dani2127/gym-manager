<?php

namespace App\Providers;

use App\Support\Tenancy\Tenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Tenant::class);
    }

    public function boot(): void
    {
        // Sending a code costs a Telegram message, so it is tightly limited.
        RateLimiter::for('otp', fn (Request $request) => [
            Limit::perMinutes(10, 3)->by('otp-send:'.$this->otpSubject($request)),
            Limit::perHour(20)->by('otp-send-ip:'.$request->ip()),
        ]);

        // Checking codes: each code also locks itself after 5 wrong guesses.
        RateLimiter::for('otp-verify', fn (Request $request) => [
            Limit::perMinutes(10, 10)->by('otp-verify:'.$this->otpSubject($request)),
            Limit::perHour(30)->by('otp-verify-ip:'.$request->ip()),
        ]);

        // A busy door at opening time, but not a guessing machine for codes.
        RateLimiter::for('check-in', fn (Request $request) => Limit::perMinute(120)->by('check-in:'.($request->user()?->id ?? $request->attributes->get('kiosk')?->id ?? $request->ip())));

        // Pairing codes are short, so guessing is capped hard per address.
        RateLimiter::for('kiosk-pair', fn (Request $request) => Limit::perMinutes(10, 10)->by('kiosk-pair:'.$request->ip()));

        RateLimiter::for('registration', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
    }

    private function otpSubject(Request $request): string
    {
        return $request->user()?->id ?? mb_strtolower((string) $request->input('organization')).'|'.$request->input('phone');
    }
}
