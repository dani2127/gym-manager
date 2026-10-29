<?php

namespace App\CheckIns;

use App\Models\CheckIn;
use App\Models\Member;

/** What the desk should show after a scan or a manual check-in. */
final readonly class CheckInOutcome
{
    private function __construct(
        public ?CheckIn $checkIn,
        public ?Member $member,
        /** allowed | denied | overridden | already_inside */
        public string $result,
        public ?string $reason,
    ) {}

    public static function recorded(CheckIn $checkIn): self
    {
        return new self($checkIn, $checkIn->member, $checkIn->result, $checkIn->denial_reason);
    }

    /** The member is already inside: show them again, record nothing. */
    public static function repeat(CheckIn $open): self
    {
        return new self($open, $open->member, 'already_inside', 'already_inside');
    }

    /** A code that doesn't open the door and isn't stored. */
    public static function rejected(string $reason, ?Member $member = null): self
    {
        return new self(null, $member, 'denied', $reason);
    }
}
