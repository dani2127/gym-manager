<?php

namespace App\Http\Resources;

use App\Models\DiscountRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DiscountRule */
class DiscountRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->translate('name'),
            'name_translations' => ['en' => $this->name['en'] ?? '', 'am' => $this->name['am'] ?? null],
            'kind' => $this->kind,
            'value_type' => $this->value_type,
            'value' => $this->value,
            'min_group_size' => $this->min_group_size,
            'requires_proof' => $this->requires_proof,
            'promo_code' => $this->promo_code,
            'plan' => $this->plan_id ? ['id' => $this->plan?->ulid, 'name' => $this->plan?->translate('name')] : null,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'is_active' => $this->is_active,
        ];
    }
}
