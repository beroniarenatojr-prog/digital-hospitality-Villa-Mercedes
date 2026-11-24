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
            ReservationStatus::Approved => $this->approvedIntro(),
            ReservationStatus::Rejected => $this->rejectedIntro(),
            ReservationStatus::Cancelled => $this->cancelledIntro(),
            ReservationStatus::Expired => $this->expiredIntro(),
            default => 'Your reservation '.$this->reservation->reservation_code.' is now '.$this->reservation->status->label().'.',
        };

        return ReservationArrivalMail::message($this->reservation, $intro);
    }

    /**
     * Approval is the moment the guest is told to pay, so this is the letter
     * that has to say what to do about it: the 50% deposit is open, and it has
     * a deadline that approval set. A booking whose deposit is already in — a
     * walk-in, a re-sent confirmation — is not asked for money again.
     */
    private function approvedIntro(): string
    {
        $depositOwed = $this->reservation->deposit_required !== null
            && $this->reservation->getVerifiedTotalPaid() < $this->reservation->requiredDepositAmount();

        return 'Your stay is confirmed. '
            . ($depositOwed
                ? 'The next step is the 50% deposit: open your reservation to scan the resort QR and upload your proof of payment before its deadline, and keep the remaining balance for check in. Show this email or the check in pass at the front desk — you do not need to sign in.'
                : 'Show this email or the check in pass at the front desk — you do not need to sign in.');
    }

    /**
     * An expired booking ended for one of two reasons, and the guest can only act
     * on the right one: either nobody answered the request in time, or approval
     * came and the 50% deposit did not.
     */
    private function expiredIntro(): string
    {
        return $this->reservation->approved_at === null
            ? 'We could not answer this request within its review window, so the dates were released. Book again when you are ready, or call the resort so we can look for other dates or spaces.'
            : 'The 50% deposit did not arrive before its deadline, so this booking was released. If you have already transferred the money, contact the resort — do not send another payment.';
    }

    private function rejectedIntro(): string
    {
        $reason = $this->reservation->rejection_reason;

        return 'We could not confirm this reservation.'
            . ($reason ? ' Reason: '.$reason : '')
            . ' If you still want to visit, contact the resort so we can check other dates or spaces.';
    }

    private function cancelledIntro(): string
    {
        $reason = $this->reservation->cancellation_reason;

        return 'This reservation has been cancelled.'
            . ($reason ? ' Reason: '.$reason : '')
            . ' If this was a mistake, contact the resort as soon as possible.';
    }
}
