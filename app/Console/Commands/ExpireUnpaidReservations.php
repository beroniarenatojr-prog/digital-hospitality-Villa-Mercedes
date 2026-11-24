<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Enums\PaymentStatus;
use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireUnpaidReservations extends Command
{
    protected $signature = 'reservations:expire-unpaid';
    protected $description = 'Release review windows and deposit deadlines that closed with nothing paid';

    public function handle(): int
    {
        $expired = 0;
        // Two deadlines, one sweep. A `Pending` row has sat in Review pending past
        // the window staff were given to answer it — approving it then would sell
        // space the resort never agreed to hold this long. An `Approved` row has
        // passed the deposit deadline approval set — the guest has been told the
        // 50% is open and has not paid it, so the dates go back on the market.
        // Neither is released while money is attached: a proof under review or a
        // verified transfer needs a person, not a timer.
        Reservation::whereIn('status', [ReservationStatus::Pending, ReservationStatus::Approved])
            ->whereNotNull('deposit_required')
            ->where('deposit_due_at', '<=', now())->select('id')->chunkById(100, function ($rows) use (&$expired) {
                foreach ($rows as $row) DB::transaction(function () use ($row, &$expired) {
                    $candidate = Reservation::find($row->id);
                    if ($candidate?->reservation_group_id) {
                        \App\Models\ReservationGroup::whereKey($candidate->reservation_group_id)->lockForUpdate()->firstOrFail();
                    }
                    $reservation = Reservation::whereKey($row->id)->lockForUpdate()->first();
                    if (! $reservation || ! in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Approved], true) || $reservation->deposit_due_at->isFuture()) return;
                    // A confirmation whose stay has begun is settled by a person at
                    // the desk, not by a timer: the guest may be on the property,
                    // and releasing the space would be the one mistake the next
                    // booking would act on. It stays on the deposit report as
                    // overdue until staff record the money or end the booking.
                    if ($reservation->status === ReservationStatus::Approved && $reservation->occupancyStart()->isPast()) return;
                    $discussion = $reservation->reservationGroup?->booking_workflow ?? $reservation->booking_workflow ?? [];
                    if (($discussion['child_charge']['status'] ?? null) === 'disputed' || isset($discussion['refund'])) return;
                    if ($reservation->reservation_group_id) {
                        $group = $reservation->reservationGroup;
                        $group->directPayments()->lockForUpdate()->get();
                        if ($group->verifiedPaymentsTotal() > 0 || $group->pendingPaymentsTotal() > 0) return;
                    }
                    $payments = $reservation->payments()->lockForUpdate()->get();
                    if ($payments->contains(fn ($p) => in_array($p->status, [PaymentStatus::Pending, PaymentStatus::Verified], true))) return;
                    $wasAwaitingReview = $reservation->status === ReservationStatus::Pending;
                    $reservation->update(['status' => ReservationStatus::Expired, 'expired_at' => now()]);
                    activity()->performedOn($reservation)->event($wasAwaitingReview ? 'review_window_expired' : 'deposit_deadline_expired')
                        ->log($wasAwaitingReview ? 'Unreviewed reservation request expired.' : 'Unpaid deposit deadline expired after approval.');
                    $expired++;
                }, 5);
            });
        $this->info("Expired {$expired} holds and unpaid confirmations.");
        return self::SUCCESS;
    }
}
