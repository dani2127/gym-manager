<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Billing\Ledger;
use App\Billing\Pricing;
use App\Billing\Quote;
use App\Billing\QuoteLine;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Branch;
use App\Models\DiscountRule;
use App\Models\Fee;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Plan;
use App\Support\Tenancy\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The desk sale: quote (a live preview, nothing saved) and store (issues the
 * invoice, records the payment and activates the membership once paid).
 */
class SaleController extends Controller
{
    public function __construct(private Pricing $pricing, private Tenant $tenant) {}

    public function quote(Request $request, Branch $branch): JsonResponse
    {
        abort_unless($request->user()->handlesMoneyAt($branch), 403);

        [$member, $quote] = $this->build($request, $branch, forSale: false);
        $today = $this->tenant->organization()->today();

        return response()->json([
            ...$this->present($quote),
            'suggested_start' => $this->pricing->suggestedStart($member, $today)->toDateString(),
            'suggested_fees' => $this->pricing->suggestedFees($member)->pluck('ulid'),
            'available_discounts' => $this->pricing->discountsFor($member, $quote->plan, $today)
                ->map(fn (DiscountRule $rule) => $this->presentDiscount($rule, $quote->plan))
                ->values(),
        ]);
    }

    public function store(Request $request, Branch $branch, Ledger $ledger): JsonResponse
    {
        abort_unless($request->user()->handlesMoneyAt($branch), 403);

        $payment = $request->validate([
            'payment' => ['nullable', 'array'],
            'payment.method' => ['required_with:payment', Rule::in(Payment::DESK_METHODS)],
            'payment.amount_cents' => ['required_with:payment', 'integer', 'min:0'],
            'payment.reference' => ['nullable', Rule::requiredIf(in_array($request->input('payment.method'), Payment::REFERENCE_REQUIRED, true)), 'string', 'max:64'],
        ])['payment'] ?? null;

        [$member, $quote] = $this->build($request, $branch, forSale: true);

        if ($payment && $payment['amount_cents'] > $quote->totalCents()) {
            throw ValidationException::withMessages(['payment.amount_cents' => __('billing.overpayment', ['balance' => number_format($quote->totalCents() / 100, 2)])]);
        }

        // Buying a membership brings a lapsed member back.
        if ($member->status === 'inactive' && $quote->plan) {
            $member->update(['status' => 'active']);
        }

        $invoice = $ledger->sell($quote, $branch, $member, $request->user(), $payment);

        return (new InvoiceResource($invoice->load('branch', 'member', 'creator', 'items.discountRule', 'payments.receiver', 'memberships')))
            ->response()
            ->setStatusCode(201);
    }

    /** @return array{0: Member, 1: Quote} */
    private function build(Request $request, Branch $branch, bool $forSale): array
    {
        $today = $this->tenant->organization()->today();

        $data = $request->validate([
            'member' => ['required', 'string', 'max:26'],
            'plan' => ['nullable', 'string', 'max:26'],
            'fees' => ['sometimes', 'array', 'max:10'],
            'fees.*' => ['string', 'max:26', 'distinct'],
            'discount' => ['nullable', 'string', 'max:26'],
            'promo_code' => ['nullable', 'string', 'max:40', 'prohibits:discount'],
            'discount_proof_checked' => ['sometimes', 'boolean'],
            'starts_on' => ['nullable', 'date', 'after_or_equal:'.$today->toDateString(), 'before:'.$today->copy()->addDays(91)->toDateString()],
        ]);

        $member = Member::where('ulid', $data['member'])->firstOrFail();
        if ($member->status === 'banned') {
            throw ValidationException::withMessages(['member' => __('billing.member_banned')]);
        }

        $plan = null;
        if (! empty($data['plan'])) {
            $plan = Plan::where('ulid', $data['plan'])->first();
            if (! $plan || ! $plan->is_active) {
                throw ValidationException::withMessages(['plan' => __('billing.plan_unavailable')]);
            }
        }

        $fees = Fee::whereIn('ulid', $data['fees'] ?? [])->where('is_active', true)->get();
        if ($fees->count() !== count($data['fees'] ?? [])) {
            throw ValidationException::withMessages(['fees' => __('billing.fee_unavailable')]);
        }

        if ($forSale && ! $plan && $fees->isEmpty()) {
            throw ValidationException::withMessages(['plan' => __('billing.nothing_to_sell')]);
        }

        $discount = $this->resolveDiscount($data, $member, $plan, $today, $forSale);
        $startsOn = $plan ? (isset($data['starts_on']) ? Carbon::parse($data['starts_on'])->startOfDay() : $this->pricing->suggestedStart($member, $today)) : null;

        return [$member, $this->pricing->quote($branch, $plan, $fees, $discount, $startsOn)];
    }

    /** @param  array<string, mixed>  $data */
    private function resolveDiscount(array $data, Member $member, ?Plan $plan, Carbon $today, bool $forSale): ?DiscountRule
    {
        if (! empty($data['promo_code'])) {
            return $this->pricing->promo($data['promo_code'], $plan, $today)
                ?? throw ValidationException::withMessages(['promo_code' => __('billing.promo_invalid')]);
        }

        if (empty($data['discount'])) {
            return null;
        }

        $rule = $this->pricing->discountsFor($member, $plan, $today)->firstWhere('ulid', $data['discount'])
            ?? throw ValidationException::withMessages(['discount' => __('billing.discount_not_eligible')]);

        // The preview shows the price straight away; only the sale needs the ID checked.
        if ($forSale && $rule->requires_proof && empty($data['discount_proof_checked'])) {
            throw ValidationException::withMessages(['discount_proof_checked' => __('billing.proof_required')]);
        }

        return $rule;
    }

    /** @return array<string, mixed> */
    private function present(Quote $quote): array
    {
        return [
            'lines' => array_map(fn (QuoteLine $line) => [
                'kind' => $line->kind,
                'id' => $line->itemable->getAttribute('ulid'),
                'description' => $line->itemable->translate('name'),
                'unit_price_cents' => $line->unitCents,
                'discount_cents' => $line->discountCents,
                'tax_cents' => $line->taxCents,
                'total_cents' => $line->totalCents,
            ], $quote->lines),
            'subtotal_cents' => $quote->subtotalCents(),
            'discount_cents' => $quote->discountCents(),
            'tax_cents' => $quote->taxCents(),
            'net_cents' => $quote->totalCents() - $quote->taxCents(),
            'total_cents' => $quote->totalCents(),
            'tax' => [
                'enabled' => $quote->tax->enabled,
                'name' => $quote->tax->name,
                'rate' => $quote->tax->ratePercent(),
                'inclusive' => $quote->tax->inclusive,
            ],
            'discount' => $quote->discount ? $this->presentDiscount($quote->discount, $quote->plan) : null,
            'starts_on' => $quote->startsOn?->toDateString(),
            'ends_on' => $quote->endsOn?->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentDiscount(DiscountRule $rule, ?Plan $plan): array
    {
        return [
            'id' => $rule->ulid,
            'name' => $rule->translate('name'),
            'kind' => $rule->kind,
            'value_type' => $rule->value_type,
            'value' => $rule->value,
            'amount_cents' => $plan ? $rule->amountOn($plan->price_cents) : 0,
            'requires_proof' => $rule->requires_proof,
            'automatic' => in_array($rule->kind, DiscountRule::GROUP_KINDS, true),
        ];
    }
}
