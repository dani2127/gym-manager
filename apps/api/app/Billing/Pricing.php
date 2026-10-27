<?php

namespace App\Billing;

use App\Models\Branch;
use App\Models\DiscountRule;
use App\Models\Fee;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Membership;
use App\Models\Plan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Prices a desk sale: the plan (with at most one discount), any fees, and the
 * branch's tax. Pure arithmetic over the catalog; nothing is saved here.
 */
class Pricing
{
    /** @param  Collection<int, Fee>  $fees */
    public function quote(Branch $branch, ?Plan $plan, Collection $fees, ?DiscountRule $discount, ?Carbon $startsOn = null): Quote
    {
        $tax = TaxConfig::forBranch($branch);
        $lines = [];

        if ($plan) {
            $gross = $plan->price_cents;
            $off = $discount?->amountOn($gross) ?? 0;
            $taxed = $tax->apply($gross - $off);
            $lines[] = new QuoteLine('plan', $plan, $plan->name, 1, $gross, $off, $discount, $taxed['tax'], $taxed['total']);
        }

        foreach ($fees as $fee) {
            $taxed = $tax->apply($fee->amount_cents);
            $lines[] = new QuoteLine('fee', $fee, $fee->name, 1, $fee->amount_cents, 0, null, $taxed['tax'], $taxed['total']);
        }

        return new Quote(
            lines: $lines,
            tax: $tax,
            plan: $plan,
            discount: $plan ? $discount : null,
            startsOn: $plan ? $startsOn : null,
            endsOn: $plan && $startsOn ? $this->endsOn($plan->duration_days, $startsOn) : null,
        );
    }

    /**
     * A renewal starts the day after the member's last paid or pending
     * membership ends, so buying early never loses days.
     */
    public function suggestedStart(?Member $member, Carbon $today): Carbon
    {
        $lastEnd = $member?->memberships()->live()->whereNotNull('ends_on')->max('ends_on');

        if ($lastEnd && Carbon::parse($lastEnd)->gte($today)) {
            return Carbon::parse($lastEnd)->addDay()->startOfDay();
        }

        return $today->copy();
    }

    public function endsOn(?int $durationDays, Carbon $startsOn): ?Carbon
    {
        return $durationDays ? $startsOn->copy()->addDays($durationDays - 1) : null;
    }

    /**
     * Discounts this member can get on this plan, biggest saving first.
     * Group discounts follow from the member's group; student discounts are
     * offered to everyone and need the desk to check an ID. Promo codes are
     * matched separately and never listed.
     *
     * @return Collection<int, DiscountRule>
     */
    public function discountsFor(?Member $member, ?Plan $plan, Carbon $day): Collection
    {
        if (! $plan) {
            return collect();
        }

        $group = $member?->groups()->withCount('members')->first();

        return DiscountRule::usableOn($day)
            ->where('kind', '!=', 'promo')
            ->get()
            ->filter(fn (DiscountRule $rule) => $rule->appliesToPlan($plan) && match (true) {
                in_array($rule->kind, DiscountRule::GROUP_KINDS, true) => $group !== null
                    && $group->type === $rule->kind
                    && $group->members_count >= ($rule->min_group_size ?? MemberGroup::SIZES[$rule->kind][0]),
                default => true,
            })
            ->sortByDesc(fn (DiscountRule $rule) => $rule->amountOn($plan->price_cents))
            ->values();
    }

    public function promo(string $code, ?Plan $plan, Carbon $day): ?DiscountRule
    {
        $rule = DiscountRule::usableOn($day)->where('kind', 'promo')->where('promo_code', strtoupper(trim($code)))->first();

        return $rule && $rule->appliesToPlan($plan) ? $rule : null;
    }

    /**
     * Fees the desk should add: the registration fee for someone buying
     * their first membership.
     *
     * @return Collection<int, Fee>
     */
    public function suggestedFees(?Member $member): Collection
    {
        $returning = $member && Membership::where('member_id', $member->id)->where('status', '!=', 'cancelled')->exists();

        return $returning ? collect() : Fee::where('kind', 'registration')->where('is_active', true)->get();
    }
}
