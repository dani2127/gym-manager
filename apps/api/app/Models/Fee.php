<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUlid;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'name', 'kind', 'amount_cents', 'is_active'])]
class Fee extends Model
{
    use BelongsToOrganization, HasPublicUlid, HasTranslations;

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['name' => 'array', 'amount_cents' => 'integer', 'is_active' => 'boolean'];
    }
}
