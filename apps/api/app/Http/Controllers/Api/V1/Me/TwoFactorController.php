<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Auth\TwoFactor;
use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Fortify;

/**
 * Turning two-step login on and off for staff. Fortify's actions do the work:
 * secret generation, code checks and recovery codes. Changes need the password.
 */
class TwoFactorController extends Controller
{
    public function show(Request $request, TwoFactor $twoFactor): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'enabled' => $user->hasEnabledTwoFactorAuthentication(),
            'pending' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null,
            'required' => $twoFactor->requiredFor($user),
            'recovery_codes_left' => $user->hasEnabledTwoFactorAuthentication() ? count($user->recoveryCodes()) : null,
        ]]);
    }

    /** Step 1: a new secret and the QR code to scan. Not active until confirmed. */
    public function store(Request $request, EnableTwoFactorAuthentication $enable): JsonResponse
    {
        $user = $request->user();
        abort_if($user->account_type === AccountType::Member, 403);
        $request->validate(['current_password' => ['required', 'current_password']]);
        if ($user->hasEnabledTwoFactorAuthentication()) {
            throw ValidationException::withMessages(['current_password' => __('auth.two_factor_already_on')]);
        }

        $enable($user, true);
        $user->refresh();

        return response()->json(['data' => [
            'qr_svg' => $user->twoFactorQrCodeSvg(),
            // For typing into the app when the camera can't scan.
            'secret' => Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
        ]]);
    }

    /** Step 2: the first code from the app proves it's set up; returns the recovery codes once. */
    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);
        $user = $request->user();

        try {
            $confirm($user, preg_replace('/\s+/', '', $request->input('code')));
        } catch (ValidationException) {
            throw ValidationException::withMessages(['code' => __('auth.two_factor_invalid')]);
        }

        Audit::record('auth.two_factor_enabled', $user);

        return response()->json(['data' => ['recovery_codes' => $user->refresh()->recoveryCodes()]]);
    }

    public function recoveryCodes(Request $request, GenerateNewRecoveryCodes $generate): JsonResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $user = $request->user();
        abort_unless($user->hasEnabledTwoFactorAuthentication(), 404);

        $generate($user);
        Audit::record('auth.two_factor_recovery_codes', $user);

        return response()->json(['data' => ['recovery_codes' => $user->refresh()->recoveryCodes()]]);
    }

    public function destroy(Request $request, DisableTwoFactorAuthentication $disable, TwoFactor $twoFactor): JsonResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $user = $request->user();
        if ($twoFactor->requiredFor($user)) {
            throw ValidationException::withMessages(['current_password' => __('auth.two_factor_required_cannot_disable')]);
        }

        $disable($user);
        Audit::record('auth.two_factor_disabled', $user);

        return response()->json(['data' => ['enabled' => false]]);
    }
}
