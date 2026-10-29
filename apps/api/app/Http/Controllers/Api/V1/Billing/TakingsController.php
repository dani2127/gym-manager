<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** A branch's money for one day: what the cash drawer and POS should hold. */
class TakingsController extends Controller
{
    public function __invoke(Request $request, Branch $branch): JsonResponse
    {
        abort_unless($request->user()->handlesMoneyAt($branch), 403);

        $tz = $branch->organization->timezone ?: config('gym.timezone');
        $date = $request->validate(['date' => ['nullable', 'date']])['date'] ?? null;
        $day = ($date ? Carbon::parse($date, $tz) : Carbon::now($tz))->startOfDay();
        $bounds = [$day->copy()->utc(), $day->copy()->endOfDay()->utc()];

        $payments = Payment::where('branch_id', $branch->id)
            ->whereBetween('created_at', $bounds)
            ->whereIn('status', ['completed', 'refunded'])
            ->get(['method', 'amount_cents', 'status']);

        $completed = $payments->where('status', 'completed');
        $byMethod = collect(Payment::DESK_METHODS)
            ->merge($completed->pluck('method'))
            ->unique()
            ->map(fn (string $method) => [
                'method' => $method,
                'amount_cents' => (int) $completed->where('method', $method)->sum('amount_cents'),
                'count' => $completed->where('method', $method)->count(),
            ])
            ->values();

        $invoices = Invoice::where('branch_id', $branch->id)->whereBetween('issued_at', $bounds);

        return response()->json([
            'date' => $day->toDateString(),
            'total_cents' => (int) $completed->sum('amount_cents'),
            'by_method' => $byMethod,
            'refunded_cents' => (int) $payments->where('status', 'refunded')->sum('amount_cents'),
            'invoices_count' => (clone $invoices)->where('status', '!=', 'void')->count(),
            'unpaid_cents' => (int) (clone $invoices)->whereIn('status', ['issued', 'partially_paid'])->get()->sum(fn (Invoice $i) => $i->balanceCents()),
        ]);
    }
}
