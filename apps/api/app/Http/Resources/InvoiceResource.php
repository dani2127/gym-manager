<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'number' => $this->number,
            'status' => $this->status,
            'subtotal_cents' => $this->subtotal_cents,
            'discount_cents' => $this->discount_cents,
            'tax_cents' => $this->tax_cents,
            'net_cents' => $this->netCents(),
            'total_cents' => $this->total_cents,
            'paid_cents' => $this->paid_cents,
            'balance_cents' => $this->balanceCents(),
            'tax_name' => $this->tax_name,
            'tax_rate' => (float) $this->tax_rate,
            'tax_inclusive' => $this->tax_inclusive,
            'currency' => $this->currency,
            'issued_at' => $this->issued_at,
            'branch' => $this->whenLoaded('branch', fn () => ['id' => $this->branch->ulid, 'name' => $this->branch->name]),
            'member' => $this->whenLoaded('member', fn () => $this->member ? [
                'id' => $this->member->ulid,
                'full_name' => $this->member->full_name,
                'member_no' => $this->member->code,
            ] : null),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (InvoiceItem $item) => [
                'description' => $item->translate('description'),
                'kind' => $item->itemable_type ? class_basename($item->itemable_type) : null,
                'qty' => $item->qty,
                'unit_price_cents' => $item->unit_price_cents,
                'discount_cents' => $item->discount_cents,
                'discount' => $item->discountRule ? ['kind' => $item->discountRule->kind, 'name' => $item->discountRule->translate('name')] : null,
                'tax_cents' => $item->tax_cents,
                'total_cents' => $item->total_cents,
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn (Payment $p) => [
                'id' => $p->ulid,
                'method' => $p->method,
                'amount_cents' => $p->amount_cents,
                'status' => $p->status,
                'reference' => $p->reference,
                'received_by' => $p->receiver?->name,
                'created_at' => $p->created_at,
            ])),
            'memberships' => MembershipResource::collection($this->whenLoaded('memberships')),
            'void_reason' => $this->void_reason,
            'voided_at' => $this->voided_at,
        ];
    }
}
