<?php

namespace App\Http\Controllers\Api\V1\Training;

use App\Billing\Ledger;
use App\Billing\Pricing;
use App\Billing\Quote;
use App\Http\Controllers\Api\V1\Classes\ClassScheduleController;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Payment;
use App\Models\PtPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Selling a personal-training package at the desk: an invoice like any sale. */
class PtSaleController extends Controller
{
    public function quote(Request $request, Branch $branch, Pricing $pricing): JsonResponse
    {
        abort_unless($request->user()->handlesMoneyAt($branch), 403);
        [, $quote] = $this->build($request, $branch, $pricing);

        return response()->json(['data' => [
            'subtotal_cents' => $quote->subtotalCents(),
            'tax_cents' => $quote->taxCents(),
            'total_cents' => $quote->totalCents(),
            'tax' => $quote->tax->enabled ? ['name' => $quote->tax->name, 'rate' => $quote->tax->ratePercent(), 'inclusive' => $quote->tax->inclusive] : null,
        ]]);
    }

    public function store(Request $request, Branch $branch, Pricing $pricing, Ledger $ledger): JsonResponse
    {
        abort_unless($request->user()->handlesMoneyAt($branch), 403);
        $payment = $request->validate([
            'payment' => ['nullable', 'array'],
            'payment.method' => ['required_with:payment', Rule::in(Payment::DESK_METHODS)],
            'payment.amount_cents' => ['required_with:payment', 'integer', 'min:0'],
            'payment.reference' => ['nullable', Rule::requiredIf(in_array($request->input('payment.method'), Payment::REFERENCE_REQUIRED, true)), 'string', 'max:64'],
        ])['payment'] ?? null;

        [$member, $quote] = $this->build($request, $branch, $pricing);
        if ($payment && $payment['amount_cents'] > $quote->totalCents()) {
            throw ValidationException::withMessages(['payment.amount_cents' => __('billing.overpayment', ['balance' => number_format($quote->totalCents() / 100, 2)])]);
        }

        $invoice = $ledger->sell($quote, $branch, $member, $request->user(), $payment);

        return (new InvoiceResource($invoice->load('branch', 'member', 'creator', 'items.discountRule', 'payments.receiver', 'memberships')))->response()->setStatusCode(201);
    }

    /** @return array{0: Member, 1: Quote} */
    private function build(Request $request, Branch $branch, Pricing $pricing): array
    {
        $data = $request->validate([
            'member' => ['required', 'string', 'max:26'],
            'package' => ['required', 'string', 'max:26'],
            'trainer' => ['nullable', 'string', 'max:26'],
        ]);
        $member = Member::where('ulid', $data['member'])->firstOrFail();
        if ($member->status === 'banned') {
            throw ValidationException::withMessages(['member' => __('billing.member_banned')]);
        }
        $package = PtPackage::with('trainer')->where('ulid', $data['package'])->where('is_active', true)->first()
            ?? throw ValidationException::withMessages(['package' => __('billing.plan_unavailable')]);

        if ($package->trainer && ($data['trainer'] ?? $package->trainer->ulid) !== $package->trainer->ulid) {
            throw ValidationException::withMessages(['trainer' => __('training.package_trainer', ['name' => $package->trainer->name])]);
        }
        $trainerUlid = $package->trainer?->ulid ?? $data['trainer'] ?? throw ValidationException::withMessages(['trainer' => __('training.trainer_required')]);
        $trainer = ClassScheduleController::trainerAt($branch, $trainerUlid);

        return [$member, $pricing->quotePt($branch, $package, $trainer)];
    }
}
