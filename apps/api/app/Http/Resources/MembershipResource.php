<?php

namespace App\Http\Resources;

use App\Memberships\Freezes;
use App\Models\Membership;
use App\Models\MembershipFreeze;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin Membership */
class MembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $today = Carbon::parse(Carbon::now(config('gym.timezone'))->toDateString());

        return [
            'id' => $this->ulid,
            'plan' => [
                'id' => $this->plan?->ulid,
                'name' => $this->planName(),
                'type' => $this->plan_snapshot['type'] ?? null,
                'access_scope' => $this->plan_snapshot['access_scope'] ?? null,
            ],
            'status' => $this->status,
            // upcoming | current | expired, or the stored status when not active.
            'state' => $this->stateOn($today),
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'days_left' => $this->ends_on && $this->ends_on->gte($today) ? (int) $today->diffInDays($this->ends_on) + 1 : null,
            'visits_total' => $this->visits_total,
            'visits_remaining' => $this->visits_remaining,
            'branch' => $this->whenLoaded('branch', fn () => ['id' => $this->branch->ulid, 'name' => $this->branch->name]),
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice ? [
                'id' => $this->invoice->ulid,
                'number' => $this->invoice->number,
                'status' => $this->invoice->status,
                'balance_cents' => $this->invoice->balanceCents(),
            ] : null),
            // Freezes and what the plan still allows, when loaded.
            'freezes' => $this->whenLoaded('freezes', fn () => $this->freezes->whereNotIn('status', ['cancelled'])->values()->map(fn (MembershipFreeze $f) => $f->present())),
            'freeze_policy' => $this->whenLoaded('freezes', fn () => app(Freezes::class)->policy($this->resource, $today)),
            'created_at' => $this->created_at,
        ];
    }
}
