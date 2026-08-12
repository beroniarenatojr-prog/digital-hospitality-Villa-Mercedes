<?php

namespace App\Notifications\Payment;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Payment $payment
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $statusValue = $this->payment->status->value;
        $reason = $statusValue === \App\Enums\PaymentStatus::Rejected->value ? $this->payment->rejection_reason : null;

        return [
            'payment_id' => $this->payment->id,
            'reservation_id' => $this->payment->reservation_id,
            'reservation_code' => $this->payment->reservation->reservation_code ?? 'Unknown',
            'new_status' => $statusValue,
            'amount' => $this->payment->amount,
            'reason' => $reason,
            'message' => "Your payment of {$this->payment->amount} for reservation {$this->payment->reservation->reservation_code} was {$statusValue}.",
        ];
    }
}
