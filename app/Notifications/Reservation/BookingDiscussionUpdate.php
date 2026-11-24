<?php

namespace App\Notifications\Reservation;

use App\Enums\Role;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingDiscussionUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $reservationId, public readonly string $event) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return ['reservation_id' => $this->reservationId, 'message' => 'Booking discussion: '.str_replace('_', ' ', $this->event).'. A request, owner approval, and money returned are separate records.'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $line = Reservation::findOrFail($this->reservationId);

        return (new MailMessage)->subject('Booking update: '.$line->reservation_code)
            ->line($this->toArray($notifiable)['message'])
            ->line('Saved policy version: '.$line->policy_version.'. Review the current verified payments and remaining balance on your booking record.')
            ->action('Review booking', route($notifiable->hasRole(Role::Administrator->value) ? 'admin.reservations.show' : 'customer.reservations.show', $line));
    }
}
