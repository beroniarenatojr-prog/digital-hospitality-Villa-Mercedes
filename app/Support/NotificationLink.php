<?php

namespace App\Support;

use App\Models\Payment;
use App\Models\Reservation;

class NotificationLink
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function forAdmin(array $data): string
    {
        if (self::filledId($data, 'payment_id') && Payment::query()->whereKey($data['payment_id'])->exists()) {
            return route('admin.payments.show', $data['payment_id']);
        }

        if (self::filledId($data, 'reservation_id') && Reservation::query()->whereKey($data['reservation_id'])->exists()) {
            return route('admin.reservations.show', $data['reservation_id']);
        }

        return route('admin.notifications.index');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function forCustomer(array $data): string
    {
        if (! empty($data['review_invitation']) && self::filledId($data, 'reservation_id')) {
            return route('customer.reservations.show', $data['reservation_id']).'#review';
        }
        if (self::filledId($data, 'reservation_id') && Reservation::query()->whereKey($data['reservation_id'])->exists()) {
            return route('customer.reservations.show', $data['reservation_id']);
        }

        return route('customer.notifications.index');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function filledId(array $data, string $key): bool
    {
        return isset($data[$key]) && $data[$key] !== '' && $data[$key] !== null;
    }
}
