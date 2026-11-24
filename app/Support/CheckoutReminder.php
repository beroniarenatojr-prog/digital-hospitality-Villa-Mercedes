<?php

namespace App\Support;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;

final class CheckoutReminder
{
    public static function currentFor(User $user): ?Reservation
    {
        $now = now();
        $windowEnd = $now->copy()->addMinutes(20);

        return $user->reservations()
            ->with('facility')
            ->where('status', ReservationStatus::Approved->value)
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->where('check_out', '<=', $windowEnd)
            ->get()
            ->first(fn (Reservation $reservation): bool => $reservation->occupancyEnd()->gt($now)
                && $reservation->occupancyEnd()->lte($windowEnd));
    }
}
