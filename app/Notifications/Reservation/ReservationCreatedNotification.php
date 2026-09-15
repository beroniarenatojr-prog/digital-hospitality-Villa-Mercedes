<?php

namespace App\Notifications\Reservation;

use App\Models\Reservation;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

class ReservationCreatedNotification extends Notification
{
    public function __construct(
        public readonly Reservation $reservation,
        public readonly bool $isAdminNotification = false
    ) {}

    /**
     * In-app badge updates stay on the request. SMTP is ReservationCreatedMail.
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
        return [
            'reservation_id' => $this->reservation->id,
            'reservation_code' => $this->reservation->reservation_code,
            'facility_name' => $this->reservation->facility->name ?? 'Unknown',
            'check_in' => $this->reservation->check_in?->toDateTimeString(),
            'check_out' => $this->reservation->check_out?->toDateTimeString(),
            'status' => $this->reservation->status->value,
            'reservation_source' => $this->reservation->reservation_source->value,
            'customer_name' => $this->isAdminNotification ? ($this->reservation->user->name ?? 'Unknown') : null,
            'total_price' => $this->reservation->total_price,
            'is_admin_notification' => $this->isAdminNotification,
            'message' => $this->isAdminNotification
                ? "New reservation {$this->reservation->reservation_code} created."
                : "Your reservation {$this->reservation->reservation_code} has been successfully placed.",
        ];
    }
}
