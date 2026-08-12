<?php

namespace App\Observers;

use App\Enums\Role;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\Reservation\ReservationCreatedNotification;
use App\Notifications\Reservation\ReservationStatusChangedNotification;
use Illuminate\Support\Facades\Notification;

class ReservationObserver
{
    /**
     * Handle events after all transactions are committed.
     *
     * @var bool
     */
    public $afterCommit = true;

    /**
     * Handle the Reservation "created" event.
     */
    public function created(Reservation $reservation): void
    {
        // 1. Notify the customer
        if ($reservation->user) {
            $reservation->user->notify(new ReservationCreatedNotification($reservation, false));
        }

        // 2. Notify all administrators
        try {
            $admins = User::role(Role::Administrator->value)->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new ReservationCreatedNotification($reservation, true));
            }
        } catch (\Spatie\Permission\Exceptions\RoleDoesNotExist $e) {
            // Role may not exist during isolated tests where seeders are not run
        }
    }

    /**
     * Handle the Reservation "updated" event.
     */
    public function updated(Reservation $reservation): void
    {
        if ($reservation->wasChanged('status')) {
            // Notify the customer about the status change
            if ($reservation->user) {
                $reservation->user->notify(new ReservationStatusChangedNotification($reservation));
            }
        }
    }
}
