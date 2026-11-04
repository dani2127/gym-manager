<?php

namespace App\Http\Middleware;

use App\Auth\TwoFactor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the gym makes two-step login compulsory, owners and managers without it
 * can only reach their own account (to set it up), not the staff screens.
 */
class EnsureTwoFactorWhenRequired
{
    public function __construct(private TwoFactor $twoFactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->hasEnabledTwoFactorAuthentication() && $this->twoFactor->requiredFor($user)) {
            return response()->json(['message' => __('auth.two_factor_setup_required'), 'code' => 'two_factor_required'], 403);
        }

        return $next($request);
    }
}
