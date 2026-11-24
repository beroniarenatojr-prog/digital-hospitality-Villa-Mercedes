<?php

namespace App\Support;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Payment;
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

    private const OVERDUE_RESERVATIONS_KEY = 'layout:overdue-reservations';

    private const PENDING_PAYMENTS_KEY = 'layout:pending-payments';

    private const OVERDUE_PAYMENTS_KEY = 'layout:overdue-payments';

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

    /**
     * Requests nobody has answered inside the window they were promised, read
     * back from the same column that means "review window" on a Pending row
     * (`deposit_due_at`, per RULES 23). These are the rows the scheduler is
     * about to release, so the Reservations badge has to look urgent while they
     * are still here — a request is not "waiting" if its answer is already late.
     */
    public static function overdueReservationCount(): int
    {
        return (int) Cache::remember(self::OVERDUE_RESERVATIONS_KEY, self::TTL_SECONDS, fn (): int => Reservation::query()
            ->where('status', ReservationStatus::Pending->value)
            ->where('deposit_due_at', '<=', now())
            ->count());
    }

    public static function forgetPendingReservations(): void
    {
        Cache::forget(self::PENDING_KEY);
        Cache::forget(self::OVERDUE_RESERVATIONS_KEY);
    }

    /**
     * Proofs waiting for staff: the queue Payment review opens on, and the
     * number its sidebar badge shows. Verified and rejected rows leave the
     * queue, so the badge falls as the desk settles them.
     */
    public static function pendingPaymentCount(): int
    {
        return (int) Cache::remember(self::PENDING_PAYMENTS_KEY, self::TTL_SECONDS, fn (): int => Payment::query()
            ->where('status', PaymentStatus::Pending->value)
            ->count());
    }

    /**
     * Proofs that have outlasted the review window the screen promises, so the
     * Payment review badge can say "this has stopped being a queue and started
     * being a complaint". The rule itself lives on Payment, so the panel that
     * says "overdue" in words and this count cannot disagree.
     */
    public static function overduePaymentCount(): int
    {
        return (int) Cache::remember(self::OVERDUE_PAYMENTS_KEY, self::TTL_SECONDS, fn (): int => Payment::query()
            ->awaitingReview()
            ->overdueForReview()
            ->count());
    }

    public static function forgetPendingPayments(): void
    {
        Cache::forget(self::PENDING_PAYMENTS_KEY);
        Cache::forget(self::OVERDUE_PAYMENTS_KEY);
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
