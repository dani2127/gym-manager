<?php

namespace App\CheckIns;

use App\Models\Member;

/**
 * QR codes members show at the door. Both kinds are signed with the member's
 * secret, so a code cannot be made up or copied onto another member.
 *
 * - Phone: G1.{member}.{window}.{signature}. Changes every 30 seconds, so a
 *   screenshot stops working within about a minute. Works offline: the app
 *   computes it from the secret it holds.
 * - Card:  G2.{member}.{version}.{signature}. Printed once. Reissuing a card
 *   raises the version, so every earlier card stops working.
 *
 * Only upper-case letters, digits and dots, so the QR stays small (alphanumeric mode).
 */
class MemberCodes
{
    public const WINDOW_SECONDS = 30;

    /** Windows accepted around now: phones offline for a while drift a little. */
    private const PAST_WINDOWS = 2;

    private const FUTURE_WINDOWS = 1;

    public function phoneCode(Member $member, ?int $at = null): string
    {
        $window = intdiv($at ?? now()->getTimestamp(), self::WINDOW_SECONDS);

        return implode('.', ['G1', $member->ulid, $window, $this->sign($member, "phone|{$window}", 16)]);
    }

    public function cardCode(Member $member): string
    {
        return implode('.', ['G2', $member->ulid, $member->card_version, $this->sign($member, "card|{$member->card_version}", 20)]);
    }

    /** @return array{kind: 'phone'|'card', ulid: string, number: int, signature: string}|null */
    public function parse(string $code): ?array
    {
        if (! preg_match('/^G([12])\.([0-9A-HJKMNP-TV-Z]{26})\.(\d{1,12})\.([0-9A-F]{16,20})$/', strtoupper(trim($code)), $m)) {
            return null;
        }

        return ['kind' => $m[1] === '1' ? 'phone' : 'card', 'ulid' => $m[2], 'number' => (int) $m[3], 'signature' => $m[4]];
    }

    /**
     * Checks a parsed code against its member.
     *
     * @param  array{kind: 'phone'|'card', ulid: string, number: int, signature: string}  $parsed
     * @return 'valid'|'invalid'|'expired'|'card_replaced'
     */
    public function verify(Member $member, array $parsed, ?int $at = null): string
    {
        if ($parsed['kind'] === 'card') {
            if (! hash_equals($this->sign($member, "card|{$parsed['number']}", 20), $parsed['signature'])) {
                return 'invalid';
            }

            return $parsed['number'] === $member->card_version ? 'valid' : 'card_replaced';
        }

        if (! hash_equals($this->sign($member, "phone|{$parsed['number']}", 16), $parsed['signature'])) {
            return 'invalid';
        }

        $now = intdiv($at ?? now()->getTimestamp(), self::WINDOW_SECONDS);
        $offset = $parsed['number'] - $now;

        return $offset >= -self::PAST_WINDOWS && $offset <= self::FUTURE_WINDOWS ? 'valid' : 'expired';
    }

    private function sign(Member $member, string $payload, int $length): string
    {
        return strtoupper(substr(hash_hmac('sha256', "{$member->ulid}|{$payload}", $member->qr_secret), 0, $length));
    }
}
