<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Auth\TwoFactor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class LoginController extends Controller
{
    use IssuesAuthentication;

    public function store(LoginRequest $request, TwoFactor $twoFactor): JsonResponse
    {
        $user = $request->authenticate();

        // Right password, but two-step login is on: no session or token until the code is checked.
        if ($user->hasEnabledTwoFactorAuthentication()) {
            return response()->json([
                'two_factor' => true,
                'challenge' => $twoFactor->challengeFor($user, $request->input('device_name')),
            ]);
        }

        Audit::record('auth.login', $user, ['client' => $request->filled('device_name') ? 'token' : 'session'], $user);

        return $this->issueAuthentication($request, $user);
    }

    public function destroy(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        } else {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => __('auth.logged_out')]);
    }
}
