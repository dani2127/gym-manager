<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUlid;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'name', 'description', 'color', 'default_duration_min', 'default_capacity', 'is_active'])]
class ClassType extends Model
{
    use BelongsToOrganization, HasPublicUlid, HasTranslations;

    /** Colours the timetable can show, matching the design system. */
    public const COLORS = ['track', 'infield', 'amber', 'sky', 'plum', 'graphite'];

    protected $attributes = ['color' => 'track', 'default_duration_min' => 60, 'default_capacity' => 20, 'is_active' => true];

    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array', 'default_duration_min' => 'integer', 'default_capacity' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return array<string, mixed> */
    public function present(): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->translate('name'),
            'name_translations' => ['en' => $this->name['en'] ?? '', 'am' => $this->name['am'] ?? null],
            'description' => $this->description ? $this->translate('description') : null,
            'description_translations' => $this->description ? ['en' => $this->description['en'] ?? '', 'am' => $this->description['am'] ?? null] : null,
            'color' => $this->color,
            'default_duration_min' => $this->default_duration_min,
            'default_capacity' => $this->default_capacity,
            'is_active' => $this->is_active,
        ];
    }
}
