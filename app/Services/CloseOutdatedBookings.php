<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Support\LayoutBadges;
use App\Support\ResortTime;
use Illuminate\Support\Facades\DB;

/**
 * Close bookings whose stay is already over and the leftover payment reviews
 * still sitting on them. Unanswered requests expire, unpaid confirmations
 * cancel, and proofs that were never verified are declined. Paid stays that
 * ended without checkout are completed. Current holds are left for staff.
 */
class CloseOutdatedBookings
{
    public const PAYMENT_REASON = 'Stay dates passed before this proof was verified.';

    public const CANCEL_REASON = 'Stay dates passed without a verified deposit.';

    /**
     * @return array{payments: int, expired: int, cancelled: int, completed: int}
     */
    public function close(): array
    {
        $counts = [
            'payments' => 0,
            'expired' => 0,
            'cancelled' => 0,
            'completed' => 0,
        ];

        Reservation::withoutEvents(function () use (&$counts): void {
            Payment::withoutEvents(function () use (&$counts): void {
                $this->rejectStalePaymentReviews($counts);
                $this->closeEndedReservations($counts);
            });
        });

        LayoutBadges::forgetPendingReservations();
        LayoutBadges::forgetPendingPayments();

        return $counts;
    }

    /**
     * @param  array{payments: int, expired: int, cancelled: int, completed: int}  $counts
     */
    private function rejectStalePaymentReviews(array &$counts): void
    {
        Payment::query()
            ->where('status', PaymentStatus::Pending->value)
            ->whereHas('reservation', function ($query): void {
                $query->where(function ($query): void {
                    $query->whereIn('status', [
                        ReservationStatus::Expired->value,
                        ReservationStatus::Cancelled->value,
                        ReservationStatus::Rejected->value,
                        ReservationStatus::Completed->value,
                    ])->orWhere('check_out', '<=', now());
                });
            })
            ->select('id')
            ->chunkById(100, function ($rows) use (&$counts): void {
                foreach ($rows as $row) {
                    DB::transaction(function () use ($row, &$counts): void {
                        $payment = Payment::query()->whereKey($row->id)->lockForUpdate()->first();
                        if (! $payment || $payment->status !== PaymentStatus::Pending) {
                            return;
                        }

                        $reservation = $payment->reservation;
                        if (! $reservation || $reservation->policy_version === config('resort.policy.confirmed_version') || ! $this->paymentReviewIsStale($reservation)) {
                            return;
                        }

                        $payment->update([
                            'status' => PaymentStatus::Rejected->value,
                            'rejection_reason' => self::PAYMENT_REASON,
                        ]);
                        $counts['payments']++;
                    }, 5);
                }
            });
    }

    /**
     * @param  array{payments: int, expired: int, cancelled: int, completed: int}  $counts
     */
    private function closeEndedReservations(array &$counts): void
    {
        Reservation::query()
            ->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Approved->value])
            ->where('check_out', '<=', ResortTime::databaseNow())
            ->select('id')
            ->chunkById(100, function ($rows) use (&$counts): void {
                foreach ($rows as $row) {
                    DB::transaction(function () use ($row, &$counts): void {
                        $reservation = Reservation::query()->whereKey($row->id)->lockForUpdate()->first();
                        if (! $reservation || ! $this->occupancyHasEnded($reservation)) {
                            return;
                        }
                        if (! in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Approved], true)) {
                            return;
                        }

                        if ($reservation->policy_version === config('resort.policy.confirmed_version')) {
                            // A passed schedule never establishes physical arrival; preserve no-show refund eligibility.
                            if ($reservation->checked_in_at && ! $reservation->checked_out_at) {
                                if (app(ReservationService::class)->checkOutAutomatically($reservation)) $counts['completed']++;
                            }
                            return;
                        }
                        $reservation->setRelation('payments', $reservation->payments()->lockForUpdate()->get());

                        if ($reservation->status === ReservationStatus::Pending) {
                            $reservation->update([
                                'status' => ReservationStatus::Expired->value,
                                'expired_at' => now(),
                            ]);
                            $counts['expired']++;

                            return;
                        }

                        if ($reservation->getVerifiedTotalPaid() > 0) {
                            $reservation->update([
                                'status' => ReservationStatus::Completed->value,
                                'checked_in_at' => $reservation->checked_in_at ?? $reservation->occupancyStart(),
                                'checked_out_at' => $reservation->checked_out_at ?? $reservation->occupancyEnd(),
                                'completed_at' => now(),
                            ]);
                            $counts['completed']++;

                            return;
                        }

                        $reservation->update([
                            'status' => ReservationStatus::Cancelled->value,
                            'cancelled_at' => now(),
                            'cancellation_reason' => self::CANCEL_REASON,
                        ]);
                        $counts['cancelled']++;
                    }, 5);
                }
            });
    }

    private function paymentReviewIsStale(Reservation $reservation): bool
    {
        if (in_array($reservation->status, [
            ReservationStatus::Expired,
            ReservationStatus::Cancelled,
            ReservationStatus::Rejected,
            ReservationStatus::Completed,
        ], true)) {
            return true;
        }

        return $this->occupancyHasEnded($reservation);
    }

    private function occupancyHasEnded(Reservation $reservation): bool
    {
        return $reservation->occupancyEnd()->lte(ResortTime::databaseNow());
    }
}
