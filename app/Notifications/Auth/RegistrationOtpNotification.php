<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly int $expireMinutes,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your '.config('resort.short_name').' verification code')
            ->greeting(config('resort.name'))
            ->line('Your verification code is:')
            ->line($this->code)
            ->line("This code expires in {$this->expireMinutes} minutes.")
            ->line('If you did not request this code, you can ignore this email.')
            ->salutation('See you at the resort,'.PHP_EOL.config('resort.short_name'));
    }
}
