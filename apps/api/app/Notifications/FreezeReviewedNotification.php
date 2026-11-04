<?php

namespace App\Notifications;

use App\Models\MembershipFreeze;
use App\Notifications\Channels\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Tells the member in Telegram that their freeze request was approved or rejected. */
class FreezeReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public MembershipFreeze $freeze) {}

    /** @return list<class-string> */
    public function via(object $notifiable): array
    {
        return [TelegramChannel::class];
    }

    public function toTelegram(object $notifiable): string
    {
        $key = $this->freeze->status === 'rejected' ? 'memberships.notify_freeze_rejected' : 'memberships.notify_freeze_approved';

        return __($key, [
            'from' => $this->freeze->starts_on->toDateString(),
            'to' => $this->freeze->ends_on->toDateString(),
            'back' => $this->freeze->ends_on->copy()->addDay()->toDateString(),
            'reason' => $this->freeze->rejection_reason,
        ]);
    }
}
