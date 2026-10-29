<?php

namespace App\Billing;

use App\Events\PaymentChanged;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipFreeze;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentReviewedNotification;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns quotes into invoices and records money against them. Every write
 * happens in a transaction with the invoice row locked, so two desks can
 * never double-apply a payment.
 */
class Ledger
{
    public function __construct(private Pricing $pricing) {}

    /** @param  array{method: string, amount_cents: int, reference?: ?string}|null  $payment */
    public function sell(Quote $quote, Branch $branch, ?Member $member, User $seller, ?array $payment): Invoice
    {
        $invoice = DB::transaction(function () use ($quote, $branch, $member, $seller) {
            $issuedAt = now();
            $year = (int) $issuedAt->copy()->setTimezone($branch->organization->timezone ?: config('gym.timezone'))->format('Y');
            $number = InvoiceSequence::take($branch, $year);

            $invoice = Invoice::create([
                'organization_id' => $branch->organization_id,
                'branch_id' => $branch->id,
                'member_id' => $member?->id,
                'number' => sprintf('%s-%d-%06d', $branch->code, $year, $number),
                'status' => 'issued',
                'subtotal_cents' => $quote->subtotalCents(),
                'discount_cents' => $quote->discountCents(),
                'tax_cents' => $quote->taxCents(),
                'total_cents' => $quote->totalCents(),
                'tax_name' => $quote->tax->enabled ? $quote->tax->name : null,
                'tax_rate' => $quote->tax->ratePercent(),
                'tax_inclusive' => $quote->tax->inclusive,
                'currency' => $branch->organization->currency ?? 'ETB',
                'issued_at' => $issuedAt,
                'created_by' => $seller->id,
            ]);

            foreach ($quote->lines as $line) {
                $invoice->items()->create([
                    'itemable_type' => $line->itemable->getMorphClass(),
                    'itemable_id' => $line->itemable->getKey(),
                    'description' => $line->description,
                    'qty' => $line->qty,
                    'unit_price_cents' => $line->unitCents,
                    'discount_cents' => $line->discountCents,
                    'discount_rule_id' => $line->discountRule?->id,
                    'tax_cents' => $line->taxCents,
                    'total_cents' => $line->totalCents,
                ]);
            }

            if ($quote->plan && $member) {
                $quote->plan->loadMissing('branches');
                Membership::create([
                    'organization_id' => $branch->organization_id,
                    'branch_id' => $branch->id,
                    'member_id' => $member->id,
                    'plan_id' => $quote->plan->id,
                    'plan_snapshot' => $quote->plan->snapshot(),
                    'starts_on' => $quote->startsOn,
                    'ends_on' => $quote->endsOn,
                    'visits_total' => $quote->plan->hasVisits() ? $quote->plan->visit_count : null,
                    'visits_remaining' => $quote->plan->hasVisits() ? $quote->plan->visit_count : null,
                    'status' => 'pending_payment',
                    'invoice_id' => $invoice->id,
                    'sold_by' => $seller->id,
                ]);
            }

            // A free sale (a 100% discount) is paid the moment it is issued.
            if ($invoice->total_cents === 0) {
                $invoice->update(['status' => 'paid']);
                $this->settle($invoice);
            }

            return $invoice;
        });

        Audit::record('invoice.issued', $invoice, [
            'number' => $invoice->number,
            'total_cents' => $invoice->total_cents,
            'discount' => $quote->discount?->kind,
        ], $seller);

        if ($payment && $payment['amount_cents'] > 0 && $invoice->total_cents > 0) {
            $this->recordPayment($invoice, $payment['method'], $payment['amount_cents'], $payment['reference'] ?? null, $seller);
        }

        return $invoice->refresh();
    }

    public function recordPayment(Invoice $invoice, string $method, int $amountCents, ?string $reference, User $receiver): Payment
    {
        $reference = Payment::normalizeReference($reference);
        $payment = DB::transaction(function () use ($invoice, $method, $amountCents, $reference, $receiver) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, ['void', 'paid'], true)) {
                throw ValidationException::withMessages(['amount_cents' => __('billing.invoice_closed')]);
            }
            if ($amountCents > $locked->balanceCents()) {
                throw ValidationException::withMessages(['amount_cents' => __('billing.overpayment', ['balance' => Money::format($locked->balanceCents())])]);
            }
            $this->guardReference($method, $reference);

            $payment = Payment::create([
                'organization_id' => $locked->organization_id,
                'branch_id' => $locked->branch_id,
                'invoice_id' => $locked->id,
                'member_id' => $locked->member_id,
                'method' => $method,
                'amount_cents' => $amountCents,
                'status' => 'completed',
                'reference' => $reference,
                'received_by' => $receiver->id,
            ]);

            $locked->paid_cents += $amountCents;
            $locked->status = $locked->paid_cents >= $locked->total_cents ? 'paid' : 'partially_paid';
            $locked->save();

            if ($locked->status === 'paid') {
                $this->settle($locked);
            }

            return $payment;
        });

        Audit::record('payment.received', $payment, ['invoice' => $invoice->number, 'method' => $method, 'amount_cents' => $amountCents], $receiver);

        return $payment;
    }

    /**
     * A member sends a Telebirr or bank transfer from the app. The payment
     * waits for the desk to check the screenshot against the gym's account.
     */
    public function submitTransfer(Invoice $invoice, string $method, int $amountCents, string $reference, string $proofPath, User $member): Payment
    {
        $reference = (string) Payment::normalizeReference($reference);

        $payment = DB::transaction(function () use ($invoice, $method, $amountCents, $reference, $proofPath, $member) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, ['void', 'paid'], true)) {
                throw ValidationException::withMessages(['amount_cents' => __('billing.invoice_closed')]);
            }
            if (Payment::where('invoice_id', $locked->id)->where('status', 'awaiting_verification')->exists()) {
                throw ValidationException::withMessages(['reference' => __('billing.transfer_waiting')]);
            }
            if ($amountCents < 1 || $amountCents > $locked->balanceCents()) {
                throw ValidationException::withMessages(['amount_cents' => __('billing.overpayment', ['balance' => Money::format($locked->balanceCents())])]);
            }
            $this->guardReference($method, $reference);

            return Payment::create([
                'organization_id' => $locked->organization_id,
                'branch_id' => $locked->branch_id,
                'invoice_id' => $locked->id,
                'member_id' => $locked->member_id,
                'method' => $method,
                'amount_cents' => $amountCents,
                'status' => 'awaiting_verification',
                'reference' => $reference,
                'proof_path' => $proofPath,
                'received_by' => $member->id,
            ]);
        });

        Audit::record('payment.transfer_submitted', $payment, ['invoice' => $invoice->number, 'method' => $method, 'amount_cents' => $amountCents, 'reference' => $reference], $member);
        PaymentChanged::dispatch($payment, 'submitted');

        return $payment;
    }

    /**
     * The desk saw the money arrive. The amount can be corrected (e.g. the
     * member typed it wrong) but never above what is owed.
     */
    public function approveTransfer(Payment $payment, User $by, ?int $amountCents = null): Payment
    {
        DB::transaction(function () use ($payment, $by, $amountCents) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'awaiting_verification') {
                throw ValidationException::withMessages(['payment' => __('billing.transfer_already_reviewed')]);
            }
            $invoice = Invoice::whereKey($locked->invoice_id)->lockForUpdate()->firstOrFail();
            $amount = $amountCents ?? $locked->amount_cents;
            if ($invoice->status === 'void' || $amount < 1 || $amount > $invoice->balanceCents()) {
                throw ValidationException::withMessages(['amount_cents' => __('billing.overpayment', ['balance' => Money::format($invoice->balanceCents())])]);
            }

            $locked->forceFill(['status' => 'completed', 'amount_cents' => $amount, 'verified_by' => $by->id, 'verified_at' => now()])->save();
            $invoice->paid_cents += $amount;
            $invoice->status = $invoice->paid_cents >= $invoice->total_cents ? 'paid' : 'partially_paid';
            $invoice->save();

            if ($invoice->status === 'paid') {
                $this->settle($invoice);
            }
        });

        $payment->refresh();
        Audit::record('payment.transfer_approved', $payment, ['reference' => $payment->reference, 'amount_cents' => $payment->amount_cents], $by);
        PaymentChanged::dispatch($payment, 'reviewed');
        $this->notifyMember($payment);

        return $payment;
    }

    /** Not received, wrong account or a fake screenshot. The reference stays used. */
    public function rejectTransfer(Payment $payment, string $reason, User $by): Payment
    {
        $updated = Payment::whereKey($payment->id)->where('status', 'awaiting_verification')
            ->update(['status' => 'rejected', 'rejection_reason' => $reason, 'verified_by' => $by->id, 'verified_at' => now()]);
        if (! $updated) {
            throw ValidationException::withMessages(['payment' => __('billing.transfer_already_reviewed')]);
        }

        $payment->refresh();
        Audit::record('payment.transfer_rejected', $payment, ['reference' => $payment->reference, 'reason' => $reason], $by);
        PaymentChanged::dispatch($payment, 'reviewed');
        $this->notifyMember($payment);

        return $payment;
    }

    /** One FT or Telebirr number pays once, whatever its status, so reused screenshots are caught. */
    private function guardReference(string $method, ?string $reference): void
    {
        if ($reference !== null && Payment::where('method', $method)->where('reference', $reference)->exists()) {
            throw ValidationException::withMessages(['reference' => __('billing.reference_used')]);
        }
    }

    private function notifyMember(Payment $payment): void
    {
        $user = $payment->member?->user;
        if ($user && $user->telegramChatId()) {
            $user->notify(new PaymentReviewedNotification($payment->load('invoice')));
        }
    }

    /**
     * Cancels the sale: payments are marked refunded (the cash goes back) and
     * any membership it bought is cancelled. The number stays used, so the
     * sequence never has gaps.
     */
    public function void(Invoice $invoice, string $reason, User $by): void
    {
        DB::transaction(function () use ($invoice, $reason, $by) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'void') {
                return;
            }

            $locked->payments()->where('status', 'completed')->update(['status' => 'refunded']);
            $locked->payments()->where('status', 'awaiting_verification')->update(['status' => 'rejected', 'rejection_reason' => $reason]);
            MembershipFreeze::whereIn('membership_id', $locked->memberships()->pluck('id'))->whereIn('status', MembershipFreeze::OPEN)->update(['status' => 'cancelled']);
            $locked->memberships()->update(['status' => 'cancelled']);
            $locked->forceFill(['status' => 'void', 'voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => $reason])->save();
        });

        Audit::record('invoice.voided', $invoice, ['number' => $invoice->number, 'reason' => $reason, 'paid_cents' => $invoice->paid_cents], $by);
    }

    /**
     * Fully paid: memberships go live. One paid late starts today instead of
     * on its planned day, so the member never loses days they were not
     * allowed in.
     */
    private function settle(Invoice $invoice): void
    {
        $today = $invoice->organization()->first()->today();

        $invoice->memberships()->where('status', 'pending_payment')->get()->each(function (Membership $membership) use ($today) {
            if ($membership->starts_on->lt($today)) {
                $membership->starts_on = $today;
                $membership->ends_on = $this->pricing->endsOn($membership->plan_snapshot['duration_days'] ?? null, $today);
            }
            $membership->status = 'active';
            $membership->save();
        });
    }
}
