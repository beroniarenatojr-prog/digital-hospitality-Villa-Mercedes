<?php

namespace App\Notifications\Reservation;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReservationStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Reservation $reservation
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $statusValue = $this->reservation->status->value;
        
        $reason = null;
        if ($statusValue === \App\Enums\ReservationStatus::Rejected->value) {
            $reason = $this->reservation->rejection_reason;
        } elseif ($statusValue === \App\Enums\ReservationStatus::Cancelled->value) {
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
