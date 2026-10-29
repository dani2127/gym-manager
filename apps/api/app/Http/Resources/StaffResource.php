<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class StaffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'account_type' => $this->account_type,
            'status' => $this->status,
            'last_login_at' => $this->last_login_at,
            'assignments' => $this->whenLoaded('branches', fn () => $this->branches->map(fn ($branch) => [
                'branch' => ['id' => $branch->ulid, 'name' => $branch->name],
                'role' => $branch->pivot->role,
            ])),
        ];
    }
}
