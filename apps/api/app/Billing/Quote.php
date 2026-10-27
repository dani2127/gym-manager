<?php

namespace App\Billing;

use App\Models\DiscountRule;
use App\Models\Plan;
use Illuminate\Support\Carbon;

/** A priced sale before anything is saved: what the desk preview shows. */
final readonly class Quote
{
    /** @param  list<QuoteLine>  $lines */
    public function __construct(
        public array $lines,
        public TaxConfig $tax,
        public ?Plan $plan,
        public ?DiscountRule $discount,
        public ?Carbon $startsOn,
        public ?Carbon $endsOn,
    ) {}

    public function subtotalCents(): int
    {
        return array_sum(array_map(fn (QuoteLine $l) => $l->grossCents(), $this->lines));
    }

    public function discountCents(): int
    {
        return array_sum(array_map(fn (QuoteLine $l) => $l->discountCents, $this->lines));
    }

    public function taxCents(): int
    {
        return array_sum(array_map(fn (QuoteLine $l) => $l->taxCents, $this->lines));
    }

    public function totalCents(): int
    {
        return array_sum(array_map(fn (QuoteLine $l) => $l->totalCents, $this->lines));
    }
}
