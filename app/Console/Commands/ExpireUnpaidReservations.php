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
    protected $description = 'Release overdue deposit holds with no unresolved proof or received funds';

    public function handle(): int
    {
        $expired = 0;
        Reservation::where('status', ReservationStatus::Pending)->whereNotNull('deposit_required')
            ->where('deposit_due_at', '<=', now())->select('id')->chunkById(100, function ($rows) use (&$expired) {
                foreach ($rows as $row) DB::transaction(function () use ($row, &$expired) {
                    $reservation = Reservation::whereKey($row->id)->lockForUpdate()->first();
                    if (! $reservation || $reservation->status !== ReservationStatus::Pending || $reservation->deposit_due_at->isFuture()) return;
                    $payments = $reservation->payments()->lockForUpdate()->get();
                    if ($payments->contains(fn ($p) => in_array($p->status, [PaymentStatus::Pending, PaymentStatus::Verified], true))) return;
                    $reservation->update(['status' => ReservationStatus::Expired, 'expired_at' => now()]);
                    activity()->performedOn($reservation)->event('deposit_hold_expired')->log('Unpaid deposit hold expired.');
                    $expired++;
                }, 5);
            });
        $this->info("Expired {$expired} unpaid holds.");
        return self::SUCCESS;
    }
}
