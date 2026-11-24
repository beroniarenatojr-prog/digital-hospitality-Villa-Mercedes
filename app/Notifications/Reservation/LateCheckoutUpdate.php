<?php

namespace App\Notifications\Reservation;

use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LateCheckoutUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $reservationId, public readonly array $decision) {}

    public function via(object $notifiable): array
    {
        return $this->decision['status'] === 'pending' ? ['database'] : ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->decision['status'] === 'approved';

        return (new MailMessage)->subject('Late check-out '.$this->decision['status'])
            ->line($approved
                ? 'Staff approved your room late check-out until '.$this->decision['departure'].' Philippine time. The additional fee of '.Money::of($this->decision['fee_cents'] / 100).' is now part of your booking balance. Approval does not mean this fee has been paid.'
                : 'Staff declined your late check-out request: '.$this->decision['reason'].'. No fee was charged; your existing check out time is unchanged.')
            ->action('View booking and balance', route('customer.reservations.show', $this->reservationId));
    }

    public function toArray(object $notifiable): array
    {
        \App\Support\LayoutBadges::forgetUnread($notifiable->id);

        return ['reservation_id' => $this->reservationId, 'message' => 'Late check-out '.$this->decision['status'].($this->decision['status'] === 'approved' ? '; fee added to balance, not recorded as paid.' : '; no fee charged.')];
    }
}
