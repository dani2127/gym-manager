<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\KioskDevice;
use App\Support\Tenancy\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs in an entrance kiosk from its device token (X-Kiosk-Token) and scopes
 * the request to its gym, like ResolveTenant does for staff.
 */
class AuthenticateKiosk
{
    public function __construct(private Tenant $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->header('X-Kiosk-Token');
        $kiosk = $token !== '' ? KioskDevice::where('token_hash', KioskDevice::hash($token))->first() : null;
        abort_unless($kiosk, 401, 'Kiosk not paired.');

        $branch = Branch::withoutGlobalScopes()->with('organization')->find($kiosk->branch_id);
        abort_unless($branch && $branch->status === 'active' && $branch->organization->isActive(), 403);

        $kiosk->setRelation('branch', $branch);
        if (! $kiosk->last_seen_at || $kiosk->last_seen_at->lt(now()->subMinute())) {
            $kiosk->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        $request->attributes->set('kiosk', $kiosk);
        $this->tenant->set($branch->organization);

        try {
            return $next($request);
        } finally {
            $this->tenant->set(null);
        }
    }
}
