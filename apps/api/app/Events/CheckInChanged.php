<?php

namespace App\Events;

use App\Http\Resources\CheckInResource;
use App\Models\CheckIn;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Pushed to every desk screen of the branch the moment someone scans in, is
 * let in by override, or checks out, so a kiosk scan pops up on the desk.
 */
class CheckInChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /** @param  'scanned'|'overridden'|'checked_out'  $type */
    public function __construct(public CheckIn $checkIn, public string $type) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('branch.'.$this->checkIn->branch->ulid.'.desk')];
    }

    public function broadcastAs(): string
    {
        return 'check-in.'.$this->type;
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['check_in' => (new CheckInResource($this->checkIn))->resolve()];
    }
}
