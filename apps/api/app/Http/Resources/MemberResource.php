<?php

namespace App\Http\Resources;

use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Member */
class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $group = $this->relationLoaded('groups') ? $this->group() : null;

        return [
            'id' => $this->ulid,
            'member_no' => $this->code,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'phone' => $this->phone,
            'email' => $this->email,
            'status' => $this->status,
            'joined_at' => $this->joined_at?->toDateString(),
            'home_branch' => $this->whenLoaded('homeBranch', fn () => ['id' => $this->homeBranch->ulid, 'name' => $this->homeBranch->name]),
            // Versioned so a new upload is never served from cache.
            'photo_url' => $this->photo_path ? "/members/{$this->ulid}/photo?v=".substr(md5($this->photo_path), 0, 8) : null,
            'emergency_name' => $this->emergency_name,
            'emergency_phone' => $this->emergency_phone,
            'notes' => $this->notes,
            'group' => $this->when($this->relationLoaded('groups'), fn () => $group ? [
                'id' => $group->ulid,
                'type' => $group->type,
                'name' => $group->name,
                'primary_member_id' => $group->primaryMember?->ulid,
                'members' => $group->members->map(fn (Member $m) => ['id' => $m->ulid, 'full_name' => $m->full_name, 'member_no' => $m->code]),
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
