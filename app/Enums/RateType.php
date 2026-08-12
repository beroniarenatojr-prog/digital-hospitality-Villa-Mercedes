<?php

namespace App\Enums;

enum RateType: string
{
    case Base = 'base';
    case AdditionalCharge = 'additional_charge';
    case Seasonal = 'seasonal';
    case ExtraPerson = 'extra_person';

    public function label(): string
    {
        return match ($this) {
            self::Base => 'Base Rate',
            self::AdditionalCharge => 'Additional Charge',
            self::Seasonal => 'Seasonal Rate',
            self::ExtraPerson => 'Extra Person Charge',
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
