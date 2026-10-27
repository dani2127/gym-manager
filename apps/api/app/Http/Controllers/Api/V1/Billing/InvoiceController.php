<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Billing\Ledger;
use App\Billing\ReceiptPdf;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'branch' => ['nullable', 'string', 'max:26'],
            'member' => ['nullable', 'string', 'max:26'],
            'status' => ['nullable', Rule::in(['issued', 'partially_paid', 'paid', 'void', 'unpaid'])],
            'date' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:40'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $branches = $request->user()->moneyBranches()->pluck('id');
        abort_if($branches->isEmpty(), 403);

        $invoices = Invoice::query()
            ->with('branch', 'member')
            ->whereIn('branch_id', $branches)
            ->when($filters['branch'] ?? null, fn ($q, $ulid) => $q->where('branch_id', Branch::where('ulid', $ulid)->value('id')))
            ->when($filters['member'] ?? null, fn ($q, $ulid) => $q->where('member_id', Member::withTrashed()->where('ulid', $ulid)->value('id')))
            ->when($filters['status'] ?? null, fn ($q, $status) => $status === 'unpaid'
                ? $q->whereIn('status', ['issued', 'partially_paid'])
                : $q->where('status', $status))
            ->when($filters['date'] ?? null, function ($q, $date) {
                [$from, $to] = $this->dayBounds($date);
                $q->whereBetween('issued_at', [$from, $to]);
            })
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->where('number', 'like', '%'.strtoupper($term).'%'))
            ->latest('issued_at')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 25);

        return InvoiceResource::collection($invoices);
    }

    public function show(Request $request, Invoice $invoice): InvoiceResource
    {
        abort_unless($request->user()->handlesMoneyAt($invoice->branch), 403);

        return new InvoiceResource($invoice->load('branch', 'member', 'creator', 'items.discountRule', 'payments.receiver', 'memberships'));
    }

    public function pay(Request $request, Invoice $invoice, Ledger $ledger): InvoiceResource
    {
        abort_unless($request->user()->handlesMoneyAt($invoice->branch), 403);

        $data = $request->validate([
            'method' => ['required', Rule::in(Payment::DESK_METHODS)],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:64'],
        ]);

        $ledger->recordPayment($invoice, $data['method'], $data['amount_cents'], $data['reference'] ?? null, $request->user());

        return $this->show($request, $invoice->refresh());
    }

    /** Owners and branch managers only: voiding hands money back. */
    public function void(Request $request, Invoice $invoice, Ledger $ledger): InvoiceResource
    {
        abort_unless($request->user()->managesBranch($invoice->branch), 403);

        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);
        $ledger->void($invoice, $data['reason'], $request->user());

        return $this->show($request, $invoice->refresh());
    }

    public function receipt(Request $request, Invoice $invoice, ReceiptPdf $pdf): Response
    {
        abort_unless($request->user()->handlesMoneyAt($invoice->branch), 403);

        $format = $request->validate(['format' => ['nullable', Rule::in(ReceiptPdf::FORMATS)]])['format'] ?? 'a5';
        $invoice->load('branch', 'member', 'creator', 'items.discountRule', 'payments', 'memberships', 'organization');

        return response($pdf->render($invoice, $format), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$invoice->number.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} The day in the gym's timezone, as UTC bounds. */
    private function dayBounds(string $date): array
    {
        $tz = config('gym.timezone');
        $day = Carbon::parse($date, $tz)->startOfDay();

        return [$day->copy()->utc(), $day->copy()->endOfDay()->utc()];
    }
}
