<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Billing\Ledger;
use App\Billing\TransferProofs;
use App\Http\Controllers\Controller;
use App\Http\Resources\CheckInResource;
use App\Models\Branch;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The desk's queue of Telebirr and bank transfers sent from the member app:
 * look at the screenshot, check the money arrived, approve or reject.
 */
class TransferQueueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $branches = $request->user()->moneyBranches()->pluck('id');
        abort_if($branches->isEmpty(), 403);

        $request->validate(['branch' => ['nullable', 'string', 'max:26'], 'status' => ['nullable', 'in:waiting,reviewed']]);
        $branchId = $request->filled('branch') ? Branch::where('ulid', $request->input('branch'))->value('id') : null;
        $reviewed = $request->input('status') === 'reviewed';

        $payments = Payment::query()
            ->whereIn('branch_id', $branches)
            ->whereIn('method', Payment::TRANSFER_METHODS)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($reviewed,
                fn ($q) => $q->whereIn('status', ['completed', 'rejected'])->whereNotNull('proof_path')->latest('verified_at')->limit(20),
                fn ($q) => $q->where('status', 'awaiting_verification')->oldest())
            ->with('member.homeBranch', 'invoice', 'branch', 'verifier')
            ->get();

        return response()->json([
            'data' => $payments->map(fn (Payment $p) => $this->present($p)),
            'meta' => ['waiting' => Payment::whereIn('branch_id', $branches)->where('status', 'awaiting_verification')->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count()],
        ]);
    }

    public function proof(Request $request, Payment $payment, TransferProofs $proofs): BinaryFileResponse
    {
        abort_unless($request->user()->handlesMoneyAt($payment->branch), 403);
        $path = $proofs->path($payment);
        abort_unless($path, 404);

        return response()->file($path, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=3600']);
    }

    public function approve(Request $request, Payment $payment, Ledger $ledger): JsonResponse
    {
        abort_unless($request->user()->handlesMoneyAt($payment->branch), 403);
        $data = $request->validate(['amount_cents' => ['nullable', 'integer', 'min:1']]);

        $payment = $ledger->approveTransfer($payment, $request->user(), $data['amount_cents'] ?? null);

        return response()->json(['data' => $this->present($payment->load('member.homeBranch', 'invoice', 'branch', 'verifier'))]);
    }

    public function reject(Request $request, Payment $payment, Ledger $ledger): JsonResponse
    {
        abort_unless($request->user()->handlesMoneyAt($payment->branch), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        $payment = $ledger->rejectTransfer($payment, $data['reason'], $request->user());

        return response()->json(['data' => $this->present($payment->load('member.homeBranch', 'invoice', 'branch', 'verifier'))]);
    }

    /** @return array<string, mixed> */
    private function present(Payment $p): array
    {
        return [
            'id' => $p->ulid,
            'method' => $p->method,
            'amount_cents' => $p->amount_cents,
            'reference' => $p->reference,
            'status' => $p->status,
            'rejection_reason' => $p->rejection_reason,
            'submitted_at' => $p->created_at,
            'verified_at' => $p->verified_at,
            'verified_by' => $p->verifier?->name,
            'proof_url' => $p->proof_path ? "/payments/{$p->ulid}/proof" : null,
            'branch' => ['id' => $p->branch->ulid, 'name' => $p->branch->name],
            'member' => $p->member ? CheckInResource::member($p->member) : null,
            'invoice' => [
                'id' => $p->invoice->ulid,
                'number' => $p->invoice->number,
                'total_cents' => $p->invoice->total_cents,
                'balance_cents' => $p->invoice->balanceCents(),
                'status' => $p->invoice->status,
            ],
        ];
    }
}
