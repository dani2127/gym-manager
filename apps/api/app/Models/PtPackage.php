<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUlid;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "10 sessions, valid 60 days". Sold at the desk like a plan. */
#[Fillable(['organization_id', 'name', 'sessions_count', 'price_cents', 'validity_days', 'trainer_id', 'is_active', 'sort'])]
class PtPackage extends Model
{
    use BelongsToOrganization, HasPublicUlid, HasTranslations;

    protected $attributes = ['is_active' => true, 'sort' => 0];

    protected function casts(): array
    {
        return ['name' => 'array', 'sessions_count' => 'integer', 'price_cents' => 'integer', 'validity_days' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return ['name' => $this->name, 'sessions_count' => $this->sessions_count, 'validity_days' => $this->validity_days, 'price_cents' => $this->price_cents];
    }

    /** @return array<string, mixed> */
    public function present(): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->translate('name'),
            'name_translations' => ['en' => $this->name['en'] ?? '', 'am' => $this->name['am'] ?? null],
            'sessions_count' => $this->sessions_count,
            'price_cents' => $this->price_cents,
            'validity_days' => $this->validity_days,
            'trainer' => $this->trainer ? ['id' => $this->trainer->ulid, 'name' => $this->trainer->name] : null,
            'is_active' => $this->is_active,
        ];
    }
}
