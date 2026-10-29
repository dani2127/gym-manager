<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use App\Support\Audit;
use App\Support\OtpService;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** A member sets their app password with the setup code from the desk, and is signed in. */
class MemberSetupController extends Controller
{
    use IssuesAuthentication;

    public function __invoke(Request $request, OtpService $otp): JsonResponse
    {
        $request->validate([
            'organization' => ['required', 'string', 'max:63'],
            'phone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', 'max:12'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $organization = Organization::where('slug', mb_strtolower($request->input('organization')))->where('status', 'active')->first();
        $user = $organization ? User::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('phone', Phone::normalize($request->input('phone')))
            ->where('status', 'active')
            ->first() : null;
        $isMember = $user && Member::withoutGlobalScopes()->where('user_id', $user->id)->exists();

        $code = preg_replace('/\D/', '', $request->input('code'));
        if (! $isMember || ! $otp->verify($user, OtpPurpose::AppSetup, $code)) {
            throw ValidationException::withMessages(['code' => __('auth.code_invalid')]);
        }

        DB::transaction(function () use ($user, $request) {
            // The desk checked who they are in person, so the phone counts as verified.
            $user->forceFill(['password' => $request->input('password'), 'phone_verified_at' => $user->phone_verified_at ?? now()])->save();
            $user->tokens()->delete();
        });

        Audit::record('member.app_setup', $user, [], $user);

        return $this->issueAuthentication($request, $user);
    }
}
