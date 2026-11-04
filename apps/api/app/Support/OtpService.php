<?php

namespace App\Support;

use App\Enums\OtpPurpose;
use App\Models\OtpCode;
use App\Models\User;
use App\Notifications\OneTimeCodeNotification;

/**
 * Six-digit one-time codes delivered over Telegram. Codes are stored as
 * HMACs, expire after a few minutes and lock after too many wrong guesses.
 */
class OtpService
{
    public const TTL_MINUTES = 5;

    public const MAX_ATTEMPTS = 5;

    /** Issues a fresh code (invalidating older ones) and sends it. Returns false if the user cannot receive it. */
    public function send(User $user, OtpPurpose $purpose): bool
    {
        if (! $user->telegramChatId()) {
            return false;
        }

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        OtpCode::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        OtpCode::create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'channel' => 'telegram',
            'code_hash' => $this->hash($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'created_at' => now(),
        ]);

        $user->notify(new OneTimeCodeNotification($code, $purpose, self::TTL_MINUTES));

        return true;
    }

    /**
     * Issues a code the desk hands over in person (no message is sent).
     * Older live codes for the same purpose stop working.
     */
    public function issueForDesk(User $user, OtpPurpose $purpose, int $digits, int $ttlMinutes): string
    {
        $code = str_pad((string) random_int(0, 10 ** $digits - 1), $digits, '0', STR_PAD_LEFT);

        OtpCode::where('user_id', $user->id)->where('purpose', $purpose)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        OtpCode::create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'channel' => 'desk',
            'code_hash' => $this->hash($code),
            'expires_at' => now()->addMinutes($ttlMinutes),
            'created_at' => now(),
        ]);

        return $code;
    }

    /** Consumes the code if it matches the latest live one. */
    public function verify(User $user, OtpPurpose $purpose, string $code): bool
    {
        $otp = OtpCode::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $otp || $otp->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! hash_equals($otp->code_hash, $this->hash($code))) {
            $otp->increment('attempts');

            return false;
        }

        $otp->update(['consumed_at' => now()]);

        return true;
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
