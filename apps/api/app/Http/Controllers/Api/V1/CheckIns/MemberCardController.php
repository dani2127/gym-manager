<?php

namespace App\Http\Controllers\Api\V1\CheckIns;

use App\CheckIns\MemberCodes;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The printed member card: its QR code, and reissuing it when a card is lost. */
class MemberCardController extends Controller
{
    public function __construct(private MemberCodes $codes) {}

    public function show(Request $request, Member $member): JsonResponse
    {
        $this->authorize('update', $member);

        return $this->card($member);
    }

    /** Makes every earlier card for this member stop working. */
    public function reissue(Request $request, Member $member): JsonResponse
    {
        $this->authorize('update', $member);

        $member->increment('card_version');
        Audit::record('member.card_reissued', $member, ['member_no' => $member->code, 'card_version' => $member->card_version]);

        return $this->card($member->refresh());
    }

    private function card(Member $member): JsonResponse
    {
        return response()->json(['data' => [
            'code' => $this->codes->cardCode($member),
            'version' => $member->card_version,
        ]]);
    }
}
