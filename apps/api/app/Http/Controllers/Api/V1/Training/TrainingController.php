<?php

namespace App\Http\Controllers\Api\V1\Training;

use App\Enums\BranchRole;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Member;
use App\Models\PtEnrollment;
use App\Models\PtSession;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Training\PersonalTraining;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Personal training for staff. A trainer only ever sees their own clients and
 * sessions; the desk and managers see the branch's. Session notes are for the
 * trainer (and the owner) only.
 */
class TrainingController extends Controller
{
    public function __construct(private PersonalTraining $training) {}

    /** Trainers at a branch, for the desk's sale form and the timetable. */
    public function trainers(Request $request, Branch $branch): JsonResponse
    {
        abort_unless($request->user()->canAccessBranch($branch), 403);
        $trainers = $branch->staff()->wherePivot('role', BranchRole::Trainer->value)->where('status', 'active')->orderBy('name')->get();
        $profiles = TrainerProfile::whereIn('user_id', $trainers->pluck('id'))->get()->keyBy('user_id');

        return response()->json(['data' => $trainers->map(fn (User $t) => TrainerProfile::presentFor($t, $profiles[$t->id] ?? null))]);
    }

    /** A member's packages, for the member page. */
    public function memberEnrollments(Request $request, Member $member): JsonResponse
    {
        $user = $request->user();
        $this->authorize('view', $member);
        $enrollments = PtEnrollment::where('member_id', $member->id)
            ->when(! $this->seesAll($user, $member), fn ($q) => $q->where('trainer_id', $user->id))
            ->with('trainer', 'branch', 'invoice')->latest()->limit(20)->get();

        return response()->json(['data' => $enrollments->map(fn (PtEnrollment $e) => [
            ...$e->present(),
            'upcoming' => $e->sessions()->where('status', 'scheduled')->where('starts_at', '>', now())->limit(5)->get()->map->present(),
        ])]);
    }

    /** A trainer's clients with active packages. */
    public function clients(Request $request): JsonResponse
    {
        $enrollments = PtEnrollment::where('trainer_id', $request->user()->id)->whereIn('status', ['active', 'pending_payment'])
            ->with('member.homeBranch', 'trainer', 'branch')->orderBy('ends_on')->get();

        return response()->json(['data' => $enrollments->map->present()]);
    }

    /** ?mine=1 for a trainer, or ?branch=… for the desk. */
    public function sessions(Request $request): JsonResponse
    {
        $data = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'days' => ['nullable', 'integer', 'min:1', 'max:31'], 'mine' => ['nullable', 'boolean']]);
        $user = $request->user();
        $tz = $user->organization()->withoutGlobalScopes()->value('timezone') ?: config('gym.timezone');
        $from = Carbon::parse($data['from'] ?? Carbon::now($tz)->toDateString(), $tz)->startOfDay();
        $to = $from->copy()->addDays((int) ($data['days'] ?? 7));

        $sessions = PtSession::where('trainer_id', $user->id)
            ->whereBetween('starts_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->with('member.homeBranch', 'trainer', 'branch', 'enrollment')->orderBy('starts_at')->get();

        return response()->json(['data' => $sessions->map(fn (PtSession $s) => $s->present(withNotes: true))]);
    }

    public function schedule(Request $request, PtEnrollment $ptEnrollment): JsonResponse
    {
        $this->authorizeManage($request->user(), $ptEnrollment);
        $data = $request->validate(['starts_at' => ['required', 'date_format:Y-m-d\TH:i'], 'duration_min' => ['required', 'integer', 'min:15', 'max:180']]);
        $tz = $ptEnrollment->branch->organization->timezone ?: config('gym.timezone');

        $session = $this->training->schedule($ptEnrollment, Carbon::parse($data['starts_at'], $tz)->utc(), (int) $data['duration_min'], $request->user());

        return response()->json(['data' => $session->load('member', 'trainer', 'branch', 'enrollment')->present()], 201);
    }

    public function mark(Request $request, PtSession $ptSession): JsonResponse
    {
        $this->authorizeManage($request->user(), $ptSession->enrollment);
        $data = $request->validate(['status' => ['required', 'in:completed,no_show,scheduled']]);

        $session = $this->training->mark($ptSession, $data['status'], $request->user());

        return response()->json(['data' => $session->load('member', 'trainer', 'branch', 'enrollment')->present($this->seesNotes($request->user(), $session))]);
    }

    public function cancel(Request $request, PtSession $ptSession): JsonResponse
    {
        $this->authorizeManage($request->user(), $ptSession->enrollment);

        return response()->json(['data' => $this->training->cancel($ptSession, $request->user())->present()]);
    }

    /** Private notes: only the trainer who led it. */
    public function notes(Request $request, PtSession $ptSession): JsonResponse
    {
        abort_unless($ptSession->trainer_id === $request->user()->id, 403);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);
        $ptSession->forceFill(['trainer_notes' => $data['notes']])->save();

        return response()->json(['data' => $ptSession->present(withNotes: true)]);
    }

    /** The package's trainer, and the desk and managers where it was sold. */
    private function authorizeManage(User $user, PtEnrollment $enrollment): void
    {
        abort_unless($enrollment->trainer_id === $user->id || $user->worksDeskAt($enrollment->branch), 403);
    }

    private function seesAll(User $user, Member $member): bool
    {
        return $user->isOwner() || ($member->homeBranch && $user->worksDeskAt($member->homeBranch));
    }

    private function seesNotes(User $user, PtSession $session): bool
    {
        return $session->trainer_id === $user->id || $user->isOwner();
    }
}
