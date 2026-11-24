<?php

namespace App\Enums;

enum FacilityStatus: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Unavailable => 'Unavailable',
            self::Maintenance => 'Under Maintenance',
        };
    }

    /** @return 'success'|'warning'|'danger'|'info'|'neutral'|'muted' */
    public function tone(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Unavailable => 'danger',
            self::Maintenance => 'muted',
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
