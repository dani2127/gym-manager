<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['branch_id', 'name'])]
#[Hidden(['token_hash', 'pairing_code_hash'])]
class KioskDevice extends Model
{
    use HasPublicUlid;

    /** No 0/O, 1/I/L: the code is read off a screen and typed on a tablet. */
    private const CODE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public const PAIRING_MINUTES = 15;

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime', 'pairing_expires_at' => 'datetime', 'paired_at' => 'datetime'];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Issues a new pairing code (and signs out the paired tablet). Returns the code once. */
    public function startPairing(): string
    {
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        $this->forceFill([
            'token_hash' => null,
            'paired_at' => null,
            'pairing_code_hash' => self::hash(self::normalizeCode($code)),
            'pairing_expires_at' => now()->addMinutes(self::PAIRING_MINUTES),
        ])->save();

        return substr($code, 0, 4).'-'.substr($code, 4);
    }

    /** Completes pairing and returns the device token, shown to the tablet once. */
    public function completePairing(): string
    {
        $token = 'kiosk_'.Str::random(48);

        $this->forceFill([
            'token_hash' => self::hash($token),
            'pairing_code_hash' => null,
            'pairing_expires_at' => null,
            'paired_at' => now(),
            'last_seen_at' => now(),
        ])->save();

        return $token;
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $code));
    }

    public static function hash(string $value): string
    {
        return hash('sha256', $value);
    }

    public function status(): string
    {
        if ($this->token_hash) {
            return 'paired';
        }

        return $this->pairing_expires_at?->isFuture() ? 'pairing' : 'unpaired';
    }
}
