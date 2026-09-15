<?php

namespace App\Enums;

enum PackageFamily: string
{
    case PackageI = 'package_i';
    case PackageII = 'package_ii';

    public function label(): string
    {
        return match ($this) {
            self::PackageI => 'Package I — Private Pool + Function Hall',
            self::PackageII => 'Package II — Sapphire Hall + Private Pool with Jacuzzi',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::PackageI => 'Package I',
            self::PackageII => 'Package II',
        };
    }

    public function subtitle(): string
    {
        return match ($this) {
            self::PackageI => 'Private Pool + Function Hall',
            self::PackageII => 'Sapphire Hall + Private Pool with Jacuzzi',
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
