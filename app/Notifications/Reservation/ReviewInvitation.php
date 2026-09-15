<?php

namespace App\Notifications\Reservation;

use App\Models\Reservation;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ReviewInvitation extends Notification
{
    public function __construct(public Reservation $reservation, public string $channel) {}
    public function via(object $notifiable): array { return [$this->channel]; }
    public function toArray(object $notifiable): array
    {
        return ['reservation_id' => $this->reservation->id, 'review_invitation' => true, 'message' => 'How was your visit? Rate your experience at Villa Mercedes. Sharing feedback is optional.'];
    }
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('How was your visit to Villa Mercedes?')
            ->line('Thank you for visiting. You can share an honest rating and optional feedback about your experience.')
            ->line('You choose whether staff may publish your review. There is no obligation to respond.')
            ->action('Rate your experience', route('customer.reservations.show', $this->reservation).'#review');
    }
}
