<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\Tenancy\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Scopes the request to the authenticated user's organization. Must run
 * before route-model binding so bound models are tenant-filtered.
 */
class ResolveTenant
{
    public function __construct(private Tenant $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $organization = $user?->organization_id ? Organization::find($user->organization_id) : null;

        abort_unless($organization && $user->isActive(), 403, __('auth.account_disabled'));
        abort_unless($organization->isActive(), 403, __('auth.organization_suspended'));

        $this->tenant->set($organization);

        try {
            return $next($request);
        } finally {
            $this->tenant->set(null);
        }
    }
}
