<?php

namespace App\Http\Controllers\Api\V1\Memberships;

use App\Http\Controllers\Controller;
use App\Http\Resources\CheckInResource;
use App\Memberships\Freezes;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\MembershipFreeze;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Freezes at the desk: freeze on the spot, handle requests from the member
 * app, end a freeze early or call one off. Going past the plan's limits is
 * for managers and owners.
 */
class FreezeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $branches = $request->user()->moneyBranches()->pluck('id');
        abort_if($branches->isEmpty(), 403);

        $request->validate(['branch' => ['nullable', 'string', 'max:26'], 'status' => ['nullable', 'in:open,done']]);
        $branchId = $request->filled('branch') ? Branch::where('ulid', $request->input('branch'))->value('id') : null;
        $done = $request->input('status') === 'done';

        $freezes = MembershipFreeze::query()
            ->whereIn('branch_id', $branches)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($done,
                fn ($q) => $q->whereIn('status', ['completed', 'rejected', 'cancelled'])->latest('updated_at')->limit(30),
                // Requests first, then running and upcoming freezes by date.
                fn ($q) => $q->whereIn('status', MembershipFreeze::OPEN)->orderByRaw("status = 'requested' desc")->orderBy('starts_on'))
            ->with('membership.member.homeBranch', 'requester', 'reviewer')
            ->get();

        return response()->json([
            'data' => $freezes->map(fn (MembershipFreeze $f) => $this->present($f)),
            'meta' => ['requested' => MembershipFreeze::whereIn('branch_id', $branches)->where('status', 'requested')->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count()],
        ]);
    }

    public function store(Request $request, Membership $membership, Freezes $freezes): JsonResponse
    {
        $this->authorizeDesk($request->user(), $membership);
        $data = $request->validate([
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'days' => ['required', 'integer', 'min:1', 'max:365'],
            'reason' => ['nullable', 'string', 'max:255'],
            'override' => ['sometimes', 'boolean'],
        ]);
        $override = (bool) ($data['override'] ?? false);
        abort_if($override && ! $request->user()->managesBranch($membership->branch), 403);

        $freeze = $freezes->create($membership, Carbon::parse($data['starts_on']), (int) $data['days'], $data['reason'] ?? null, $request->user(), override: $override);

        return response()->json(['data' => $this->present($freeze)], 201);
    }

    public function approve(Request $request, MembershipFreeze $freeze, Freezes $freezes): JsonResponse
    {
        $this->authorizeDesk($request->user(), $freeze->membership);
        $override = $request->boolean('override');
        abort_if($override && ! $request->user()->managesBranch($freeze->membership->branch), 403);

        return response()->json(['data' => $this->present($freezes->approve($freeze, $request->user(), $override))]);
    }

    public function reject(Request $request, MembershipFreeze $freeze, Freezes $freezes): JsonResponse
    {
        $this->authorizeDesk($request->user(), $freeze->membership);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        return response()->json(['data' => $this->present($freezes->reject($freeze, $data['reason'], $request->user()))]);
    }

    public function end(Request $request, MembershipFreeze $freeze, Freezes $freezes): JsonResponse
    {
        $this->authorizeDesk($request->user(), $freeze->membership);

        return response()->json(['data' => $this->present($freezes->end($freeze, $request->user()))]);
    }

    public function cancel(Request $request, MembershipFreeze $freeze, Freezes $freezes): JsonResponse
    {
        $this->authorizeDesk($request->user(), $freeze->membership);

        return response()->json(['data' => $this->present($freezes->cancel($freeze, $request->user()))]);
    }

    /** The desk where it was sold, or the member's home desk. */
    private function authorizeDesk(User $user, Membership $membership): void
    {
        $home = $membership->member?->homeBranch;
        abort_unless($user->worksDeskAt($membership->branch) || ($home && $user->worksDeskAt($home)), 403);
    }

    /** @return array<string, mixed> */
    private function present(MembershipFreeze $freeze): array
    {
        $freeze->loadMissing('membership.member.homeBranch', 'requester', 'reviewer');
        $membership = $freeze->membership;

        return [
            ...$freeze->present(),
            'member' => $membership->member ? CheckInResource::member($membership->member) : null,
            'membership' => [
                'id' => $membership->ulid,
                'plan' => $membership->planName(),
                'ends_on' => $membership->ends_on?->toDateString(),
                'status' => $membership->status,
            ],
        ];
    }
}
