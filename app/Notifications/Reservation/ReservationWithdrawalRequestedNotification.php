<?php

namespace App\Notifications\Reservation;

use App\Models\Reservation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationWithdrawalRequestedNotification extends Notification
{
    public function __construct(
        public readonly Reservation $reservation
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Withdrawal requested: '.$this->reservation->reservation_code)
            ->line("{$this->reservation->user?->name} asked to withdraw reservation {$this->reservation->reservation_code}.")
            ->action('Review reservation', route('admin.reservations.show', $this->reservation));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'reservation_id' => $this->reservation->id,
            'reservation_code' => $this->reservation->reservation_code,
            'message' => "Guest requested withdrawal of {$this->reservation->reservation_code}.",
        ];
    }
}
