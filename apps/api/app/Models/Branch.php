<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'name', 'code', 'phone', 'address', 'city', 'capacity', 'status'])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use BelongsToOrganization, HasFactory, HasPublicUlid;

    protected $attributes = ['status' => 'active'];

    public const DEFAULT_OPENS_AT = '06:00';

    public const DEFAULT_CLOSES_AT = '22:00';

    protected static function booted(): void
    {
        // Every new branch starts with a full week of default hours.
        static::created(function (Branch $branch) {
            $branch->openingHours()->createMany(
                collect(range(0, 6))->map(fn (int $weekday) => [
                    'weekday' => $weekday,
                    'opens_at' => self::DEFAULT_OPENS_AT,
                    'closes_at' => self::DEFAULT_CLOSES_AT,
                ])->all()
            );
        });
    }

    protected function casts(): array
    {
        return ['capacity' => 'integer'];
    }

    /** @return BelongsToMany<User, $this, BranchUser> */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(BranchUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return HasMany<OpeningHour, $this> */
    public function openingHours(): HasMany
    {
        return $this->hasMany(OpeningHour::class)->orderBy('weekday');
    }

    /** @return HasMany<BranchClosure, $this> */
    public function closures(): HasMany
    {
        return $this->hasMany(BranchClosure::class)->orderBy('date');
    }
}
