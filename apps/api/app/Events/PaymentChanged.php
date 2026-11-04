<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Tells the branch's desk screens that a transfer arrived or was reviewed, so the queue refreshes. */
class PaymentChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /** @param  'submitted'|'reviewed'  $type */
    public function __construct(public Payment $payment, public string $type) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('branch.'.$this->payment->branch()->withoutGlobalScopes()->value('ulid').'.desk')];
    }

    public function broadcastAs(): string
    {
        return 'payment.'.$this->type;
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['payment' => ['id' => $this->payment->ulid, 'status' => $this->payment->status, 'amount_cents' => $this->payment->amount_cents]];
    }
}
