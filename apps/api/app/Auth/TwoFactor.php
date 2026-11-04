<?php

namespace App\Auth;

use App\Models\User;
use App\Support\Settings\SettingsRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/**
 * Two-step login on top of Laravel Fortify. After the password is right, the
 * client gets an encrypted, single-use challenge (valid 5 minutes) and must
 * send it back with a code from the authenticator app or a recovery code.
 */
class TwoFactor
{
    private const CHALLENGE_MINUTES = 5;

    public function __construct(private TwoFactorAuthenticationProvider $provider, private SettingsRepository $settings) {}

    /** Whether the gym makes it compulsory for this person (owners and managers). */
    public function requiredFor(User $user): bool
    {
        if (! $user->isOwnerOrManager()) {
            return false;
        }
        $organization = $user->organization()->withoutGlobalScopes()->first();

        return $organization && (bool) $this->settings->get('security.require_two_factor', $organization);
    }

    public function challengeFor(User $user, ?string $deviceName): string
    {
        return Crypt::encryptString(json_encode([
            'user' => $user->id,
            'device' => $deviceName,
            'expires' => now()->addMinutes(self::CHALLENGE_MINUTES)->getTimestamp(),
            'nonce' => Str::random(24),
        ]));
    }

    /**
     * Checks the challenge and the code; returns the user and the device name
     * the login started with. A challenge works once.
     *
     * @return array{0: User, 1: ?string}
     */
    public function complete(string $challenge, ?string $code, ?string $recoveryCode): array
    {
        $expired = fn () => throw ValidationException::withMessages(['challenge' => __('auth.two_factor_expired')]);

        try {
            $payload = json_decode(Crypt::decryptString($challenge), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $expired();
        }
        if (($payload['expires'] ?? 0) < now()->getTimestamp() || Cache::has('2fa-used:'.$payload['nonce'])) {
            $expired();
        }

        $user = User::withoutGlobalScopes()->find($payload['user']);
        if (! $user || ! $user->isActive() || ! $user->hasEnabledTwoFactorAuthentication()) {
            $expired();
        }

        if (! $this->verify($user, $code, $recoveryCode)) {
            throw ValidationException::withMessages(['code' => __('auth.two_factor_invalid')]);
        }

        Cache::put('2fa-used:'.$payload['nonce'], true, now()->addMinutes(self::CHALLENGE_MINUTES + 1));

        return [$user, $payload['device'] ?? null];
    }

    private function verify(User $user, ?string $code, ?string $recoveryCode): bool
    {
        if (filled($code)) {
            $code = preg_replace('/\s+/', '', $code);

            return $this->provider->verify(Fortify::currentEncrypter()->decrypt($user->two_factor_secret), $code);
        }

        if (filled($recoveryCode)) {
            $recoveryCode = trim($recoveryCode);
            $match = collect($user->recoveryCodes())->first(fn (string $stored) => hash_equals($stored, $recoveryCode));
            if ($match) {
                // Each recovery code works once.
                $user->replaceRecoveryCode($match);

                return true;
            }
        }

        return false;
    }
}
