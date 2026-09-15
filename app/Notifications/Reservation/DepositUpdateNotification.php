<?php

namespace App\Notifications\Reservation;

use Illuminate\Notifications\Notification;

class DepositUpdateNotification extends Notification
{
    public function __construct(public int $reservationId, public string $message) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toArray(object $notifiable): array
    {
        \App\Support\LayoutBadges::forgetUnread($notifiable->id);
        return ['reservation_id' => $this->reservationId, 'message' => $this->message];
    }
}
