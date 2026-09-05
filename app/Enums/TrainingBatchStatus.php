<?php

namespace App\Enums;

enum TrainingBatchStatus: string
{
    case Planned = 'planned';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Ongoing => 'Ongoing',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Statuses that hold the batch's facilities against guest bookings.
     * A finished or cancelled cohort releases its rooms.
     *
     * @return list<string>
     */
    public static function blocking(): array
    {
        return [self::Planned->value, self::Ongoing->value];
    }

    public function isBlocking(): bool
    {
        return in_array($this->value, self::blocking(), true);
    }

    /**
     * Statuses that still accept applications.
     */
    public function acceptsApplications(): bool
    {
        return $this === self::Planned || $this === self::Ongoing;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
