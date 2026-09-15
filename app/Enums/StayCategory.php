<?php

namespace App\Enums;

enum StayCategory: string
{
    case DayUse = 'day_use';
    case NightUse = 'night_use';
    case Overnight = 'overnight';

    public function label(): string
    {
        return match ($this) {
            self::DayUse => 'Day Use',
            self::NightUse => 'Night Use',
            self::Overnight => 'Overnight Stay',
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
