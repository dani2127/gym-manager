<?php

namespace App\Notifications;

use App\Enums\OtpPurpose;
use App\Notifications\Channels\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OneTimeCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $code,
        public OtpPurpose $purpose,
        public int $minutes,
    ) {}

    /** @return list<class-string> */
    public function via(object $notifiable): array
    {
        return [TelegramChannel::class];
    }

    public function toTelegram(object $notifiable): string
    {
        return __('otp.'.$this->purpose->value, [
            'code' => $this->code,
            'minutes' => $this->minutes,
            'app' => config('app.name'),
        ]);
    }
}
