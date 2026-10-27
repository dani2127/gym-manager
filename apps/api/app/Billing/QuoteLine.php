<?php

namespace App\Billing;

use App\Models\DiscountRule;
use Illuminate\Database\Eloquent\Model;

final readonly class QuoteLine
{
    /** @param  array{en: string, am?: string}  $description */
    public function __construct(
        public string $kind,
        public Model $itemable,
        public array $description,
        public int $qty,
        public int $unitCents,
        public int $discountCents,
        public ?DiscountRule $discountRule,
        public int $taxCents,
        public int $totalCents,
    ) {}

    public function grossCents(): int
    {
        return $this->unitCents * $this->qty;
    }
}
