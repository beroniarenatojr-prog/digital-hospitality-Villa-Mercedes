<?php

namespace App\Notifications\Reservation;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

class ReservationStatusChangedNotification extends Notification
{
    public function __construct(
        public readonly Reservation $reservation
    ) {}

    /**
     * In-app badge updates stay on the request. SMTP is ReservationStatusChangedMail.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return [];
        }

        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $statusValue = $this->reservation->status->value;

        $reason = null;
        if ($statusValue === ReservationStatus::Rejected->value) {
            $reason = $this->reservation->rejection_reason;
        } elseif ($statusValue === ReservationStatus::Cancelled->value) {
            $reason = $this->reservation->cancellation_reason;
        }

        return [
            'reservation_id' => $this->reservation->id,
            'reservation_code' => $this->reservation->reservation_code,
            'new_status' => $statusValue,
            'reason' => $reason,
            'message' => "Your reservation {$this->reservation->reservation_code} status changed to {$this->reservation->status->label()}.",
        ];
    }
}
