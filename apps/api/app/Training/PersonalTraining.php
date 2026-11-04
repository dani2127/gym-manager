<?php

namespace App\Training;

use App\Models\ClassSession;
use App\Models\PtEnrollment;
use App\Models\PtSession;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Personal-training sessions on a paid package. Sessions can't outnumber what
 * is left, must fall inside the package's dates and can't clash with the
 * trainer's other sessions or classes. Done and no-show both use a session.
 */
class PersonalTraining
{
    public function schedule(PtEnrollment $enrollment, Carbon $startsAt, int $minutes, User $by): PtSession
    {
        $session = DB::transaction(function () use ($enrollment, $startsAt, $minutes, $by) {
            $enrollment = PtEnrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            $fail = fn (string $key, array $replace = []) => throw ValidationException::withMessages(['starts_at' => __("training.{$key}", $replace)]);
            $endsAt = $startsAt->copy()->addMinutes($minutes);
            $day = Carbon::parse($startsAt->copy()->setTimezone($this->tz($enrollment))->toDateString());

            if ($enrollment->status !== 'active') {
                $fail('not_active');
            }
            if ($startsAt->isPast()) {
                $fail('in_past');
            }
            if ($day->lt($enrollment->starts_on) || ($enrollment->ends_on && $day->gt($enrollment->ends_on))) {
                $fail('outside_dates', ['from' => $enrollment->starts_on->toDateString(), 'to' => $enrollment->ends_on?->toDateString()]);
            }
            if ($enrollment->sessions_remaining - $enrollment->scheduledCount() < 1) {
                $fail('none_left');
            }
            $overlaps = fn ($q) => $q->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt);
            if (PtSession::where('trainer_id', $enrollment->trainer_id)->where('status', 'scheduled')->where($overlaps)->exists()
                || ClassSession::where('trainer_id', $enrollment->trainer_id)->where('status', 'scheduled')->where($overlaps)->exists()) {
                $fail('trainer_busy');
            }
            if (PtSession::where('member_id', $enrollment->member_id)->where('status', 'scheduled')->where($overlaps)->exists()) {
                $fail('member_busy');
            }

            return PtSession::create([
                'organization_id' => $enrollment->organization_id,
                'pt_enrollment_id' => $enrollment->id,
                'trainer_id' => $enrollment->trainer_id,
                'member_id' => $enrollment->member_id,
                'branch_id' => $enrollment->branch_id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'created_by' => $by->id,
            ]);
        });

        Audit::record('pt.session_scheduled', $session, ['starts_at' => $session->starts_at->toIso8601String()], $by);

        return $session;
    }

    /** completed / no_show use a session; back to scheduled gives it back. */
    public function mark(PtSession $session, string $status, User $by): PtSession
    {
        DB::transaction(function () use ($session, $status) {
            $session = PtSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $enrollment = PtEnrollment::whereKey($session->pt_enrollment_id)->lockForUpdate()->firstOrFail();
            if ($session->status === 'cancelled') {
                throw ValidationException::withMessages(['status' => __('training.cancelled')]);
            }
            if ($session->starts_at->copy()->subMinutes(15)->isFuture()) {
                throw ValidationException::withMessages(['status' => __('training.too_early')]);
            }
            $wasUsed = in_array($session->status, ['completed', 'no_show'], true);
            $nowUsed = in_array($status, ['completed', 'no_show'], true);

            if ($nowUsed && ! $wasUsed) {
                $enrollment->sessions_remaining = max(0, $enrollment->sessions_remaining - 1);
            } elseif ($wasUsed && ! $nowUsed) {
                $enrollment->sessions_remaining = min($enrollment->sessions_total, $enrollment->sessions_remaining + 1);
            }
            if ($enrollment->sessions_remaining === 0 && $enrollment->status === 'active') {
                $enrollment->status = 'completed';
            } elseif ($enrollment->sessions_remaining > 0 && $enrollment->status === 'completed') {
                $enrollment->status = 'active';
            }
            $enrollment->save();
            $session->forceFill(['status' => $status])->save();
        });

        Audit::record('pt.session_marked', $session, ['status' => $status], $by);

        return $session->refresh();
    }

    public function cancel(PtSession $session, User $by): PtSession
    {
        if ($session->status !== 'scheduled') {
            throw ValidationException::withMessages(['status' => __('training.not_scheduled')]);
        }
        $session->forceFill(['status' => 'cancelled'])->save();
        Audit::record('pt.session_cancelled', $session, [], $by);

        return $session;
    }

    /** Nightly: packages past their last day expire; their future sessions are called off. */
    public function expire(Carbon $today): int
    {
        $ids = PtEnrollment::withoutGlobalScopes()->where('status', 'active')->whereDate('ends_on', '<', $today->toDateString())->pluck('id');
        PtSession::withoutGlobalScopes()->whereIn('pt_enrollment_id', $ids)->where('status', 'scheduled')->update(['status' => 'cancelled']);

        return PtEnrollment::withoutGlobalScopes()->whereIn('id', $ids)->update(['status' => 'expired', 'updated_at' => now()]);
    }

    private function tz(PtEnrollment $enrollment): string
    {
        return $enrollment->branch()->withoutGlobalScopes()->first()?->organization?->timezone ?: config('gym.timezone');
    }
}
