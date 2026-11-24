<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * The resort's clock.
 *
 * Two time bases coexist in this application, and mixing them moves an answer
 * by the eight hours between Manila and UTC:
 *
 *  - Instants (created_at, paid_at, checked_in_at, deposit_due_at) are stored
 *    in UTC and compared against a bare now().
 *  - Resort wall-clock values (check_in, check_out, reservation_date,
 *    payment_date) are the numbers staff write on the guest sheet: 2:00 PM
 *    means 2:00 PM at the resort, with no offset applied. They are only
 *    comparable to this clock.
 *
 * "Today", "tonight", and arrival lists are wall-clock questions, so the back
 * office asks ResortTime for them. Just after Manila midnight the UTC date is
 * still yesterday, which is how a 12:30 AM arrival used to disappear from the
 * arrival list until 8:00 AM.
 */
final class ResortTime
{
    /**
     * The timezone the resort operates in, and the one every resort calendar
     * day — arrivals, occupancy, daily revenue, report ranges — is measured in.
     */
    public static function timezone(): string
    {
        return config('resort.timezone', 'Asia/Manila');
    }

    /**
     * The current moment on the resort's wall clock.
     */
    public static function now(): Carbon
    {
        return Carbon::now(self::timezone());
    }

    /**
     * The current resort wall-clock value normalized to the timezone Eloquent
     * assigns to naive check_in/check_out database casts. This preserves the
     * displayed Manila hour for direct Carbon comparisons without converting
     * it back into a UTC instant.
     */
    public static function databaseNow(): Carbon
    {
        return self::now()->shiftTimezone(config('app.timezone', 'UTC'));
    }

    /**
     * The resort calendar day that is in progress, at midnight.
     */
    public static function today(): Carbon
    {
        return self::now()->startOfDay();
    }

    /**
     * A short label for a selected check-in range, used on operational reports.
     */
    public static function dateRangeLabel(?Carbon $from, ?Carbon $to): string
    {
        if (! $from || ! $to) {
            return 'today';
        }

        if ($from->isSameDay($to)) {
            return $from->format('M j, Y');
        }

        return $from->format('M j').' – '.$to->format('M j, Y');
    }
}
