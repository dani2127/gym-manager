<?php

namespace App\Http\Resources;

use App\Models\Branch;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Plan */
class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->translate('name'),
            'name_translations' => ['en' => $this->name['en'] ?? '', 'am' => $this->name['am'] ?? null],
            'description' => $this->description ? $this->translate('description') : null,
            'description_translations' => $this->description ? ['en' => $this->description['en'] ?? '', 'am' => $this->description['am'] ?? null] : null,
            'type' => $this->type,
            'duration_days' => $this->duration_days,
            'visit_count' => $this->visit_count,
            'price_cents' => $this->price_cents,
            'access_scope' => $this->access_scope,
            'branches' => $this->whenLoaded('branches', fn () => $this->branches->map(fn (Branch $b) => ['id' => $b->ulid, 'name' => $b->name])),
            'freeze_allowed' => $this->freeze_allowed,
            'max_freeze_days' => $this->max_freeze_days,
            'max_freezes' => $this->max_freezes,
            'class_credits' => $this->class_credits,
            'is_active' => $this->is_active,
            'sort' => $this->sort,
        ];
    }
}
