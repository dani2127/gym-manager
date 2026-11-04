<?php

namespace App\Http\Controllers\Api\V1\Classes;

use App\Classes\Timetable;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ClassSchedule;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Room;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** A branch's weekly timetable. Managers and owners edit it; sessions follow. */
class ClassScheduleController extends Controller
{
    public function __construct(private Timetable $timetable) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        abort_unless($request->user()->canAccessBranch($branch), 403);

        $schedules = ClassSchedule::where('branch_id', $branch->id)->where('is_active', true)
            ->with('classType', 'trainer', 'room')->orderBy('weekday')->orderBy('start_time')->get();

        return response()->json(['data' => $schedules->map->present()]);
    }

    public function store(Request $request, Branch $branch): JsonResponse
    {
        $this->authorize('update', $branch);
        $schedule = ClassSchedule::create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'valid_from' => $branch->organization->today()->toDateString(),
            ...$this->validated($request, $branch, true),
        ]);
        $created = $this->timetable->generate($schedule->load('branch.organization'));
        Audit::record('class_schedule.added', $schedule, ['weekday' => $schedule->weekday, 'start_time' => $schedule->start_time, 'sessions' => $created]);

        return response()->json(['data' => $schedule->load('classType', 'trainer', 'room')->present()], 201);
    }

    public function update(Request $request, Branch $branch, ClassSchedule $classSchedule): JsonResponse
    {
        $this->authorize('update', $branch);
        abort_unless($classSchedule->branch_id === $branch->id, 404);
        $classSchedule->update($this->validated($request, $branch, false));
        $this->timetable->applyChanges($classSchedule);
        Audit::record('class_schedule.updated', $classSchedule, $classSchedule->getChanges());

        return response()->json(['data' => $classSchedule->load('classType', 'trainer', 'room')->present()]);
    }

    /** Ends the slot: future sessions nobody booked go; booked ones stay until cancelled. */
    public function destroy(Request $request, Branch $branch, ClassSchedule $classSchedule): JsonResponse
    {
        $this->authorize('update', $branch);
        abort_unless($classSchedule->branch_id === $branch->id, 404);
        $classSchedule->update(['is_active' => false, 'valid_until' => $branch->organization->today()->toDateString()]);
        ClassSession::where('schedule_id', $classSchedule->id)->where('starts_at', '>', now())
            ->where('booked_count', 0)->where('waitlist_count', 0)->delete();
        Audit::record('class_schedule.removed', $classSchedule);

        return response()->json(status: 204);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, Branch $branch, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';
        $data = $request->validate([
            'class_type' => [$req, 'string', 'max:26'],
            'trainer' => ['sometimes', 'nullable', 'string', 'max:26'],
            'room' => ['sometimes', 'nullable', 'string', 'max:26'],
            'weekday' => [$req, 'integer', 'between:0,6'],
            'start_time' => [$req, 'date_format:H:i'],
            'duration_min' => [$req, 'integer', 'min:10', 'max:240'],
            'capacity' => [$req, 'integer', 'min:1', 'max:500'],
        ]);

        $out = collect($data)->except(['class_type', 'trainer', 'room'])->all();
        if (array_key_exists('class_type', $data)) {
            $out['class_type_id'] = ClassType::where('ulid', $data['class_type'])->where('is_active', true)->value('id')
                ?? throw ValidationException::withMessages(['class_type' => __('validation.exists', ['attribute' => 'class type'])]);
        }
        if (array_key_exists('trainer', $data)) {
            $out['trainer_id'] = $data['trainer'] ? self::trainerAt($branch, $data['trainer'])->id : null;
        }
        if (array_key_exists('room', $data)) {
            $out['room_id'] = $data['room'] ? (Room::where('branch_id', $branch->id)->where('ulid', $data['room'])->value('id')
                ?? throw ValidationException::withMessages(['room' => __('validation.exists', ['attribute' => 'room'])])) : null;
        }

        return $out;
    }

    /** Any staff member assigned to the branch can lead a class. */
    public static function trainerAt(Branch $branch, string $ulid): User
    {
        $user = User::where('ulid', $ulid)->first();
        if (! $user || ! $user->canAccessBranch($branch) || ! $user->isStaff()) {
            throw ValidationException::withMessages(['trainer' => __('classes.trainer_not_at_branch')]);
        }

        return $user;
    }
}
