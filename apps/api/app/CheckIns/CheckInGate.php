<?php

namespace App\CheckIns;

use App\Events\CheckInChanged;
use App\Models\Branch;
use App\Models\CheckIn;
use App\Models\Member;
use App\Models\Membership;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Decides whether a member may come in, and records the attempt. Every scan
 * of a known member leaves a check_ins row, allowed or denied, so the desk
 * and the owner can see who was turned away and why.
 */
class CheckInGate
{
    /** Denials the desk may let in anyway, with a reason. */
    public const OVERRIDABLE = [
        'branch_closed', 'membership_frozen', 'payment_pending', 'wrong_branch',
        'no_visits_left', 'not_started', 'membership_expired', 'no_membership',
    ];

    public function __construct(private MemberCodes $codes, private BranchHours $hours) {}

    /**
     * A scanned QR code (phone or card). Unknown or forged codes return an
     * outcome without a member and are not stored.
     */
    public function scan(Branch $branch, string $code, string $method, ?User $by): CheckInOutcome
    {
        $parsed = $this->codes->parse($code);
        $member = $parsed ? Member::where('ulid', $parsed['ulid'])->first() : null;

        if (! $parsed || ! $member) {
            return CheckInOutcome::rejected('invalid_code');
        }

        // A forged signature names no one; a genuine but stale code shows who
        // it belongs to, so the desk can ask them to refresh or use the new card.
        $verdict = $this->codes->verify($member, $parsed);
        if ($verdict === 'invalid') {
            return CheckInOutcome::rejected('invalid_code');
        }
        if ($verdict !== 'valid') {
            return CheckInOutcome::rejected($verdict === 'expired' ? 'expired_code' : 'card_replaced', $member);
        }

        $method = $method === 'kiosk' ? 'kiosk' : ($parsed['kind'] === 'card' ? 'card' : 'qr');

        return $this->admit($branch, $member, $method, $by);
    }

    /** The desk found the member by name or number and checks them in. */
    public function admit(Branch $branch, Member $member, string $method, ?User $by): CheckInOutcome
    {
        $now = now();
        $today = $branch->organization->today();

        // An open visit already exists: a double scan, not a new visit.
        $open = $this->openVisit($branch, $member, $today);
        if ($open) {
            return CheckInOutcome::repeat($open->load('member.homeBranch', 'membership', 'branch', 'locker'));
        }

        $checkIn = DB::transaction(function () use ($branch, $member, $method, $by, $now, $today) {
            $memberships = Membership::where('member_id', $member->id)
                ->whereIn('status', ['active', 'frozen', 'pending_payment', 'expired'])
                ->orderBy('starts_on')
                ->lockForUpdate()
                ->get();

            [$reason, $membership] = $member->status === 'banned'
                ? ['member_banned', null]
                : $this->decide($branch, $member, $memberships, $today);

            if ($reason === null && ! $this->hours->isOpen($branch, $now)) {
                $reason = 'branch_closed';
            }

            $visitUsed = false;
            if ($reason === null && $membership && $membership->visits_remaining !== null && ! $this->visitUsedToday($membership, $today)) {
                $membership->decrement('visits_remaining');
                $visitUsed = true;
            }

            return CheckIn::create([
                'organization_id' => $branch->organization_id,
                'branch_id' => $branch->id,
                'member_id' => $member->id,
                'membership_id' => $membership?->id,
                'method' => $method,
                'result' => $reason === null ? 'allowed' : 'denied',
                'denial_reason' => $reason,
                'visit_used' => $visitUsed,
                'checked_in_at' => $now,
                'handled_by' => $by?->id,
            ]);
        });

        $checkIn->load('member.homeBranch', 'membership', 'branch', 'locker');
        CheckInChanged::dispatch($checkIn, 'scanned');

        return CheckInOutcome::recorded($checkIn);
    }

    /** Lets a denied member in anyway. The reason is kept and audited. */
    public function override(CheckIn $checkIn, string $reason, User $by): CheckIn
    {
        $checkIn->forceFill(['result' => 'overridden', 'override_reason' => $reason, 'handled_by' => $by->id])->save();
        Audit::record('check_in.overridden', $checkIn, ['member_id' => $checkIn->member_id, 'denial' => $checkIn->denial_reason, 'reason' => $reason], $by);

        $checkIn->load('member.homeBranch', 'membership', 'branch', 'locker');
        CheckInChanged::dispatch($checkIn, 'overridden');

        return $checkIn;
    }

    /** Checking out also frees the locker (it stays on the visit as history). */
    public function checkOut(CheckIn $checkIn): CheckIn
    {
        if ($checkIn->checked_out_at === null) {
            $checkIn->forceFill(['checked_out_at' => now()])->save();
            CheckInChanged::dispatch($checkIn->load('member.homeBranch', 'membership', 'branch', 'locker'), 'checked_out');
        }

        return $checkIn;
    }

    /**
     * Picks the membership that lets the member in here today, or the reason
     * none does. Reasons are ranked so the desk sees the most useful one.
     *
     * @param  Collection<int, Membership>  $memberships
     * @return array{0: ?string, 1: ?Membership}
     */
    private function decide(Branch $branch, Member $member, Collection $memberships, Carbon $today): array
    {
        $current = $memberships->filter(fn (Membership $m) => $m->starts_on->lte($today) && ($m->ends_on === null || $m->ends_on->gte($today)));

        foreach ($current->where('status', 'active') as $membership) {
            $hasVisit = $membership->visits_remaining === null || $membership->visits_remaining > 0 || $this->visitUsedToday($membership, $today);
            if ($hasVisit && $this->validAt($membership, $member, $branch)) {
                return [null, $membership];
            }
        }

        $reasons = [
            'membership_frozen' => $current->firstWhere('status', 'frozen'),
            'payment_pending' => $memberships->firstWhere('status', 'pending_payment'),
            'wrong_branch' => $current->where('status', 'active')->first(fn (Membership $m) => ! $this->validAt($m, $member, $branch)),
            'no_visits_left' => $current->where('status', 'active')->first(fn (Membership $m) => $m->visits_remaining === 0),
            'not_started' => $memberships->where('status', 'active')->first(fn (Membership $m) => $m->starts_on->gt($today)),
            'membership_expired' => $memberships->last(),
        ];

        foreach ($reasons as $reason => $membership) {
            if ($membership) {
                return [$reason, $membership];
            }
        }

        return ['no_membership', null];
    }

    /** The plan's branch access, as sold (from the snapshot). */
    private function validAt(Membership $membership, Member $member, Branch $branch): bool
    {
        $snapshot = $membership->plan_snapshot;

        return match ($snapshot['access_scope'] ?? 'home_branch') {
            'all_branches' => true,
            'selected' => in_array($branch->id, $snapshot['branch_ids'] ?? [], true),
            default => $member->home_branch_id === $branch->id,
        };
    }

    /** A visit pass counts one visit per day, however often the member comes and goes. */
    private function visitUsedToday(Membership $membership, Carbon $today): bool
    {
        return CheckIn::where('membership_id', $membership->id)
            ->where('visit_used', true)
            ->where('checked_in_at', '>=', $this->startOfLocalDay($membership, $today))
            ->exists();
    }

    private function openVisit(Branch $branch, Member $member, Carbon $today): ?CheckIn
    {
        return CheckIn::where('branch_id', $branch->id)
            ->where('member_id', $member->id)
            ->inside()
            ->where('checked_in_at', '>=', $this->localMidnight($branch, $today))
            ->latest('checked_in_at')
            ->first();
    }

    private function startOfLocalDay(Membership $membership, Carbon $today): Carbon
    {
        return $this->localMidnight($membership->branch, $today);
    }

    /** Midnight of the gym's day, as a UTC moment for timestamp comparisons. */
    private function localMidnight(Branch $branch, Carbon $today): Carbon
    {
        return Carbon::parse($today->toDateString(), $branch->organization->timezone ?: config('gym.timezone'))->utc();
    }
}
