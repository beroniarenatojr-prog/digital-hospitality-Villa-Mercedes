<?php

namespace App\Enums;

enum Role: string
{
    case Administrator = 'Administrator';
    case Customer = 'Customer';
    case Owner = 'Owner';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
