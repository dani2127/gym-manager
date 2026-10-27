<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'organization_id', 'branch_id', 'member_id', 'plan_id', 'plan_snapshot', 'starts_on', 'ends_on', 'visits_total',
    'visits_remaining', 'status', 'invoice_id', 'sold_by',
])]
class Membership extends Model
{
    use BelongsToOrganization, HasPublicUlid;

    protected function casts(): array
    {
        return [
            'plan_snapshot' => 'array',
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'visits_total' => 'integer',
            'visits_remaining' => 'integer',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class)->withTrashed();
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Paid or awaiting payment, and not cancelled or expired.
     *
     * @param  Builder<Membership>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereIn('status', ['pending_payment', 'active', 'frozen']);
    }

    public function planName(?string $locale = null): string
    {
        $names = $this->plan_snapshot['name'] ?? [];
        $locale ??= app()->getLocale();

        return (string) (($names[$locale] ?? null) ?: ($names['en'] ?? ''));
    }

    /**
     * Where the membership stands on a given day, for display and check-in:
     * upcoming, current, expired, or the stored status when not active.
     */
    public function stateOn(Carbon $day): string
    {
        if ($this->status !== 'active') {
            return $this->status;
        }
        if ($this->starts_on->gt($day)) {
            return 'upcoming';
        }
        if (($this->ends_on && $this->ends_on->lt($day)) || $this->visits_remaining === 0) {
            return 'expired';
        }

        return 'current';
    }
}
