<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'invoice_id', 'itemable_type', 'itemable_id', 'description', 'qty', 'unit_price_cents', 'discount_cents',
    'discount_rule_id', 'tax_cents', 'total_cents',
])]
#[WithoutTimestamps]
class InvoiceItem extends Model
{
    use HasTranslations;

    protected function casts(): array
    {
        return [
            'description' => 'array',
            'qty' => 'integer',
            'unit_price_cents' => 'integer',
            'discount_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<DiscountRule, $this> */
    public function discountRule(): BelongsTo
    {
        return $this->belongsTo(DiscountRule::class);
    }
}
