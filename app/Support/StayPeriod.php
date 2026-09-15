<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Turns calendar dates from the public search form into the same occupancy
 * window the booking wizard uses (day-use vs overnight published hours).
 */
final class StayPeriod
{
    public function __construct(
        public readonly Carbon $arrival,
        public readonly Carbon $departure,
        public readonly string $preset,
        public readonly string $checkInDate,
        public readonly string $checkOutDate,
    ) {}

    public static function fromDates(string $checkInDate, string $checkOutDate): self
    {
        $startDay = Carbon::createFromFormat('Y-m-d', $checkInDate)->startOfDay();
        $endDay = Carbon::createFromFormat('Y-m-d', $checkOutDate)->startOfDay();

        if ($endDay->lessThan($startDay)) {
            $endDay = $startDay->copy()->addDay();
        }

        $preset = $endDay->isSameDay($startDay) ? 'day_use' : 'overnight';

        if ($preset === 'day_use') {
            $start = config('resort.stay.day_use_hours.start');
            $end = config('resort.stay.day_use_hours.end');
            $arrival = $startDay->copy()->setTimeFromTimeString($start);
            $departure = $startDay->copy()->setTimeFromTimeString($end);
        } else {
            $start = config('resort.stay.overnight_hours.check_in');
            $end = config('resort.stay.overnight_hours.check_out');
            $arrival = $startDay->copy()->setTimeFromTimeString($start);
            $departure = $endDay->copy()->setTimeFromTimeString($end);
        }

        return new self(
            $arrival,
            $departure,
            $preset,
            $startDay->toDateString(),
            $endDay->toDateString(),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'check_in' => $this->checkInDate,
            'check_out' => $this->checkOutDate,
            'arrival' => $this->arrival->format('Y-m-d H:i'),
            'departure' => $this->departure->format('Y-m-d H:i'),
            'preset' => $this->preset,
        ];
    }
}
