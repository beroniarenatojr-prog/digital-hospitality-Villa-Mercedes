<?php

namespace App\Notifications\Reservation;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Support\ReservationArrivalMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationStatusChangedMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Reservation $reservation
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
        $this->reservation->loadMissing(['facility', 'user']);

        $intro = match ($this->reservation->status) {
            ReservationStatus::Approved => 'Your stay is confirmed. Show this email or the arrival pass at the front desk — you do not need to sign in.',
            ReservationStatus::Rejected => 'We could not confirm this reservation.'.($this->reservation->rejection_reason ? ' '.$this->reservation->rejection_reason : ''),
            ReservationStatus::Cancelled => 'This reservation has been cancelled.'.($this->reservation->cancellation_reason ? ' '.$this->reservation->cancellation_reason : ''),
            default => 'Your reservation '.$this->reservation->reservation_code.' is now '.$this->reservation->status->label().'.',
        };

        return ReservationArrivalMail::message($this->reservation, $intro);
    }
}
