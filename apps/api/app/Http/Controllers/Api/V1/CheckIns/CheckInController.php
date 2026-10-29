<?php

namespace App\Http\Controllers\Api\V1\CheckIns;

use App\CheckIns\CheckInGate;
use App\CheckIns\CheckInOutcome;
use App\Http\Controllers\Controller;
use App\Http\Resources\CheckInResource;
use App\Models\Branch;
use App\Models\CheckIn;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The door: scan a QR code, check someone in by hand, let a denied member in
 * anyway, check out, and see who is inside right now.
 */
class CheckInController extends Controller
{
    public function __construct(private CheckInGate $gate) {}

    public function scan(Request $request, Branch $branch): JsonResponse
    {
        abort_unless($request->user()->worksDeskAt($branch), 403);

        $data = $request->validate(['code' => ['required', 'string', 'max:120']]);

        return $this->outcome($this->gate->scan($branch, $data['code'], 'qr', $request->user()));
    }

    public function store(Request $request, Branch $branch): JsonResponse
    {
        abort_unless($request->user()->worksDeskAt($branch), 403);

        $data = $request->validate(['member' => ['required', 'string', 'max:26']]);
        $member = Member::where('ulid', $data['member'])->firstOrFail();

        return $this->outcome($this->gate->admit($branch, $member, 'search', $request->user()));
    }

    public function override(Request $request, CheckIn $checkIn): JsonResponse
    {
        abort_unless($request->user()->worksDeskAt($checkIn->branch), 403);

        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);
        if ($checkIn->result !== 'denied' || ! in_array($checkIn->denial_reason, CheckInGate::OVERRIDABLE, true)) {
            throw ValidationException::withMessages(['reason' => __('checkins.not_overridable')]);
        }

        return $this->outcome(CheckInOutcome::recorded($this->gate->override($checkIn, $data['reason'], $request->user())));
    }

    public function checkOut(Request $request, CheckIn $checkIn): JsonResponse
    {
        abort_unless($request->user()->worksDeskAt($checkIn->branch), 403);

        return response()->json(['data' => new CheckInResource($this->gate->checkOut($checkIn)->load('member.homeBranch', 'membership', 'branch', 'locker'))]);
    }

    /**
     * The branch's day: who is inside now, recent scans, and counts.
     * `scope=inside` lists people inside; `scope=recent` the latest scans.
     */
    public function index(Request $request, Branch $branch): JsonResponse
    {
        abort_unless($request->user()->worksDeskAt($branch), 403);

        $scope = $request->validate(['scope' => ['nullable', Rule::in(['inside', 'recent'])]])['scope'] ?? 'inside';
        $tz = $branch->organization->timezone ?: config('gym.timezone');
        $midnight = Carbon::now($tz)->startOfDay()->utc();

        $today = CheckIn::where('branch_id', $branch->id)->where('checked_in_at', '>=', $midnight);

        $list = (clone $today)
            ->when($scope === 'inside', fn ($q) => $q->inside())
            ->with('member.homeBranch', 'membership', 'handler', 'locker')
            ->latest('checked_in_at')
            ->latest('id')
            ->limit($scope === 'inside' ? 200 : 30)
            ->get();

        return response()->json([
            'data' => CheckInResource::collection($list),
            'meta' => [
                'inside' => (clone $today)->inside()->count(),
                'capacity' => $branch->capacity,
                'visits_today' => (clone $today)->whereIn('result', ['allowed', 'overridden'])->count(),
                'denied_today' => (clone $today)->where('result', 'denied')->count(),
            ],
        ]);
    }

    private function outcome(CheckInOutcome $outcome): JsonResponse
    {
        $data = $outcome->checkIn
            ? (new CheckInResource($outcome->checkIn))->resolve()
            : ['id' => null, 'result' => 'denied', 'reason' => $outcome->reason, 'overridable' => false, 'member' => $outcome->member ? CheckInResource::member($outcome->member->loadMissing('homeBranch')) : null, 'membership' => null];

        if ($outcome->result === 'already_inside') {
            $data['result'] = 'already_inside';
            $data['reason'] = 'already_inside';
            $data['overridable'] = false;
        }

        return response()->json(['data' => $data]);
    }
}
