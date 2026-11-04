<?php

namespace App\Http\Resources;

use App\Models\Fee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Fee */
class FeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->translate('name'),
            'name_translations' => ['en' => $this->name['en'] ?? '', 'am' => $this->name['am'] ?? null],
            'kind' => $this->kind,
            'amount_cents' => $this->amount_cents,
            'is_active' => $this->is_active,
        ];
    }
}
