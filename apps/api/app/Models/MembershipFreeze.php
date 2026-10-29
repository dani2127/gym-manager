<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id', 'membership_id', 'branch_id', 'starts_on', 'ends_on', 'days', 'reason', 'status', 'over_limit',
    'requested_by', 'reviewed_by', 'reviewed_at', 'rejection_reason',
])]
class MembershipFreeze extends Model
{
    use BelongsToOrganization, HasPublicUlid;

    /** Freezes that use up the plan's allowance. */
    public const COUNTED = ['approved', 'active', 'completed'];

    /** Freezes still in play: one at a time per membership. */
    public const OPEN = ['requested', 'approved', 'active'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'days' => 'integer',
            'over_limit' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Membership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return array<string, mixed> */
    public function present(): array
    {
        return [
            'id' => $this->ulid,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'back_on' => $this->ends_on->copy()->addDay()->toDateString(),
            'days' => $this->days,
            'reason' => $this->reason,
            'status' => $this->status,
            'over_limit' => $this->over_limit,
            'from_member' => $this->relationLoaded('requester') && $this->requester?->account_type?->value === 'member',
            'requested_by' => $this->relationLoaded('requester') ? $this->requester?->name : null,
            'reviewed_by' => $this->relationLoaded('reviewer') ? $this->reviewer?->name : null,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at,
        ];
    }
}
