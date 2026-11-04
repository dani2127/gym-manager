<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'user_id', 'bio', 'specialties', 'is_public'])]
class TrainerProfile extends Model
{
    use BelongsToOrganization, HasTranslations;

    protected $attributes = ['is_public' => true];

    protected function casts(): array
    {
        return ['bio' => 'array', 'specialties' => 'array', 'is_public' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, mixed> */
    public static function presentFor(User $trainer, ?self $profile): array
    {
        return [
            'id' => $trainer->ulid,
            'name' => $trainer->name,
            'bio' => $profile?->bio ? $profile->translate('bio') : null,
            'bio_translations' => $profile?->bio ? ['en' => $profile->bio['en'] ?? '', 'am' => $profile->bio['am'] ?? null] : null,
            'specialties' => $profile?->specialties ?? [],
            'is_public' => $profile?->is_public ?? true,
        ];
    }
}
