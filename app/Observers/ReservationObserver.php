<?php

namespace App\Observers;

use App\Enums\ReservationSource;
use App\Enums\Role;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\Reservation\ReservationCreatedMail;
use App\Notifications\Reservation\ReservationCreatedNotification;
use App\Notifications\Reservation\ReservationStatusChangedMail;
use App\Notifications\Reservation\ReservationStatusChangedNotification;
use App\Support\LayoutBadges;
use App\Support\OutboundMail;
use Illuminate\Notifications\Notification as NotificationContract;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

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
        LayoutBadges::forgetPendingReservations();

        // 1. Notify the booking account (in-app immediately, email via the queue)
        if ($reservation->user) {
            $reservation->user->notify(new ReservationCreatedNotification($reservation, false));
            LayoutBadges::forgetUnread($reservation->user->id);
        }

        $this->emailGuest($reservation, new ReservationCreatedMail($reservation));

        // 2. Notify all administrators in-app
        try {
            $admins = User::role(Role::Administrator->value)->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new ReservationCreatedNotification($reservation, true));
                $admins->each(fn (User $admin) => LayoutBadges::forgetUnread($admin->id));
            }
        } catch (RoleDoesNotExist $e) {
            // Role may not exist during isolated tests where seeders are not run
        }
    }

    /**
     * Handle the Reservation "updated" event.
     */
    public function updated(Reservation $reservation): void
    {
        if ($reservation->wasChanged(['status', 'checked_out_at']) && $reservation->canReview()) {
            app(\App\Services\ReviewInvitationService::class)->invite($reservation);
        }
        if ($reservation->wasChanged('status')) {
            LayoutBadges::forgetPendingReservations();

            if ($reservation->user) {
                $reservation->user->notify(new ReservationStatusChangedNotification($reservation));
                LayoutBadges::forgetUnread($reservation->user->id);
            }

            $this->emailGuest($reservation, new ReservationStatusChangedMail($reservation));
        }
    }

    public function deleted(Reservation $reservation): void
    {
        LayoutBadges::forgetPendingReservations();
    }

    private function emailGuest(Reservation $reservation, NotificationContract $mail): void
    {
        if (! OutboundMail::isConfigured()) {
            return;
        }

        if ($reservation->reservation_source === ReservationSource::WalkIn) {
            $guestEmail = $reservation->guest_email;

            if (! filled($guestEmail) || $reservation->user?->email === $guestEmail) {
                return;
            }

            Notification::route('mail', $guestEmail)->notify($mail);

            return;
        }

        if ($reservation->user instanceof User) {
            $reservation->user->notify($mail);
        }
    }
}
