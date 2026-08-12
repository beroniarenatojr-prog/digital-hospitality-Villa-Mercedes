<?php

namespace App\Enums;

enum Role: string
{
    case Administrator = 'Administrator';
    case Customer = 'Customer';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
