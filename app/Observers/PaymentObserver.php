<?php

namespace App\Observers;

use App\Enums\Role;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\Payment\PaymentStatusChangedNotification;
use App\Notifications\Payment\PaymentSubmittedNotification;
use App\Support\LayoutBadges;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

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
        // A new proof changes the Payment review queue, so its sidebar badge is
        // recomputed rather than served from the last 45 seconds.
        LayoutBadges::forgetPendingPayments();

        // Notify all administrators about the new payment submission
        try {
            $admins = User::role(Role::Administrator->value)->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new PaymentSubmittedNotification($payment));
            }
        } catch (RoleDoesNotExist $e) {
            // Role may not exist during isolated tests where seeders are not run
        }
    }

    /**
     * Handle the Payment "updated" event.
     */
    public function updated(Payment $payment): void
    {
        Log::info('PaymentObserver@updated triggered', ['payment_id' => $payment->id, 'wasChanged' => $payment->wasChanged('status')]);

        if ($payment->wasChanged('status')) {
            LayoutBadges::forgetPendingPayments();

            // Notify the customer (via the reservation or group relation) about the status change
            $user = $payment->reservation?->user ?? $payment->reservationGroup?->user;
            if ($user) {
                $user->notify(new PaymentStatusChangedNotification($payment));
            }
        }
    }

    /**
     * Handle the Payment "deleted" event.
     */
    public function deleted(Payment $payment): void
    {
        LayoutBadges::forgetPendingPayments();
    }
}
