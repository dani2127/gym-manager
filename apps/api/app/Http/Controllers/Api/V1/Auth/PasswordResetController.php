<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
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

/** Password reset with a one-time code sent to the user's linked Telegram chat. */
class PasswordResetController extends Controller
{
    public function __construct(private OtpService $otp) {}

    public function sendCode(Request $request): JsonResponse
    {
        $request->validate([
            'organization' => ['required', 'string', 'max:63'],
            'phone' => ['required', 'string', 'max:20'],
        ]);

        if ($user = $this->findUser($request)) {
            $this->otp->send($user, OtpPurpose::PasswordReset);
        }

        // Same answer whether or not the account exists, to avoid enumeration.
        return response()->json(['message' => __('auth.reset_code_sent')], 202);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'organization' => ['required', 'string', 'max:63'],
            'phone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $this->findUser($request);

        if (! $user || ! $this->otp->verify($user, OtpPurpose::PasswordReset, $request->input('code'))) {
            throw ValidationException::withMessages(['code' => __('auth.code_invalid')]);
        }

        DB::transaction(function () use ($user, $request) {
            $user->forceFill(['password' => $request->input('password')])->save();
            // Sign out every device; the password may have been compromised.
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });

        Audit::record('auth.password_reset', $user, [], $user);

        return response()->json(['message' => __('auth.password_reset_done')]);
    }

    private function findUser(Request $request): ?User
    {
        $organization = Organization::where('slug', mb_strtolower($request->input('organization')))
            ->where('status', 'active')
            ->first();

        return $organization ? User::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('phone', Phone::normalize($request->input('phone')))
            ->where('status', 'active')
            ->first() : null;
    }
}
