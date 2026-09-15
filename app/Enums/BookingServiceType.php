<?php

namespace App\Enums;

enum BookingServiceType: string
{
    case Room = 'room';
    case FunctionHall = 'function_hall';
    case ResortPackage = 'resort_package';
    case Cottage = 'cottage';
    case Pool = 'pool';

    public function label(): string
    {
        return match ($this) {
            self::Room => 'Rooms',
            self::FunctionHall => 'Function halls',
            self::ResortPackage => 'Packages',
            self::Cottage => 'Cottages',
            self::Pool => 'Resort pools',
        };
    }

    public function facilityType(): ?FacilityType
    {
        return match ($this) {
            self::Room => FacilityType::Room,
            self::FunctionHall => FacilityType::FunctionHall,
            self::ResortPackage => FacilityType::ResortPackage,
            self::Cottage => FacilityType::Cottage,
            self::Pool => null,
        };
    }

    public function listingRouteName(): string
    {
        return match ($this) {
            self::Room => 'rooms',
            self::FunctionHall => 'halls',
            self::ResortPackage => 'packages',
            self::Cottage => 'cottages',
            self::Pool => 'pools',
        };
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function listingUrl(array $query = []): string
    {
        return route($this->listingRouteName(), $query);
    }

    /**
     * Other bookable types to suggest when the selected service is not available.
     *
     * @return list<self>
     */
    public function alternativeTypes(): array
    {
        return match ($this) {
            self::Pool => [self::Cottage, self::ResortPackage, self::Room, self::FunctionHall],
            self::Cottage => [self::Room, self::ResortPackage, self::FunctionHall],
            self::Room => [self::Cottage, self::ResortPackage, self::FunctionHall],
            self::FunctionHall => [self::ResortPackage, self::Room, self::Cottage],
            self::ResortPackage => [self::Room, self::FunctionHall, self::Cottage],
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
