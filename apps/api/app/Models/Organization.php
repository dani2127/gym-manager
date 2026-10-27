<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'status', 'timezone', 'default_locale', 'currency', 'tin_number', 'plan_tier'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasPublicUlid;

    protected $attributes = [
        'status' => 'active',
        'timezone' => 'Africa/Addis_Ababa',
        'default_locale' => 'en',
        'currency' => 'ETB',
        'plan_tier' => 'standard',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<Branch, $this> */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /** Reserves the next member number; call inside a transaction. */
    public function takeMemberNumber(): int
    {
        $locked = static::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();
        $number = $locked->next_member_no;
        $locked->increment('next_member_no');

        return $number;
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
