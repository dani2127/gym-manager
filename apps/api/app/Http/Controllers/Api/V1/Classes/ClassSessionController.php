<?php

namespace App\Http\Controllers\Api\V1\Classes;

use App\Classes\Bookings;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ClassBooking;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Member;
use App\Models\Room;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Dated classes for staff: the list, the roster with attendance, desk
 * bookings, one-off classes and calling a class off.
 */
class ClassSessionController extends Controller
{
    public function __construct(private Bookings $bookings) {}

    /** ?branch=…&from=YYYY-MM-DD&days=7, or ?mine=1 for a trainer's own classes. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch' => ['nullable', 'string', 'max:26'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', 'min:1', 'max:31'],
            'mine' => ['nullable', 'boolean'],
        ]);
        $user = $request->user();
        $branch = isset($data['branch']) ? Branch::where('ulid', $data['branch'])->firstOrFail() : null;
        abort_if($branch && ! $user->canAccessBranch($branch), 403);
        abort_if(! $branch && ! $request->boolean('mine'), 422);

        $tz = $user->organization()->withoutGlobalScopes()->value('timezone') ?: config('gym.timezone');
        $from = Carbon::parse($data['from'] ?? Carbon::now($tz)->toDateString(), $tz)->startOfDay();
        $to = $from->copy()->addDays((int) ($data['days'] ?? 7));

        $sessions = ClassSession::query()
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when($request->boolean('mine'), fn ($q) => $q->where('trainer_id', $user->id))
            ->whereBetween('starts_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->with('classType', 'trainer', 'room', 'branch')
            ->orderBy('starts_at')->get();

        return response()->json(['data' => $sessions->map->present()]);
    }

    public function show(Request $request, ClassSession $classSession): JsonResponse
    {
        $this->authorizeRoster($request->user(), $classSession);
        $classSession->load('classType', 'trainer', 'room', 'branch');
        $roster = $classSession->bookings()->where('status', '!=', 'cancelled')
            ->with('member.homeBranch')->orderByRaw("status = 'waitlisted'")->orderBy('waitlist_position')->orderBy('booked_at')->get();

        return response()->json(['data' => [...$classSession->present(), 'bookings' => $roster->map->present()]]);
    }

    /** A one-off class outside the timetable. */
    public function store(Request $request, Branch $branch): JsonResponse
    {
        $this->authorize('update', $branch);
        $data = $request->validate([
            'class_type' => ['required', 'string', 'max:26'],
            'trainer' => ['nullable', 'string', 'max:26'],
            'room' => ['nullable', 'string', 'max:26'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'duration_min' => ['required', 'integer', 'min:10', 'max:240'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
        ]);
        $tz = $branch->organization->timezone ?: config('gym.timezone');
        $starts = Carbon::parse($data['starts_at'], $tz)->utc();
        if ($starts->isPast()) {
            throw ValidationException::withMessages(['starts_at' => __('classes.reason.session_started')]);
        }

        $session = ClassSession::create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'class_type_id' => ClassType::where('ulid', $data['class_type'])->value('id') ?? abort(422),
            'trainer_id' => isset($data['trainer']) ? ClassScheduleController::trainerAt($branch, $data['trainer'])->id : null,
            'room_id' => isset($data['room']) ? Room::where('branch_id', $branch->id)->where('ulid', $data['room'])->value('id') : null,
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addMinutes($data['duration_min']),
            'capacity' => $data['capacity'],
        ]);
        Audit::record('class.session_added', $session);

        return response()->json(['data' => $session->load('classType', 'trainer', 'room', 'branch')->present()], 201);
    }

    /** Change who leads it, the room, or the size (not below the people already booked). */
    public function update(Request $request, ClassSession $classSession): JsonResponse
    {
        $this->authorize('update', $classSession->branch);
        $data = $request->validate([
            'trainer' => ['sometimes', 'nullable', 'string', 'max:26'],
            'room' => ['sometimes', 'nullable', 'string', 'max:26'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);
        if (isset($data['capacity']) && $data['capacity'] < $classSession->booked_count) {
            throw ValidationException::withMessages(['capacity' => __('classes.capacity_below_booked', ['count' => $classSession->booked_count])]);
        }
        $changes = [];
        if (array_key_exists('trainer', $data)) {
            $changes['trainer_id'] = $data['trainer'] ? ClassScheduleController::trainerAt($classSession->branch, $data['trainer'])->id : null;
        }
        if (array_key_exists('room', $data)) {
            $changes['room_id'] = $data['room'] ? Room::where('branch_id', $classSession->branch_id)->where('ulid', $data['room'])->value('id') : null;
        }
        if (isset($data['capacity'])) {
            $changes['capacity'] = $data['capacity'];
        }
        $classSession->update($changes);

        return response()->json(['data' => $classSession->load('classType', 'trainer', 'room', 'branch')->present()]);
    }

    public function cancel(Request $request, ClassSession $classSession): JsonResponse
    {
        $this->authorize('update', $classSession->branch);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        return response()->json(['data' => $this->bookings->cancelSession($classSession, $data['reason'], $request->user())->present()]);
    }

    /** The desk books a member in (members book themselves in the app). */
    public function book(Request $request, ClassSession $classSession): JsonResponse
    {
        abort_unless($request->user()->worksDeskAt($classSession->branch), 403);
        $data = $request->validate(['member' => ['required', 'string', 'max:26']]);
        $member = Member::where('ulid', $data['member'])->firstOrFail();

        $booking = $this->bookings->book($classSession, $member, $request->user(), byStaff: true);

        return response()->json(['data' => $booking->load('member.homeBranch')->present()], 201);
    }

    public function cancelBooking(Request $request, ClassBooking $classBooking): JsonResponse
    {
        abort_unless($request->user()->worksDeskAt($classBooking->session->branch), 403);

        return response()->json(['data' => $this->bookings->cancel($classBooking, $request->user(), byStaff: true)->load('member.homeBranch')->present()]);
    }

    public function attendance(Request $request, ClassBooking $classBooking): JsonResponse
    {
        $this->authorizeRoster($request->user(), $classBooking->session);
        $data = $request->validate(['status' => ['required', 'in:attended,no_show,booked']]);

        return response()->json(['data' => $this->bookings->markAttendance($classBooking, $data['status'], $request->user())->load('member.homeBranch')->present()]);
    }

    /** The desk and managers of the branch, and the trainer leading the class. */
    private function authorizeRoster(User $user, ClassSession $session): void
    {
        abort_unless($user->worksDeskAt($session->branch) || $session->trainer_id === $user->id, 403);
    }
}
