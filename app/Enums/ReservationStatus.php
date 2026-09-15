<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Rescheduled = 'rescheduled';
    case Completed = 'completed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Confirmed',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Rescheduled => 'Rescheduled',
            self::Completed => 'Completed',
            self::Expired => 'Expired',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
