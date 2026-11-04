<?php

namespace App\Events;

use App\Models\MembershipFreeze;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Tells the branch's desk screens a freeze was requested or handled, so the list and badge refresh. */
class FreezeChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /** @param  'requested'|'reviewed'  $type */
    public function __construct(public MembershipFreeze $freeze, public string $type) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('branch.'.$this->freeze->branch()->withoutGlobalScopes()->value('ulid').'.desk')];
    }

    public function broadcastAs(): string
    {
        return 'freeze.'.$this->type;
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['freeze' => ['id' => $this->freeze->ulid, 'status' => $this->freeze->status]];
    }
}
