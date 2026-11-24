<?php

namespace App\Console\Commands;

use App\Services\CloseOutdatedBookings as Closer;
use Illuminate\Console\Command;

class CloseOutdatedBookings extends Command
{
    protected $signature = 'reservations:close-outdated';

    protected $description = 'Reject leftover payment reviews and expire, cancel, or complete stays whose occupancy already ended';

    public function handle(Closer $closer): int
    {
        $counts = $closer->close();

        $this->info(sprintf(
            'Closed outdated bookings: %d payment reviews rejected, %d expired, %d cancelled, %d completed.',
            $counts['payments'],
            $counts['expired'],
            $counts['cancelled'],
            $counts['completed'],
        ));

        return self::SUCCESS;
    }
}
