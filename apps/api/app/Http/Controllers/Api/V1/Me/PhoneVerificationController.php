<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Support\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PhoneVerificationController extends Controller
{
    public function __construct(private OtpService $otp) {}

    public function send(Request $request): JsonResponse
    {
        if (! $this->otp->send($request->user(), OtpPurpose::PhoneVerify)) {
            throw ValidationException::withMessages(['telegram' => __('auth.telegram_required')]);
        }

        return response()->json(['message' => __('auth.verification_code_sent')], 202);
    }

    public function verify(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);

        $user = $request->user();

        if (! $this->otp->verify($user, OtpPurpose::PhoneVerify, $request->input('code'))) {
            throw ValidationException::withMessages(['code' => __('auth.code_invalid')]);
        }

        $user->forceFill(['phone_verified_at' => now()])->save();

        return response()->json(['message' => __('auth.phone_verified')]);
    }
}
