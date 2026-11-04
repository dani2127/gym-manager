<?php

namespace App\Http\Controllers\Api\V1\MemberApp;

use App\Billing\Ledger;
use App\Billing\Pricing;
use App\Billing\Quote;
use App\Billing\QuoteLine;
use App\Billing\TransferProofs;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PlanResource;
use App\Models\DiscountRule;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Plan;
use App\Support\Tenancy\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Renewing from the app: pick a plan, see the price (with the member's group
 * discount, any promo code, registration fee and tax), place the order, and
 * send a Telebirr or bank transfer for the desk to check.
 */
class MemberShopController extends Controller
{
    public function __construct(private Pricing $pricing, private Tenant $tenant) {}

    private function member(Request $request): Member
    {
        return $request->attributes->get('member');
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => [
            'plans' => PlanResource::collection(Plan::where('is_active', true)->with('branches')->ordered()->get()),
            'accounts' => PaymentAccount::where('is_active', true)->orderBy('sort')->orderBy('id')->get()->map->present(),
            'open_order' => $this->openOrder($this->member($request))?->ulid,
        ]]);
    }

    public function quote(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->present($this->build($request))]);
    }

    public function order(Request $request, Ledger $ledger): JsonResponse
    {
        $member = $this->member($request);
        if ($open = $this->openOrder($member)) {
            throw ValidationException::withMessages(['plan' => __('billing.open_order_exists', ['number' => $open->number])]);
        }

        $invoice = $ledger->sell($this->build($request), $member->homeBranch, $member, $request->user(), null);

        return (new InvoiceResource($invoice->load('branch', 'items.discountRule', 'payments', 'memberships')))->response()->setStatusCode(201);
    }

    public function cancel(Request $request, Invoice $invoice, Ledger $ledger): JsonResponse
    {
        abort_unless($invoice->member_id === $this->member($request)->id, 404);
        if ($invoice->status !== 'issued' || $invoice->payments()->whereIn('status', ['completed', 'awaiting_verification'])->exists()) {
            throw ValidationException::withMessages(['invoice' => __('billing.order_not_cancellable')]);
        }

        $ledger->void($invoice, 'Cancelled by the member in the app', $request->user());

        return response()->json(status: 204);
    }

    public function transfer(Request $request, Invoice $invoice, Ledger $ledger, TransferProofs $proofs): JsonResponse
    {
        abort_unless($invoice->member_id === $this->member($request)->id, 404);

        $data = $request->validate([
            'method' => ['required', Rule::in(Payment::TRANSFER_METHODS)],
            'reference' => ['required', 'string', 'min:6', 'max:40', 'regex:/^[A-Za-z0-9 \-]+$/'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'proof' => ['required', 'file', 'image', 'max:8192'],
        ], ['reference.required' => __('billing.reference_required')]);

        $path = $proofs->store($invoice->organization_id, $data['proof']);
        $ledger->submitTransfer($invoice, $data['method'], $data['amount_cents'], $data['reference'], $path, $request->user());

        return response()->json(['data' => new InvoiceResource($invoice->refresh()->load('branch', 'items.discountRule', 'payments', 'memberships'))], 201);
    }

    /** An unpaid order the member placed in the app and hasn't paid or cancelled. */
    private function openOrder(Member $member): ?Invoice
    {
        return Invoice::where('member_id', $member->id)
            ->where('created_by', $member->user_id)
            ->whereIn('status', ['issued', 'partially_paid'])
            ->latest('id')
            ->first();
    }

    private function build(Request $request): Quote
    {
        $member = $this->member($request);
        $data = $request->validate([
            'plan' => ['required', 'string', 'max:26'],
            'promo_code' => ['nullable', 'string', 'max:40'],
        ]);
        $today = $this->tenant->organization()->today();

        $plan = Plan::where('ulid', $data['plan'])->where('is_active', true)->first()
            ?? throw ValidationException::withMessages(['plan' => __('billing.plan_unavailable')]);

        // Online: the best group discount applies by itself, or a promo code.
        // Student discounts need an ID check, so they stay at the desk.
        $discount = ! empty($data['promo_code'])
            ? ($this->pricing->promo($data['promo_code'], $plan, $today) ?? throw ValidationException::withMessages(['promo_code' => __('billing.promo_invalid')]))
            : $this->pricing->discountsFor($member, $plan, $today)->first(fn (DiscountRule $r) => in_array($r->kind, DiscountRule::GROUP_KINDS, true));

        return $this->pricing->quote($member->homeBranch, $plan, $this->pricing->suggestedFees($member), $discount, $this->pricing->suggestedStart($member, $today));
    }

    /** @return array<string, mixed> */
    private function present(Quote $quote): array
    {
        return [
            'lines' => array_map(fn (QuoteLine $l) => [
                'kind' => $l->kind,
                'description' => $l->itemable->translate('name'),
                'unit_price_cents' => $l->unitCents,
                'discount_cents' => $l->discountCents,
                'total_cents' => $l->totalCents,
            ], $quote->lines),
            'discount' => $quote->discount ? ['name' => $quote->discount->translate('name'), 'kind' => $quote->discount->kind] : null,
            'tax' => ['enabled' => $quote->tax->enabled, 'name' => $quote->tax->name, 'rate' => $quote->tax->ratePercent(), 'inclusive' => $quote->tax->inclusive],
            'tax_cents' => $quote->taxCents(),
            'total_cents' => $quote->totalCents(),
            'starts_on' => $quote->startsOn?->toDateString(),
            'ends_on' => $quote->endsOn?->toDateString(),
        ];
    }
}
