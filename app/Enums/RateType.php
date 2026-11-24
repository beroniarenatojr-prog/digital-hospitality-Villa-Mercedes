<?php

namespace App\Enums;

enum RateType: string
{
    case Base = 'base';
    case AdditionalCharge = 'additional_charge';
    case Seasonal = 'seasonal';
    case ExtraPerson = 'extra_person';

    /** @return array<string, string> */
    public static function hints(): array
    {
        return [
            self::Base->value => 'The standard rate used when no seasonal rate matches. Every bookable facility needs one.',
            self::Seasonal->value => 'A rate that replaces the base rate during a date range. Effective dates are required.',
            self::AdditionalCharge->value => 'An extra charge such as early check-in or late check-out. Added on top of the base or seasonal rate.',
            self::ExtraPerson->value => 'Charged for each guest above the standard capacity, up to the maximum capacity.',
        ];
    }

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
