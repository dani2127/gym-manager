<?php

namespace App\Support;

/**
 * Ethiopian mobile numbers (Ethio Telecom 9…, Safaricom 7…) normalized to
 * E.164: "0911 23 45 67", "911234567" and "+251911234567" all become
 * "+251911234567".
 */
final class Phone
{
    public static function normalize(?string $input): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $input);

        if (str_starts_with($digits, '251')) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^[79]\d{8}$/', $digits) ? '+251'.$digits : null;
    }
}
