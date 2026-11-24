<?php

namespace App\Notifications\Reservation;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Support\ReservationArrivalMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationCreatedMail extends Notification implements ShouldQueue
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

        // A request that is still under review must not read like a booking: what
        // the guest was told at submission is what this copy repeats.
        $intro = match (true) {
            $this->reservation->status === ReservationStatus::Approved => 'Here is a copy of your reservation. Keep this email for your check in — you do not need to sign in at the front desk.',
            config('payments.deposit_workflow') => 'Here is a copy of your request — it is waiting in Review pending. We email you as soon as the resort verifies your request and payment.',
            default => 'Here is a copy of your request — it is still with staff for approval. We email you as soon as it is confirmed.',
        };

        return ReservationArrivalMail::message($this->reservation, $intro);
    }
}
