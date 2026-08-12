<?php

namespace App\Observers;

use App\Enums\Role;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\Payment\PaymentStatusChangedNotification;
use App\Notifications\Payment\PaymentSubmittedNotification;
use Illuminate\Support\Facades\Notification;

class PaymentObserver
{
    /**
     * Handle events after all transactions are committed.
     *
     * @var bool
     */
    public $afterCommit = true;

    /**
     * Handle the Payment "created" event.
     */
    public function created(Payment $payment): void
    {
        // Notify all administrators about the new payment submission
        try {
            $admins = User::role(Role::Administrator->value)->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new PaymentSubmittedNotification($payment));
            }
        } catch (\Spatie\Permission\Exceptions\RoleDoesNotExist $e) {
            // Role may not exist during isolated tests where seeders are not run
        }
    }

    /**
     * Handle the Payment "updated" event.
     */
    public function updated(Payment $payment): void
    {
        \Illuminate\Support\Facades\Log::info('PaymentObserver@updated triggered', ['payment_id' => $payment->id, 'wasChanged' => $payment->wasChanged('status')]);
        
        if ($payment->wasChanged('status')) {
            // Notify the customer (via the reservation relation) about the status change
            if ($payment->reservation && $payment->reservation->user) {
                $payment->reservation->user->notify(new PaymentStatusChangedNotification($payment));
            }
        }
    }
}
