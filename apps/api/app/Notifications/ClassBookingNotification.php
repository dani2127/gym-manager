<?php

namespace App\Notifications;

use App\Models\ClassBooking;
use App\Notifications\Channels\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Tells the member in Telegram they moved off the waitlist, or their class was called off. */
class ClassBookingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  'promoted'|'session_cancelled'  $kind */
    public function __construct(public ClassBooking $booking, public string $kind) {}

    /** @return list<class-string> */
    public function via(object $notifiable): array
    {
        return [TelegramChannel::class];
    }

    public function toTelegram(object $notifiable): string
    {
        $session = $this->booking->session;
        $tz = $session->branch->organization->timezone ?: config('gym.timezone');

        return __("classes.notify_{$this->kind}", [
            'class' => $session->classType->translate('name', $notifiable->preferredLocale()),
            'when' => $session->starts_at->copy()->setTimezone($tz)->format('D j M, H:i'),
            'branch' => $session->branch->name,
            'reason' => $session->cancel_reason,
        ], $notifiable->preferredLocale());
    }
}
