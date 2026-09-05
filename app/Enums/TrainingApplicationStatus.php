<?php

namespace App\Enums;

enum TrainingApplicationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Waitlisted = 'waitlisted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Waitlisted => 'Waitlisted',
            self::Rejected => 'Rejected',
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
