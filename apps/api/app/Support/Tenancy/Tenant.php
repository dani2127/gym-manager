<?php

namespace App\Support\Tenancy;

use App\Models\Organization;

/**
 * The organization the current request acts within. Set by the ResolveTenant
 * middleware from the authenticated user; read by OrganizationScope.
 */
class Tenant
{
    private ?Organization $organization = null;

    public function set(?Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function organization(): ?Organization
    {
        return $this->organization;
    }

    public function id(): ?int
    {
        return $this->organization?->id;
    }
}
