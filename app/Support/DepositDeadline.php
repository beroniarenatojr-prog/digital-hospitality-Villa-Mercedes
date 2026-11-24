<?php

namespace App\Support;

use Carbon\Carbon;

final class DepositDeadline
{
    // Existing booking dates are resort wall-clock values. Payment deadlines
    // are instants stored in the application's timezone (UTC by default).
    public static function visitInstant(Carbon $occupancyStart): Carbon
    {
        return $occupancyStart->copy()->shiftTimezone(ResortTime::timezone())->setTimezone(config('app.timezone'));
    }

    public static function forVisit(Carbon $occupancyStart, ?Carbon $originalDeadline = null): Carbon
    {
        return ($originalDeadline?->copy() ?? now()->addHours(self::holdHours()))->min(self::visitInstant($occupancyStart));
    }

    public static function forRoomStay(Carbon $start, Carbon $end, bool $room): Carbon
    {
        // After the standard start, keep the configured payment window,
        // bounded by checkout. Other services retain their original deadline.
        if ($room && $start->toDateString() === ResortTime::today()->toDateString()
            && $end->toDateString() > $start->toDateString()
            && self::visitInstant($start)->lte(now())) {
            return now()->addHours(self::holdHours())->min(self::visitInstant($end));
        }
        return self::forVisit($start);
    }

    public static function forReservation(\App\Models\Reservation $reservation): Carbon
    {
        return self::forRoomStay($reservation->occupancyStart(), $reservation->check_out, $reservation->facility->isRoom());
    }

    /**
     * How long a new hold waits for its deposit, before the visit's own start
     * caps it. Read from config so the window a guest is promised in the deposit
     * instructions is the window the scheduler releases the hold on.
     */
    private static function holdHours(): int
    {
        return max(1, (int) config('payments.hold_hours', 24));
    }
}
