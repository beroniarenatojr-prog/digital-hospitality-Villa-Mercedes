<?php

namespace App\Support\Admin;

use App\Enums\ReservationStatus;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * What is waiting on staff, as the dashboard's first panel states it.
 *
 * The definitions live here rather than in a view because two renderings need
 * them: the page, and the panel that reads itself again while a staff member is
 * looking at it. One definition is what stops those two from disagreeing — a
 * count, its wording and the screen it opens are stated once.
 */
final class DashboardAttention
{
    /**
     * @return list<array{label: string, description: string, count: int, url: string, action: string}>
     */
    public static function items(Carbon $today, int $pendingReservations, int $pendingPayments, int $arrivalsToday): array
    {

        return [
            [
                'label' => 'Reservations waiting for a decision',
                'description' => 'Review requests that need staff action.',
                'count' => $pendingReservations,
                'url' => route('admin.reservations.index', ['status' => ReservationStatus::Pending->value]),
                'action' => 'Review reservations',
            ],
            [
                'label' => 'Payments waiting for verification',
                'description' => 'Proofs submitted by guests that have not been checked against the account yet.',
                'count' => $pendingPayments,
                'url' => route('admin.payments.index', ['status' => 'pending']),
                'action' => 'Review payments',
            ],
            [
                'label' => 'Check ins due today',
                'description' => 'Pending and approved stays with a check-in today that have not been checked in yet.',
                'count' => $arrivalsToday,
                'url' => route('admin.reservations.index', ['arriving_today' => 1]),
                'action' => 'Open check in list',
            ],
        ];
    }

    /**
     * @param  list<array{count: int}>  $items
     */
    public static function total(array $items): int
    {
        return (int) array_sum(array_column($items, 'count'));
    }

    /**
     * The phrase beside the panel title. The page prints it and the fragment
     * carries it, so a poll that moved a count moves the total with it.
     */
    public static function summary(int $total): string
    {
        return $total.' '.Str::plural('item', $total);
    }
}
