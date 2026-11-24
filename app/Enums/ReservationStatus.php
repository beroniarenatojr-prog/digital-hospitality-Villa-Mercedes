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
     * The colour this state wears, so `x-badge` never has to guess it from the
     * spelling of the label. Renaming a label must not silently repaint a state.
     *
     * @return 'success'|'warning'|'danger'|'info'|'neutral'|'muted'
     */
    public function tone(): string
    {
        return match ($this) {
            self::Approved, self::Completed => 'success',
            self::Pending => 'warning',
            self::Rejected, self::Cancelled, self::Expired => 'danger',
            self::Rescheduled => 'muted',
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
