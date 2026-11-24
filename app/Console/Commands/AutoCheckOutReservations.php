<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Services\ReservationService;
use App\Support\ResortTime;
use Illuminate\Console\Command;

class AutoCheckOutReservations extends Command
{
    protected $signature = 'reservations:auto-check-out';

    protected $description = 'Complete in-house reservations when their scheduled occupancy ends';

    public function handle(ReservationService $reservations): int
    {
        $checkedOut = 0;

        Reservation::query()
            ->where('status', ReservationStatus::Approved->value)
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->where('check_out', '<=', ResortTime::databaseNow())
            ->select('id')
            ->chunkById(100, function ($rows) use ($reservations, &$checkedOut): void {
                foreach ($rows as $row) {
                    if ($reservations->checkOutAutomatically($row)) {
                        $checkedOut++;
                    }
                }
            });

        $this->info("Automatically checked out {$checkedOut} reservations.");

        return self::SUCCESS;
    }
}
