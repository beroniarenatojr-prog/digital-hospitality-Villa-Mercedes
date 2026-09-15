<?php

namespace App\Support;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived layout counts shared by admin/customer chrome and the dashboard.
 */
final class LayoutBadges
{
    private const PENDING_KEY = 'layout:pending-reservations';

    private const TTL_SECONDS = 45;

    /**
     * @return array{count: int, items: Collection<int, DatabaseNotification>}
     */
    public static function unreadNotifications(User $user): array
    {
        /** @var array{count: int, items: Collection<int, DatabaseNotification>} $payload */
        $payload = Cache::remember(self::unreadKey($user->id), self::TTL_SECONDS, function () use ($user): array {
            $items = $user->unreadNotifications()->latest()->limit(5)->get();
            $count = $items->count();

            return [
                'count' => $count === 5 ? $user->unreadNotifications()->count() : $count,
                'items' => $items,
            ];
        });

        return $payload;
    }

    public static function pendingReservationCount(): int
    {
        return (int) Cache::remember(self::PENDING_KEY, self::TTL_SECONDS, fn (): int => Reservation::query()
            ->where('status', ReservationStatus::Pending->value)
            ->count());
    }

    public static function forgetPendingReservations(): void
    {
        Cache::forget(self::PENDING_KEY);
    }

    public static function forgetUnread(int $userId): void
    {
        Cache::forget(self::unreadKey($userId));
    }

    private static function unreadKey(int $userId): string
    {
        return "layout:unread-notifications:{$userId}";
    }
}
