<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUlid;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'organization_id', 'name', 'description', 'type', 'duration_days', 'visit_count', 'price_cents', 'access_scope',
    'freeze_allowed', 'max_freeze_days', 'max_freezes', 'class_credits', 'is_active', 'sort',
])]
class Plan extends Model
{
    use BelongsToOrganization, HasPublicUlid, HasTranslations, SoftDeletes;

    protected $attributes = ['access_scope' => 'home_branch', 'is_active' => true, 'freeze_allowed' => false, 'sort' => 0];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'price_cents' => 'integer',
            'duration_days' => 'integer',
            'visit_count' => 'integer',
            'class_credits' => 'integer',
            'freeze_allowed' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Branches a member may use when access_scope is "selected".
     *
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'plan_branch');
    }

    /** @param  Builder<Plan>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('price_cents');
    }

    public function isTimed(): bool
    {
        return in_array($this->type, ['time', 'time_visits'], true);
    }

    public function hasVisits(): bool
    {
        return in_array($this->type, ['visits', 'time_visits'], true);
    }

    /**
     * Copied onto each membership so later price or rule changes never alter
     * what a member already bought.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'duration_days' => $this->duration_days,
            'visit_count' => $this->visit_count,
            'price_cents' => $this->price_cents,
            'access_scope' => $this->access_scope,
            'branch_ids' => $this->access_scope === 'selected' ? $this->branches->pluck('id')->all() : [],
            'freeze_allowed' => $this->freeze_allowed,
            'max_freeze_days' => $this->max_freeze_days,
            'max_freezes' => $this->max_freezes,
            'class_credits' => $this->class_credits,
        ];
    }
}
