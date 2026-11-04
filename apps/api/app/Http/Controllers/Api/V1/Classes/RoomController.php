<?php

namespace App\Http\Controllers\Api\V1\Classes;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Rooms and studios at a branch. Managers and owners keep the list. */
class RoomController extends Controller
{
    public function index(Request $request, Branch $branch): JsonResponse
    {
        abort_unless($request->user()->canAccessBranch($branch), 403);

        return response()->json(['data' => Room::where('branch_id', $branch->id)->orderBy('name')->get()->map->present()]);
    }

    public function store(Request $request, Branch $branch): JsonResponse
    {
        $this->authorize('update', $branch);
        $room = Room::create(['organization_id' => $branch->organization_id, 'branch_id' => $branch->id, ...$this->validated($request, $branch)]);

        return response()->json(['data' => $room->present()], 201);
    }

    public function update(Request $request, Branch $branch, Room $room): JsonResponse
    {
        $this->authorize('update', $branch);
        abort_unless($room->branch_id === $branch->id, 404);
        $room->update($this->validated($request, $branch, $room));

        return response()->json(['data' => $room->present()]);
    }

    public function destroy(Request $request, Branch $branch, Room $room): JsonResponse
    {
        $this->authorize('update', $branch);
        abort_unless($room->branch_id === $branch->id, 404);
        $room->delete();

        return response()->json(status: 204);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, Branch $branch, ?Room $room = null): array
    {
        return $request->validate([
            'name' => [$room ? 'sometimes' : 'required', 'string', 'max:60', Rule::unique('rooms')->where('branch_id', $branch->id)->ignore($room?->id)],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:500'],
        ]);
    }
}
