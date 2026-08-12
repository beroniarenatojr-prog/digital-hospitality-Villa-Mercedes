<?php

namespace App\Notifications\Payment;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentSubmittedNotification extends Notification implements ShouldQueue
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
        return [
            'payment_id' => $this->payment->id,
            'reservation_id' => $this->payment->reservation_id,
            'reservation_code' => $this->payment->reservation->reservation_code ?? 'Unknown',
            'amount' => $this->payment->amount,
            'method' => $this->payment->method->value,
            'status' => $this->payment->status->value,
            'message' => "A new payment of {$this->payment->amount} was submitted for reservation {$this->payment->reservation->reservation_code}.",
        ];
    }
}
