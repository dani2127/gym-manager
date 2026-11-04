<?php

namespace App\Http\Controllers\Api\V1\CheckIns;

use App\CheckIns\Lockers;
use App\Http\Controllers\Controller;
use App\Http\Resources\CheckInResource;
use App\Models\Branch;
use App\Models\CheckIn;
use App\Models\Locker;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A branch's lockers. Desk staff see them and hand them out; owners and the
 * branch's managers add, renumber, mark out of order and remove them.
 */
class LockerController extends Controller
{
    public function __construct(private Lockers $lockers) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        abort_unless($request->user()->worksDeskAt($branch), 403);

        $inUse = $this->lockers->inUse($branch);
        $holders = CheckIn::whereIn('id', $inUse->values())->with('member')->get()->keyBy('id');

        $lockers = Locker::where('branch_id', $branch->id)->get()
            ->sortBy(fn (Locker $l) => sprintf('%010s', $l->number))
            ->values()
            ->map(function (Locker $l) use ($inUse, $holders) {
                $holder = $inUse->has($l->id) ? $holders[$inUse[$l->id]] ?? null : null;

                return [
                    'id' => $l->id,
                    'number' => $l->number,
                    'zone' => $l->zone,
                    'status' => $l->status,
                    'in_use_by' => $holder ? ['check_in' => $holder->ulid, 'member' => CheckInResource::member($holder->member)] : null,
                ];
            });

        return response()->json([
            'data' => $lockers,
            'meta' => ['total' => $lockers->count(), 'in_use' => $inUse->count(), 'free' => $lockers->where('status', 'available')->whereNull('in_use_by')->count()],
        ]);
    }

    /** Adds a numbered range, e.g. 1–40 in the women's zone. */
    public function store(Request $request, Branch $branch): JsonResponse
    {
        abort_unless($request->user()->managesBranch($branch), 403);

        $data = $request->validate([
            'from' => ['required', 'integer', 'min:1', 'max:9999'],
            'to' => ['required', 'integer', 'gte:from', 'max:9999'],
            'prefix' => ['nullable', 'string', 'max:3', 'alpha:ascii'],
            'zone' => ['required', Rule::in(['male', 'female', 'unisex'])],
        ]);
        abort_if($data['to'] - $data['from'] >= 300, 422, 'At most 300 lockers at a time.');

        $numbers = collect(range($data['from'], $data['to']))->map(fn (int $n) => strtoupper($data['prefix'] ?? '').$n);
        $existing = Locker::where('branch_id', $branch->id)->whereIn('number', $numbers)->pluck('number');
        if ($existing->isNotEmpty()) {
            throw ValidationException::withMessages(['from' => __('checkins.locker_exists', ['number' => $existing->first()])]);
        }

        $numbers->each(fn (string $number) => Locker::create(['branch_id' => $branch->id, 'number' => $number, 'zone' => $data['zone']]));
        Audit::record('lockers.added', $branch, ['count' => $numbers->count(), 'zone' => $data['zone'], 'first' => $numbers->first(), 'last' => $numbers->last()]);

        return $this->index($request, $branch)->setStatusCode(201);
    }

    public function update(Request $request, Branch $branch, Locker $locker): JsonResponse
    {
        abort_unless($request->user()->managesBranch($branch) && $locker->branch_id === $branch->id, 404);

        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['available', 'out_of_order'])],
            'zone' => ['sometimes', Rule::in(['male', 'female', 'unisex'])],
        ]);
        $locker->update($data);

        return response()->json(['data' => $locker->only('id', 'number', 'zone', 'status')]);
    }

    public function destroy(Request $request, Branch $branch, Locker $locker): JsonResponse
    {
        abort_unless($request->user()->managesBranch($branch) && $locker->branch_id === $branch->id, 404);

        if ($this->lockers->inUse($branch)->has($locker->id)) {
            throw ValidationException::withMessages(['locker' => __('checkins.locker_in_use', ['number' => $locker->number])]);
        }

        $locker->delete();
        Audit::record('lockers.removed', $branch, ['number' => $locker->number]);

        return response()->json(status: 204);
    }

    /** Hands a locker to someone inside: the one chosen, or the next free one. */
    public function assign(Request $request, CheckIn $checkIn): JsonResponse
    {
        abort_unless($request->user()->worksDeskAt($checkIn->branch), 403);

        $data = $request->validate(['locker' => ['nullable', 'integer']]);
        $wanted = isset($data['locker']) ? Locker::where('branch_id', $checkIn->branch_id)->findOrFail($data['locker']) : null;

        return response()->json(['data' => new CheckInResource($this->lockers->assign($checkIn, $wanted))]);
    }

    public function release(Request $request, CheckIn $checkIn): JsonResponse
    {
        abort_unless($request->user()->worksDeskAt($checkIn->branch), 403);

        return response()->json(['data' => new CheckInResource($this->lockers->release($checkIn))]);
    }
}
