<?php

namespace App\Http\Resources;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Branch */
class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'code' => $this->code,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'capacity' => $this->capacity,
            'status' => $this->status,
            // Present when listed for a user: their role here (null for owners).
            'role' => $this->whenPivotLoaded('branch_user', fn () => $this->pivot->role),
            'opening_hours' => $this->whenLoaded('openingHours', fn () => $this->openingHours->map(fn ($hour) => [
                'weekday' => $hour->weekday,
                'opens_at' => $hour->opens_at ? substr($hour->opens_at, 0, 5) : null,
                'closes_at' => $hour->closes_at ? substr($hour->closes_at, 0, 5) : null,
                'is_closed' => $hour->is_closed,
            ])),
        ];
    }
}
