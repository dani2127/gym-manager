<?php

namespace App\Classes;

use App\Models\ClassBooking;
use App\Models\ClassSession;
use App\Models\Member;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\ClassBookingNotification;
use App\Support\Audit;
use App\Support\Settings\SettingsRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Booking group classes. A booking needs a membership that is valid at the
 * session's branch on its day and has class credits left (no limit when the
 * plan sets none). A full class puts people on a waitlist; when a place frees
 * up, the first person still eligible moves in. The session row is locked so
 * two people can't take the last place.
 */
class Bookings
{
    public function __construct(private SettingsRepository $settings) {}

    /**
     * The membership that would pay for this class, or why there isn't one.
     *
     * @return array{0: ?Membership, 1: ?string}
     */
    public function eligibility(Member $member, ClassSession $session, bool $byStaff = false): array
    {
        $session->loadMissing('branch.organization');
        if ($member->status === 'banned') {
            return [null, 'member_banned'];
        }
        if ($session->status !== 'scheduled') {
            return [null, 'session_cancelled'];
        }
        // The desk can still add a walk-in until the class ends; members only before it starts.
        if (($byStaff ? $session->ends_at : $session->starts_at)->isPast()) {
            return [null, 'session_started'];
        }
        if (! $byStaff && $session->starts_at->gt(now()->addDays($this->bookingDays($session)))) {
            return [null, 'too_early'];
        }

        $day = Carbon::parse($session->starts_at->copy()->setTimezone($this->tz($session))->toDateString());
        $memberships = Membership::where('member_id', $member->id)->where('status', 'active')
            ->whereDate('starts_on', '<=', $day)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $day))
            ->orderBy('starts_on')->get();

        if ($memberships->isEmpty()) {
            return [null, 'membership_needed'];
        }
        $here = $memberships->filter(fn (Membership $m) => $m->coversBranch($session->branch, $member));
        if ($here->isEmpty()) {
            return [null, 'wrong_branch'];
        }
        $withClasses = $here->filter(fn (Membership $m) => ($m->plan_snapshot['class_credits'] ?? null) !== 0);
        if ($withClasses->isEmpty()) {
            return [null, 'classes_not_in_plan'];
        }

        // Unlimited plans first, then the one with credits left.
        $pick = $withClasses->first(fn (Membership $m) => ($m->plan_snapshot['class_credits'] ?? null) === null)
            ?? $withClasses->first(fn (Membership $m) => $this->creditsLeft($m, $session) > 0);

        return $pick ? [$pick, null] : [null, 'no_class_credits'];
    }

    /** Credits left on a membership; null when unlimited. Late cancels still use a credit. */
    public function creditsLeft(Membership $membership, ?ClassSession $except = null): ?int
    {
        $total = $membership->plan_snapshot['class_credits'] ?? null;
        if ($total === null) {
            return null;
        }
        $used = ClassBooking::where('membership_id', $membership->id)
            ->when($except, fn ($q) => $q->where('class_session_id', '!=', $except->id))
            ->where(fn ($q) => $q->whereIn('status', ClassBooking::USES_CREDIT)->orWhere(fn ($q) => $q->where('status', 'cancelled')->where('late_cancel', true)))
            ->count();

        return max(0, $total - $used);
    }

    public function book(ClassSession $session, Member $member, ?User $by, bool $byStaff = false): ClassBooking
    {
        $booking = DB::transaction(function () use ($session, $member, $by, $byStaff) {
            $session = ClassSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $existing = ClassBooking::where('class_session_id', $session->id)->where('member_id', $member->id)->first();
            if ($existing && $existing->status !== 'cancelled') {
                throw ValidationException::withMessages(['member' => __('classes.already_booked')]);
            }

            [$membership, $reason] = $this->eligibility($member, $session, $byStaff);
            if (! $membership) {
                throw ValidationException::withMessages(['member' => __("classes.reason.{$reason}")]);
            }

            $full = $session->booked_count >= $session->capacity;
            $values = [
                'organization_id' => $session->organization_id,
                'membership_id' => $membership->id,
                'status' => $full ? 'waitlisted' : 'booked',
                'waitlist_position' => $full ? $session->waitlist_count + 1 : null,
                'booked_by' => $by?->id,
                'booked_at' => now(),
                'cancelled_at' => null,
                'late_cancel' => false,
            ];
            if ($existing) {
                $existing->forceFill($values)->save();
                $booking = $existing;
            } else {
                $booking = ClassBooking::create(['class_session_id' => $session->id, 'member_id' => $member->id, ...$values]);
            }

            $session->increment($full ? 'waitlist_count' : 'booked_count');

            return $booking;
        });

        Audit::record('class.booked', $booking, ['session' => $session->ulid, 'status' => $booking->status], $by);

        return $booking->fresh();
    }

    /** Members can cancel until the class starts; after the cutoff the credit is still used. */
    public function cancel(ClassBooking $booking, ?User $by, bool $byStaff = false): ClassBooking
    {
        $promoted = DB::transaction(function () use ($booking, $byStaff) {
            $session = ClassSession::whereKey($booking->class_session_id)->lockForUpdate()->firstOrFail();
            $booking = ClassBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if (! in_array($booking->status, ['booked', 'waitlisted'], true)) {
                throw ValidationException::withMessages(['booking' => __('classes.not_cancellable')]);
            }
            if (! $byStaff && $session->starts_at->isPast()) {
                throw ValidationException::withMessages(['booking' => __('classes.reason.session_started')]);
            }

            $wasBooked = $booking->status === 'booked';
            $late = $wasBooked && ! $byStaff && now()->gt($session->starts_at->copy()->subHours($this->cutoffHours($session)));
            $booking->forceFill(['status' => 'cancelled', 'waitlist_position' => null, 'cancelled_at' => now(), 'late_cancel' => $late])->save();

            if ($wasBooked) {
                $session->decrement('booked_count');

                return $session->starts_at->isFuture() ? $this->promote($session->refresh()) : null;
            }
            $session->decrement('waitlist_count');
            $this->renumber($session);

            return null;
        });

        Audit::record('class.cancelled', $booking, ['by_staff' => $byStaff], $by);
        if ($promoted) {
            $this->notify($promoted, 'promoted');
        }

        return $booking->fresh();
    }

    public function markAttendance(ClassBooking $booking, string $status, User $by): ClassBooking
    {
        if (! in_array($booking->status, ['booked', 'attended', 'no_show'], true)) {
            throw ValidationException::withMessages(['status' => __('classes.not_on_roster')]);
        }
        $session = $booking->session;
        if ($session->starts_at->copy()->subMinutes(30)->isFuture()) {
            throw ValidationException::withMessages(['status' => __('classes.attendance_too_early')]);
        }
        $booking->forceFill(['status' => $status])->save();
        Audit::record('class.attendance', $booking, ['status' => $status], $by);

        return $booking;
    }

    /** Calls the class off: everyone is cancelled (credits come back) and told. */
    public function cancelSession(ClassSession $session, string $reason, User $by): ClassSession
    {
        $affected = DB::transaction(function () use ($session, $reason) {
            $session = ClassSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($session->status === 'cancelled') {
                return collect();
            }
            $affected = $session->bookings()->whereIn('status', ['booked', 'waitlisted'])->with('member.user')->get();
            $session->bookings()->whereIn('status', ['booked', 'waitlisted'])
                ->update(['status' => 'cancelled', 'waitlist_position' => null, 'cancelled_at' => now(), 'late_cancel' => false]);
            $session->forceFill(['status' => 'cancelled', 'cancel_reason' => $reason, 'booked_count' => 0, 'waitlist_count' => 0])->save();

            return $affected;
        });

        Audit::record('class.session_cancelled', $session, ['reason' => $reason, 'bookings' => $affected->count()], $by);
        foreach ($affected as $booking) {
            $this->notify($booking, 'session_cancelled');
        }

        return $session->refresh();
    }

    /** The first person on the waitlist who can still come takes the free place. */
    private function promote(ClassSession $session): ?ClassBooking
    {
        $waiting = ClassBooking::where('class_session_id', $session->id)->where('status', 'waitlisted')
            ->orderBy('waitlist_position')->with('member')->get();

        foreach ($waiting as $booking) {
            [$membership] = $this->eligibility($booking->member, $session);
            if (! $membership) {
                continue;
            }
            $booking->forceFill(['status' => 'booked', 'waitlist_position' => null, 'membership_id' => $membership->id])->save();
            $session->increment('booked_count');
            $session->decrement('waitlist_count');
            $this->renumber($session);

            return $booking;
        }

        return null;
    }

    private function renumber(ClassSession $session): void
    {
        ClassBooking::where('class_session_id', $session->id)->where('status', 'waitlisted')->orderBy('waitlist_position')->get()
            ->each(fn (ClassBooking $b, int $i) => $b->forceFill(['waitlist_position' => $i + 1])->save());
    }

    private function notify(ClassBooking $booking, string $kind): void
    {
        $user = $booking->member?->user;
        if ($user && $user->telegramChatId()) {
            $user->notify(new ClassBookingNotification($booking->load('session.classType', 'session.branch.organization'), $kind));
        }
    }

    private function tz(ClassSession $session): string
    {
        return $session->branch->organization->timezone ?: config('gym.timezone');
    }

    public function bookingDays(ClassSession $session): int
    {
        return (int) $this->settings->get('classes.booking_days', $session->branch->organization, $session->branch);
    }

    private function cutoffHours(ClassSession $session): int
    {
        $session->loadMissing('branch.organization');

        return (int) $this->settings->get('classes.cancel_cutoff_hours', $session->branch->organization, $session->branch);
    }
}
