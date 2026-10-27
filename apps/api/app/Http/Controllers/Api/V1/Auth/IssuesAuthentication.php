<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Browsers on a stateful (Sanctum SPA) domain get a session cookie; other
 * clients must send device_name and get a bearer token.
 */
trait IssuesAuthentication
{
    protected function issueAuthentication(Request $request, User $user, int $status = 200): JsonResponse
    {
        $user->forceFill(['last_login_at' => now()])->save();

        if ($request->filled('device_name')) {
            return response()->json([
                'token' => $user->createToken($request->input('device_name'))->plainTextToken,
                'token_type' => 'Bearer',
            ], $status);
        }

        if (! $request->hasSession()) {
            throw ValidationException::withMessages(['device_name' => __('auth.device_name_required')]);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['message' => __('auth.logged_in')], $status);
    }
}
