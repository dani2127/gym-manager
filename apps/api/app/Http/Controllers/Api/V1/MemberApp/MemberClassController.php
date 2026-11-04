<?php

namespace App\Http\Controllers\Api\V1\MemberApp;

use App\Classes\Bookings;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ClassBooking;
use App\Models\ClassSession;
use App\Models\Member;
use App\Models\Membership;
use App\Support\Settings\SettingsRepository;
use App\Support\Tenancy\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Classes in the member app: the coming week, booking, the waitlist, cancelling. */
class MemberClassController extends Controller
{
    public function __construct(private Bookings $bookings, private SettingsRepository $settings) {}

    public function index(Request $request, Tenant $tenant): JsonResponse
    {
        $member = $this->member($request);
        $organization = $tenant->organization();
        $branches = $this->branchesFor($member);
        $days = (int) $this->settings->get('classes.booking_days', $organization);

        $sessions = ClassSession::whereIn('branch_id', $branches->pluck('id'))
            ->where('starts_at', '>', now()->subHour())
            ->where('starts_at', '<', now()->addDays($days)->endOfDay())
            ->with('classType', 'trainer', 'room', 'branch.organization')
            ->orderBy('starts_at')->get()
            ->filter(fn (ClassSession $s) => (bool) $this->settings->get('classes.enabled', $organization, $s->branch));

        $mine = ClassBooking::where('member_id', $member->id)->whereIn('class_session_id', $sessions->pluck('id'))->get()->keyBy('class_session_id');

        return response()->json([
            'data' => $sessions->values()->map(function (ClassSession $s) use ($member, $mine) {
                $booking = $mine->get($s->id);
                $active = $booking && $booking->status !== 'cancelled';
                [$membership, $reason] = $active ? [null, null] : $this->bookings->eligibility($member, $s);

                return [
                    ...$s->present(),
                    'my_booking' => $active ? ['status' => $booking->status, 'waitlist_position' => $booking->waitlist_position] : null,
                    'can_book' => ! $active && $membership !== null,
                    'reason' => $reason ? __("classes.reason.{$reason}") : null,
                ];
            }),
            'meta' => ['credits' => $this->credits($member)],
        ]);
    }

    public function book(Request $request, ClassSession $classSession): JsonResponse
    {
        $member = $this->member($request);
        abort_unless($this->branchesFor($member)->contains('id', $classSession->branch_id), 404);

        $booking = $this->bookings->book($classSession, $member, $request->user());

        return response()->json(['data' => ['status' => $booking->status, 'waitlist_position' => $booking->waitlist_position]], 201);
    }

    public function cancel(Request $request, ClassSession $classSession): JsonResponse
    {
        $member = $this->member($request);
        $booking = ClassBooking::where('class_session_id', $classSession->id)->where('member_id', $member->id)
            ->whereIn('status', ['booked', 'waitlisted'])->first()
            ?? throw ValidationException::withMessages(['booking' => __('classes.not_cancellable')]);

        $booking = $this->bookings->cancel($booking, $request->user());

        return response()->json(['data' => ['status' => $booking->status, 'late_cancel' => $booking->late_cancel]]);
    }

    /** Upcoming and recent bookings, for the Classes tab. */
    public function bookings(Request $request): JsonResponse
    {
        $bookings = ClassBooking::where('member_id', $this->member($request)->id)->where('status', '!=', 'cancelled')
            ->whereHas('session', fn ($q) => $q->where('starts_at', '>', now()->subDays(30)))
            ->with('session.classType', 'session.trainer', 'session.room', 'session.branch')
            ->get()->sortBy(fn (ClassBooking $b) => $b->session->starts_at)->values();

        return response()->json(['data' => $bookings->map->present()]);
    }

    /** The home branch plus every branch a current membership opens. */
    private function branchesFor(Member $member)
    {
        $branches = Branch::where('status', 'active')->get();
        $memberships = Membership::where('member_id', $member->id)->whereIn('status', ['active', 'frozen'])->get();

        return $branches->filter(fn (Branch $b) => $b->id === $member->home_branch_id
            || $memberships->contains(fn (Membership $m) => $m->coversBranch($b, $member)));
    }

    /** @return array<int, array{plan: string, left: ?int}> class credits per current membership */
    private function credits(Member $member): array
    {
        return Membership::where('member_id', $member->id)->where('status', 'active')->get()
            ->map(fn (Membership $m) => ['plan' => $m->planName(), 'left' => $this->bookings->creditsLeft($m), 'included' => ($m->plan_snapshot['class_credits'] ?? null) !== 0])
            ->values()->all();
    }

    private function member(Request $request): Member
    {
        return $request->attributes->get('member');
    }
}
