<?php

namespace App\Http\Controllers\Api\V1\MemberApp;

use App\Http\Controllers\Controller;
use App\Memberships\Freezes;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipFreeze;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** A member asks to pause their membership; the desk approves. Within the plan's limits only. */
class MemberFreezeController extends Controller
{
    public function store(Request $request, Membership $membership, Freezes $freezes): JsonResponse
    {
        abort_unless($membership->member_id === $this->member($request)->id, 404);
        $data = $request->validate([
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'days' => ['required', 'integer', 'min:1', 'max:365'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $freeze = $freezes->create($membership, Carbon::parse($data['starts_on']), (int) $data['days'], $data['reason'] ?? null, $request->user(), asRequest: true);

        return response()->json(['data' => $freeze->present()], 201);
    }

    /** Withdraw a request, or call off an approved freeze before it starts. */
    public function cancel(Request $request, MembershipFreeze $freeze, Freezes $freezes): JsonResponse
    {
        abort_unless($freeze->membership->member_id === $this->member($request)->id, 404);

        return response()->json(['data' => $freezes->cancel($freeze, $request->user())->present()]);
    }

    private function member(Request $request): Member
    {
        return $request->attributes->get('member');
    }
}
