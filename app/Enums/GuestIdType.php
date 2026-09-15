<?php

namespace App\Enums;

enum GuestIdType: string
{
    case PhilSys = 'philsys';
    case Passport = 'passport';
    case DriversLicense = 'drivers_license';
    case Umid = 'umid';
    case Sss = 'sss';
    case Gsis = 'gsis';
    case Prc = 'prc';
    case Postal = 'postal';
    case Philhealth = 'philhealth';
    case Tin = 'tin';
    case Ofw = 'ofw';
    case SeniorCitizen = 'senior_citizen';
    case Pwd = 'pwd';

    public function label(): string
    {
        return match ($this) {
            self::PhilSys => 'PhilSys National ID',
            self::Passport => 'Philippine Passport',
            self::DriversLicense => 'Driver\'s License (LTO)',
            self::Umid => 'UMID',
            self::Sss => 'SSS ID',
            self::Gsis => 'GSIS eCard',
            self::Prc => 'PRC ID',
            self::Postal => 'Postal ID',
            self::Philhealth => 'PhilHealth ID',
            self::Tin => 'TIN ID',
            self::Ofw => 'OFW / OWWA ID',
            self::SeniorCitizen => 'Senior Citizen ID',
            self::Pwd => 'PWD ID',
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
