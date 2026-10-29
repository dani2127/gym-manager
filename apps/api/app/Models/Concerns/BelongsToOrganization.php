<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Support\Tenancy\OrganizationScope;
use App\Support\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Restricts queries to the current tenant and stamps organization_id on create.
 * Outside a tenant context (console, webhooks, login) the scope is inactive,
 * so those code paths must filter by organization explicitly.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function ($model) {
            $model->organization_id ??= app(Tenant::class)->id();
        });
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
