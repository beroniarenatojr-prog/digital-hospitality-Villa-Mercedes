<?php

namespace App\Notifications\Reservation;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifiedPaymentReceipt extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $this->payment->fresh()?->status === PaymentStatus::Verified;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payment = $this->payment->fresh() ?? $this->payment;
        $reservation = $payment->reservation;
        $group = $payment->reservationGroup;
        $mail = (new MailMessage)->subject('Verified payment receipt: '.($reservation?->reservation_code ?? $group?->group_code))
            ->line('Payment status: Verified. Recorded paid amount: '.Money::of($payment->amount).'.');
        $lines = $reservation ? collect([$reservation]) : $group->reservations;
        foreach ($lines as $line) {
            $mail->line($line->reservation_code.' — '.$line->facility?->name);
            $mail->line('Accepted policy: '.$line->policy_version.'. Refund request, owner approval, and money returned are separate records.');
            foreach ($line->priceItems() as $label => $cents) {
                $mail->line($label.': '.Money::of($cents / 100));
            }
        }

        return $mail->line('Current booking balance: '.Money::of($reservation ? $reservation->getRemainingBalance() : $group->outstandingBalance()))
            ->line('Itemization describes your current agreed booking. Only verified funds count as paid. This receipt is not a booking approval or a tax invoice.')
            ->action('View itemized receipt', route('customer.payments.receipt', $payment));
    }
}
