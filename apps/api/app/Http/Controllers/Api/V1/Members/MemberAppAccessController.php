<?php

namespace App\Http\Controllers\Api\V1\Members;

use App\Enums\AccountType;
use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\User;
use App\Support\Audit;
use App\Support\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Member app access, run from the desk: invite (a setup code given in
 * person), see whether the member has signed in, and sign out every device
 * when a phone is lost.
 */
class MemberAppAccessController extends Controller
{
    public const SETUP_DIGITS = 8;

    public const SETUP_TTL_HOURS = 72;

    public function show(Request $request, Member $member): JsonResponse
    {
        $this->authorize('update', $member);

        return response()->json(['data' => $this->state($member)]);
    }

    /**
     * Creates (or reuses) the member's login and returns a setup code. The
     * member sets their own password with it, so the desk never knows it.
     */
    public function invite(Request $request, Member $member, OtpService $otp): JsonResponse
    {
        $this->authorize('update', $member);

        if (! $member->phone) {
            throw ValidationException::withMessages(['phone' => __('members.app_needs_phone')]);
        }

        $user = DB::transaction(function () use ($member) {
            $user = $member->user ?? User::withoutGlobalScopes()
                ->where('organization_id', $member->organization_id)
                ->where('phone', $member->phone)
                ->first();

            // A phone shared with another member's login can't be used twice.
            if ($user && Member::where('user_id', $user->id)->whereKeyNot($member->id)->exists()) {
                throw ValidationException::withMessages(['phone' => __('members.app_phone_taken')]);
            }

            $user ??= User::create([
                'organization_id' => $member->organization_id,
                'account_type' => AccountType::Member,
                'name' => $member->full_name,
                'phone' => $member->phone,
                // Unusable until the member sets their own with the setup code.
                'password' => Str::random(40),
            ]);

            $member->forceFill(['user_id' => $user->id])->save();

            return $user;
        });

        $code = $otp->issueForDesk($user, OtpPurpose::AppSetup, self::SETUP_DIGITS, self::SETUP_TTL_HOURS * 60);
        Audit::record('member.app_invited', $member, ['member_no' => $member->code]);

        return response()->json(['data' => [
            ...$this->state($member->refresh()),
            'setup_code' => substr($code, 0, 4).' '.substr($code, 4),
            'setup_expires_at' => now()->addHours(self::SETUP_TTL_HOURS),
        ]]);
    }

    /**
     * Lost or stolen phone: every device is signed out and the QR secret is
     * replaced, so codes the old phone can still make offline stop working.
     * The printed card is replaced too and must be reprinted.
     */
    public function revoke(Request $request, Member $member): JsonResponse
    {
        $this->authorize('update', $member);

        DB::transaction(function () use ($member) {
            if ($member->user) {
                $member->user->tokens()->delete();
                DB::table('sessions')->where('user_id', $member->user->id)->delete();
            }
            $member->forceFill(['qr_secret' => Str::random(40), 'card_version' => $member->card_version + 1])->save();
        });

        Audit::record('member.app_signed_out', $member, ['member_no' => $member->code]);

        return response()->json(['data' => $this->state($member->refresh())]);
    }

    /** @return array<string, mixed> */
    private function state(Member $member): array
    {
        $user = $member->user;

        return [
            'status' => ! $user ? 'none' : ($user->phone_verified_at && $user->last_login_at ? 'active' : 'invited'),
            'last_login_at' => $user?->last_login_at,
        ];
    }
}
