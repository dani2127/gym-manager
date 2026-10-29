<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id', 'branch_id', 'member_id', 'membership_id', 'method', 'result', 'denial_reason',
    'override_reason', 'visit_used', 'locker_id', 'checked_in_at', 'checked_out_at', 'handled_by',
])]
class CheckIn extends Model
{
    use BelongsToOrganization, HasPublicUlid;

    protected function casts(): array
    {
        return ['visit_used' => 'boolean', 'checked_in_at' => 'datetime', 'checked_out_at' => 'datetime'];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
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
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** @return BelongsTo<Locker, $this> */
    public function locker(): BelongsTo
    {
        return $this->belongsTo(Locker::class);
    }

    /**
     * People let in and not yet checked out: "inside right now".
     *
     * @param  Builder<CheckIn>  $query
     */
    public function scopeInside(Builder $query): void
    {
        $query->whereIn('result', ['allowed', 'overridden'])->whereNull('checked_out_at');
    }

    public function isEntry(): bool
    {
        return in_array($this->result, ['allowed', 'overridden'], true);
    }
}
