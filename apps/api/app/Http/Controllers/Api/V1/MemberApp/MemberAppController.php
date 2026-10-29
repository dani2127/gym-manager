<?php

namespace App\Http\Controllers\Api\V1\MemberApp;

use App\Billing\ReceiptPdf;
use App\CheckIns\MemberCodes;
use App\Http\Controllers\Controller;
use App\Http\Resources\CheckInResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\MembershipResource;
use App\Models\CheckIn;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Membership;
use App\Support\Tenancy\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/** The member's own data, for the member app. Read-only. */
class MemberAppController extends Controller
{
    private function member(Request $request): Member
    {
        return $request->attributes->get('member');
    }

    public function show(Request $request, Tenant $tenant): JsonResponse
    {
        $member = $this->member($request)->load('homeBranch');
        $memberships = $member->memberships()->with('branch')->whereIn('status', ['active', 'frozen', 'pending_payment'])->orderBy('starts_on')->get();
        $today = $tenant->organization()->today();

        $current = $memberships->first(fn (Membership $m) => $m->status !== 'pending_payment' && in_array($m->stateOn($today), ['current', 'frozen'], true));

        return response()->json(['data' => [
            'member' => [
                ...CheckInResource::member($member),
                'phone' => $member->phone,
                'joined_at' => $member->joined_at?->toDateString(),
            ],
            'organization' => ['name' => $tenant->organization()->name, 'slug' => $tenant->organization()->slug],
            'current' => $current ? new MembershipResource($current) : null,
            'upcoming' => MembershipResource::collection($memberships->filter(fn (Membership $m) => $m->id !== $current?->id)->values()),
            'owed_cents' => (int) Invoice::where('member_id', $member->id)->whereIn('status', ['issued', 'partially_paid'])->get()->sum(fn (Invoice $i) => $i->balanceCents()),
        ]]);
    }

    /**
     * What the app needs to draw the door QR code offline: the member's
     * secret and the server clock, so a phone with a wrong clock still works.
     */
    public function pass(Request $request): JsonResponse
    {
        $member = $this->member($request);

        return response()->json(['data' => [
            'member_id' => $member->ulid,
            'member_no' => $member->code,
            'secret' => $member->qr_secret,
            'window_seconds' => MemberCodes::WINDOW_SECONDS,
            'server_time' => now()->getTimestamp(),
        ]])->header('Cache-Control', 'no-store');
    }

    public function memberships(Request $request): AnonymousResourceCollection
    {
        return MembershipResource::collection(
            $this->member($request)->memberships()->with('branch')->latest('starts_on')->latest('id')->limit(30)->get()
        );
    }

    public function invoices(Request $request): AnonymousResourceCollection
    {
        return InvoiceResource::collection(
            Invoice::where('member_id', $this->member($request)->id)->with('branch', 'items')->latest('issued_at')->limit(30)->get()
        );
    }

    public function receipt(Request $request, Invoice $invoice, ReceiptPdf $pdf): Response
    {
        abort_unless($invoice->member_id === $this->member($request)->id, 404);

        $invoice->load('branch', 'member', 'creator', 'items.discountRule', 'payments', 'memberships', 'organization');

        return response($pdf->render($invoice, 'a5'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$invoice->number.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function visits(Request $request): JsonResponse
    {
        $visits = CheckIn::where('member_id', $this->member($request)->id)
            ->whereIn('result', ['allowed', 'overridden'])
            ->with('branch')
            ->latest('checked_in_at')
            ->limit(30)
            ->get();

        return response()->json(['data' => $visits->map(fn (CheckIn $c) => [
            'id' => $c->ulid,
            'branch' => $c->branch->name,
            'checked_in_at' => $c->checked_in_at,
            'checked_out_at' => $c->checked_out_at,
        ])]);
    }
}
