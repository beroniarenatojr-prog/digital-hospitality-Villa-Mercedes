<?php

namespace App\Console\Commands;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Notifications\Reservation\CheckoutReminderNotification;
use App\Support\LayoutBadges;
use Illuminate\Console\Command;

class SendCheckoutReminders extends Command
{
    protected $signature = 'reservations:send-checkout-reminders';

    protected $description = 'Send in-app reminders twenty minutes before scheduled checkout';

    public function handle(): int
    {
        $sent = 0;
        $now = now();
        $windowEnd = $now->copy()->addMinutes(20);

        Reservation::query()
            ->with('user')
            ->where('status', ReservationStatus::Approved->value)
            ->where('reservation_source', ReservationSource::Online->value)
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->where('check_out', '<=', $windowEnd)
            ->select('reservations.*')
            ->chunkById(100, function ($reservations) use ($now, $windowEnd, &$sent): void {
                foreach ($reservations as $reservation) {
                    $checkout = $reservation->occupancyEnd();
                    if ($checkout->lte($now) || $checkout->gt($windowEnd) || ! $reservation->user) {
                        continue;
                    }

                    $alreadySent = $reservation->user->notifications()
                        ->where('type', CheckoutReminderNotification::class)
                        ->where('data->reservation_id', $reservation->id)
                        ->exists();
                    if ($alreadySent) {
                        continue;
                    }

                    $reservation->user->notify(new CheckoutReminderNotification($reservation));
                    LayoutBadges::forgetUnread($reservation->user->id);
                    $sent++;
                }
            });

        $this->info("Sent {$sent} checkout reminders.");

        return self::SUCCESS;
    }
}
