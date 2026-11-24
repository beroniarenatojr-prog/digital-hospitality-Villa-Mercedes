<?php

namespace App\Enums;

enum ServiceAvailability: string
{
    case Available = 'available';
    case FullyBooked = 'fully_booked';
    case Unavailable = 'unavailable';
    case TemporarilyClosed = 'temporarily_closed';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::FullyBooked => 'Fully booked',
            self::Unavailable => 'Unavailable',
            self::TemporarilyClosed => 'Temporarily closed',
        };
    }
}
