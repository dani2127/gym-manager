<?php

namespace App\Billing;

/** Integer santim arithmetic; money never touches floats. */
final class Money
{
    /** Divides non-negative integers, rounding half up. */
    public static function divideRounded(int $numerator, int $denominator): int
    {
        return intdiv(2 * $numerator + $denominator, 2 * $denominator);
    }

    /** "1,150.00" for receipts; the web app formats its own copy. */
    public static function format(int $cents): string
    {
        return number_format($cents / 100, 2);
    }
}
