<?php

namespace App\Notifications;

use App\Billing\Money;
use App\Models\Payment;
use App\Notifications\Channels\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Tells the member in Telegram that their transfer was approved or rejected. */
class PaymentReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    /** @return list<class-string> */
    public function via(object $notifiable): array
    {
        return [TelegramChannel::class];
    }

    public function toTelegram(object $notifiable): string
    {
        $key = $this->payment->status === 'completed' ? 'billing.notify_approved' : 'billing.notify_rejected';

        return __($key, [
            'amount' => Money::format($this->payment->amount_cents),
            'number' => $this->payment->invoice->number,
            'reason' => $this->payment->rejection_reason,
        ]);
    }
}
