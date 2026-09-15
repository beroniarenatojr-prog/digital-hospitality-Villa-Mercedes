<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingMagicLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $url,
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
            ->subject('Confirm your '.config('resort.short_name').' reservation')
            ->greeting(config('resort.name'))
            ->line('Use this link to confirm your reservation. Nothing is charged when you confirm.')
            ->action('Confirm reservation', $this->url)
            ->line("This link expires in {$this->expireMinutes} minutes.")
            ->line('If you did not start a reservation, you can ignore this email.')
            ->salutation('See you at the resort,'.PHP_EOL.config('resort.short_name'));
    }
}
