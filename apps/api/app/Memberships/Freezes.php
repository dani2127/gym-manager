<?php

namespace App\Memberships;

use App\Events\FreezeChanged;
use App\Models\Membership;
use App\Models\MembershipFreeze;
use App\Models\User;
use App\Notifications\FreezeReviewedNotification;
use App\Support\Audit;
use App\Support\Settings\SettingsRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pausing a membership. Approving a freeze moves the membership's end date
 * (and any renewal queued after it) out by the frozen days; the nightly run
 * flips the membership to frozen on the first day and back on the day after
 * the last. Ending early or cancelling gives the unused days back.
 */
class Freezes
{
    public function __construct(private SettingsRepository $settings) {}

    public function enabled(Membership $membership): bool
    {
        return (bool) $this->settings->get('freeze.enabled', $membership->organization, $membership->branch);
    }

    /**
     * What the plan allows and what is left of it, for the desk and the app.
     *
     * @return array<string, mixed>
     */
    public function policy(Membership $membership, Carbon $today): array
    {
        $plan = $membership->plan_snapshot ?? [];
        $counted = $membership->freezes->whereIn('status', MembershipFreeze::COUNTED);
        $maxDays = $plan['max_freeze_days'] ?? null;
        $maxFreezes = $plan['max_freezes'] ?? null;
        $daysUsed = (int) $counted->sum('days');
        $open = $membership->freezes->first(fn (MembershipFreeze $f) => in_array($f->status, MembershipFreeze::OPEN, true));
        $state = $membership->stateOn($today);

        return [
            'enabled' => $this->enabled($membership),
            'allowed' => (bool) ($plan['freeze_allowed'] ?? false),
            'max_days' => $maxDays,
            'max_freezes' => $maxFreezes,
            'days_used' => $daysUsed,
            'freezes_used' => $counted->count(),
            'days_left' => $maxDays === null ? null : max(0, $maxDays - $daysUsed),
            'freezes_left' => $maxFreezes === null ? null : max(0, $maxFreezes - $counted->count()),
            // Something the desk could freeze now (limits aside).
            'freezable' => $open === null && in_array($state, ['current', 'upcoming'], true),
        ];
    }

    /**
     * The desk freezes on the spot (approved at once); a member's request
     * waits for the desk. Going past the plan's limits needs $override.
     */
    public function create(Membership $membership, Carbon $startsOn, int $days, ?string $reason, User $by, bool $asRequest = false, bool $override = false): MembershipFreeze
    {
        $freeze = DB::transaction(function () use ($membership, $startsOn, $days, $reason, $by, $asRequest, $override) {
            $membership = Membership::whereKey($membership->id)->lockForUpdate()->firstOrFail();
            $membership->load('freezes', 'organization', 'branch');
            $overLimit = $this->check($membership, $startsOn, $days, $override);

            $freeze = MembershipFreeze::create([
                'organization_id' => $membership->organization_id,
                'membership_id' => $membership->id,
                'branch_id' => $membership->branch_id,
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $startsOn->copy()->addDays($days - 1)->toDateString(),
                'days' => $days,
                'reason' => $reason,
                'status' => $asRequest ? 'requested' : 'approved',
                'over_limit' => $overLimit,
                'requested_by' => $by->id,
                'reviewed_by' => $asRequest ? null : $by->id,
                'reviewed_at' => $asRequest ? null : now(),
            ]);

            if (! $asRequest) {
                $this->shift($membership, $days);
            }

            return $freeze;
        });

        Audit::record($asRequest ? 'freeze.requested' : 'freeze.approved', $freeze, $freeze->only('starts_on', 'ends_on', 'days', 'reason', 'over_limit'), $by);
        $this->activateDue($freeze->membership->organization->today(), $freeze->membership_id);
        FreezeChanged::dispatch($freeze, $asRequest ? 'requested' : 'reviewed');

        return $freeze->refresh();
    }

    public function approve(MembershipFreeze $freeze, User $by, bool $override = false): MembershipFreeze
    {
        DB::transaction(function () use ($freeze, $by, $override) {
            $membership = Membership::whereKey($freeze->membership_id)->lockForUpdate()->firstOrFail();
            $freeze = MembershipFreeze::whereKey($freeze->id)->lockForUpdate()->firstOrFail();
            $this->expectStatus($freeze, ['requested']);

            $membership->load('freezes', 'organization', 'branch');
            $membership->setRelation('freezes', $membership->freezes->reject(fn (MembershipFreeze $f) => $f->id === $freeze->id));
            // A start date that passed while the request waited moves to today.
            $today = $membership->organization->today();
            if ($freeze->starts_on->lt($today)) {
                $freeze->starts_on = $today;
                $freeze->ends_on = $today->copy()->addDays($freeze->days - 1);
            }
            $overLimit = $this->check($membership, $freeze->starts_on, $freeze->days, $override);

            $freeze->forceFill(['status' => 'approved', 'over_limit' => $overLimit, 'reviewed_by' => $by->id, 'reviewed_at' => now()])->save();
            $this->shift($membership, $freeze->days);
        });

        $freeze->refresh();
        Audit::record('freeze.approved', $freeze, $freeze->only('starts_on', 'ends_on', 'days', 'over_limit'), $by);
        $this->activateDue($freeze->membership->organization->today(), $freeze->membership_id);
        FreezeChanged::dispatch($freeze, 'reviewed');
        $this->notifyMember($freeze);

        return $freeze->refresh();
    }

    public function reject(MembershipFreeze $freeze, string $reason, User $by): MembershipFreeze
    {
        $updated = MembershipFreeze::whereKey($freeze->id)->where('status', 'requested')
            ->update(['status' => 'rejected', 'rejection_reason' => $reason, 'reviewed_by' => $by->id, 'reviewed_at' => now()]);
        if (! $updated) {
            throw ValidationException::withMessages(['freeze' => __('memberships.freeze_already_reviewed')]);
        }

        $freeze->refresh();
        Audit::record('freeze.rejected', $freeze, ['reason' => $reason], $by);
        FreezeChanged::dispatch($freeze, 'reviewed');
        $this->notifyMember($freeze);

        return $freeze;
    }

    /** Withdraws a request, or calls off a freeze that hasn't started; the days come back. */
    public function cancel(MembershipFreeze $freeze, User $by): MembershipFreeze
    {
        DB::transaction(function () use ($freeze) {
            $membership = Membership::whereKey($freeze->membership_id)->lockForUpdate()->firstOrFail();
            $freeze = MembershipFreeze::whereKey($freeze->id)->lockForUpdate()->firstOrFail();
            $this->expectStatus($freeze, ['requested', 'approved']);

            if ($freeze->status === 'approved') {
                $this->shift($membership, -$freeze->days);
            }
            $freeze->forceFill(['status' => 'cancelled'])->save();
        });

        $freeze->refresh();
        Audit::record('freeze.cancelled', $freeze, $freeze->only('starts_on', 'days'), $by);
        FreezeChanged::dispatch($freeze, 'reviewed');

        return $freeze;
    }

    /** The member is back early: the freeze stops yesterday and the unused days come off the end date. */
    public function end(MembershipFreeze $freeze, User $by): MembershipFreeze
    {
        DB::transaction(function () use ($freeze) {
            $membership = Membership::whereKey($freeze->membership_id)->lockForUpdate()->firstOrFail();
            $freeze = MembershipFreeze::whereKey($freeze->id)->lockForUpdate()->firstOrFail();
            $this->expectStatus($freeze, ['active']);

            $today = $membership->organization->today();
            $served = (int) $freeze->starts_on->diffInDays($today);
            $unused = $freeze->days - $served;

            if ($served === 0) {
                $freeze->forceFill(['status' => 'cancelled'])->save();
            } else {
                $freeze->forceFill(['status' => 'completed', 'days' => $served, 'ends_on' => $today->copy()->subDay()])->save();
            }
            $membership->forceFill(['status' => 'active'])->save();
            if ($unused > 0) {
                $this->shift($membership, -$unused);
            }
        });

        $freeze->refresh();
        Audit::record('freeze.ended_early', $freeze, $freeze->only('ends_on', 'days'), $by);
        FreezeChanged::dispatch($freeze, 'reviewed');

        return $freeze;
    }

    /**
     * Starts freezes whose first day has come and finishes those whose last
     * day has passed. Nightly for every gym, and right after an approval.
     */
    public function activateDue(Carbon $today, ?int $membershipId = null): int
    {
        $changed = 0;
        $scope = fn ($q) => $membershipId ? $q->where('membership_id', $membershipId) : $q;

        $finished = MembershipFreeze::withoutGlobalScopes()->where('status', 'active')->whereDate('ends_on', '<', $today->toDateString())->tap($scope)->get();
        foreach ($finished as $freeze) {
            DB::transaction(function () use ($freeze) {
                $freeze->forceFill(['status' => 'completed'])->save();
                Membership::withoutGlobalScopes()->whereKey($freeze->membership_id)->where('status', 'frozen')->update(['status' => 'active', 'updated_at' => now()]);
            });
            $changed++;
        }

        $starting = MembershipFreeze::withoutGlobalScopes()->where('status', 'approved')->whereDate('starts_on', '<=', $today->toDateString())->tap($scope)->get();
        foreach ($starting as $freeze) {
            DB::transaction(function () use ($freeze) {
                $freeze->forceFill(['status' => 'active'])->save();
                Membership::withoutGlobalScopes()->whereKey($freeze->membership_id)->where('status', 'active')->update(['status' => 'frozen', 'updated_at' => now()]);
            });
            $changed++;
        }

        return $changed;
    }

    /** @return bool whether the freeze goes past the plan's limits (only allowed with $override) */
    private function check(Membership $membership, Carbon $startsOn, int $days, bool $override): bool
    {
        $fail = fn (string $field, string $key, array $replace = []) => throw ValidationException::withMessages([$field => __("memberships.{$key}", $replace)]);
        $today = $membership->organization->today();

        if (! $this->enabled($membership)) {
            $fail('membership', 'freeze_disabled');
        }
        if ($membership->status !== 'active' || ! in_array($membership->stateOn($today), ['current', 'upcoming'], true)) {
            $fail('membership', 'freeze_not_live');
        }
        if ($membership->freezes->contains(fn (MembershipFreeze $f) => in_array($f->status, MembershipFreeze::OPEN, true))) {
            $fail('membership', 'freeze_open');
        }
        if ($days < 1 || $days > 365) {
            $fail('days', 'freeze_days_range');
        }
        if ($startsOn->lt($today)) {
            $fail('starts_on', 'freeze_start_past');
        }
        if ($membership->ends_on && $startsOn->gt($membership->ends_on)) {
            $fail('starts_on', 'freeze_start_after_end', ['date' => $membership->ends_on->toDateString()]);
        }

        $policy = $this->policy($membership, $today);
        $overLimit = ! $policy['allowed']
            || ($policy['days_left'] !== null && $days > $policy['days_left'])
            || ($policy['freezes_left'] !== null && $policy['freezes_left'] < 1);

        if ($overLimit && ! $override) {
            if (! $policy['allowed']) {
                $fail('membership', 'freeze_not_allowed');
            }
            if ($policy['freezes_left'] !== null && $policy['freezes_left'] < 1) {
                $fail('membership', 'freeze_count_used', ['max' => $policy['max_freezes']]);
            }
            $fail('days', 'freeze_days_over', ['left' => $policy['days_left']]);
        }

        return $overLimit;
    }

    /**
     * Moves the end date by $days, and every membership queued after it, so a
     * renewal never overlaps the frozen one. Visit passes without an end date
     * don't move.
     */
    private function shift(Membership $membership, int $days): void
    {
        if ($membership->ends_on === null || $days === 0) {
            return;
        }

        Membership::where('member_id', $membership->member_id)
            ->whereKeyNot($membership->id)
            ->whereIn('status', ['active', 'pending_payment', 'frozen'])
            ->whereDate('starts_on', '>', $membership->ends_on->toDateString())
            ->get()
            ->each(fn (Membership $later) => $later->forceFill([
                'starts_on' => $later->starts_on->copy()->addDays($days),
                'ends_on' => $later->ends_on?->copy()->addDays($days),
            ])->save());

        $membership->forceFill(['ends_on' => $membership->ends_on->copy()->addDays($days)])->save();
    }

    /** @param  list<string>  $statuses */
    private function expectStatus(MembershipFreeze $freeze, array $statuses): void
    {
        if (! in_array($freeze->status, $statuses, true)) {
            throw ValidationException::withMessages(['freeze' => __('memberships.freeze_already_reviewed')]);
        }
    }

    private function notifyMember(MembershipFreeze $freeze): void
    {
        $user = $freeze->membership->member?->user;
        if ($freeze->requester?->id === $user?->id && $user?->telegramChatId()) {
            $user->notify(new FreezeReviewedNotification($freeze));
        }
    }
}
