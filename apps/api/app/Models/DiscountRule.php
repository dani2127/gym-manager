<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUlid;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'organization_id', 'name', 'kind', 'value_type', 'value', 'min_group_size', 'requires_proof',
    'promo_code', 'plan_id', 'valid_from', 'valid_until', 'is_active',
])]
class DiscountRule extends Model
{
    use BelongsToOrganization, HasPublicUlid, HasTranslations;

    /** Kinds that follow from the member's group, so the desk never picks them by hand. */
    public const GROUP_KINDS = ['couple', 'family', 'corporate'];

    protected $attributes = ['is_active' => true, 'requires_proof' => false];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'value' => 'integer',
            'min_group_size' => 'integer',
            'requires_proof' => 'boolean',
            'is_active' => 'boolean',
            'valid_from' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
        ];
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class)->withTrashed();
    }

    /** @param  Builder<DiscountRule>  $query */
    public function scopeUsableOn(Builder $query, Carbon $day): void
    {
        $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $day))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $day));
    }

    public function appliesToPlan(?Plan $plan): bool
    {
        return $plan !== null && ($this->plan_id === null || $this->plan_id === $plan->id);
    }

    /** The discount in santim on a line worth $gross santim. */
    public function amountOn(int $gross): int
    {
        if ($this->value_type === 'percent') {
            return intdiv($gross * min($this->value, 100) * 2 + 100, 200);
        }

        return min($this->value, $gross);
    }
}
