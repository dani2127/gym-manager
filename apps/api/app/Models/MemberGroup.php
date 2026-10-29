<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['organization_id', 'type', 'name', 'primary_member_id'])]
class MemberGroup extends Model
{
    use BelongsToOrganization, HasPublicUlid;

    /** Size limits per group type; discounts in M3 rely on them. */
    public const SIZES = [
        'couple' => [2, 2],
        'family' => [2, 12],
        'corporate' => [2, 500],
    ];

    /** @return BelongsToMany<Member, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'member_group_members')->withTimestamps();
    }

    /** @return BelongsTo<Member, $this> */
    public function primaryMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'primary_member_id');
    }
}
