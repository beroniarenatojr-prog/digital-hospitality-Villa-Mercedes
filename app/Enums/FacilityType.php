<?php

namespace App\Enums;

enum FacilityType: string
{
    case Room = 'room';
    case FunctionHall = 'function_hall';
    case ResortPackage = 'resort_package';

    public function label(): string
    {
        return match ($this) {
            self::Room => 'Room',
            self::FunctionHall => 'Function Hall',
            self::ResortPackage => 'Resort Package',
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
