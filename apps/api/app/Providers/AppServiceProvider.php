<?php

namespace App\Providers;

use App\Support\Tenancy\Tenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Tenant::class);
        // Fortify only supplies the two-step login pieces; our controllers own the routes.
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        // In production, new passwords are also checked against known data breaches
        // (Have I Been Pwned; only a hash prefix leaves the server).
        Password::defaults(fn () => app()->isProduction() ? Password::min(8)->uncompromised() : Password::min(8));

        // Two-step codes: few tries per pending login, so six digits can't be guessed.
        RateLimiter::for('two-factor', fn (Request $request) => [
            Limit::perMinutes(5, 5)->by('2fa:'.($request->hasSession() ? $request->session()->getId() : '').'|'.$request->input('challenge')),
            Limit::perHour(30)->by('2fa-ip:'.$request->ip()),
        ]);

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
