<?php

namespace App\Support;

use App\Models\Amenity;
use Illuminate\Support\Collection;

/**
 * Stay-wide inclusives shown under the Rooms and Packages listings.
 */
final class ListingInclusives
{
    /**
     * Amenities every listed room includes. Names match AmenitySeeder / FacilitySeeder.
     *
     * @var list<string>
     */
    public const SHARED_ROOM_AMENITY_NAMES = [
        'Free WiFi',
        'Air Conditioning',
        'Hot/Cold Shower',
        'Bath Towels',
        'Coffee Cups',
        'Swimming Pool Access',
    ];

    /**
     * Short supporting copy per shared room amenity.
     *
     * @var array<string, string>
     */
    private const ROOM_DETAILS = [
        'Free WiFi' => 'Fibre, resort-wide',
        'Air Conditioning' => 'In every guest room',
        'Hot/Cold Shower' => 'In every bathroom',
        'Bath Towels' => 'Provided for your stay',
        'Coffee Cups' => 'Ready in-room',
        'Swimming Pool Access' => 'Three spring pools',
    ];

    /**
     * @param  iterable<int, mixed>|null  $facilities
     * @return list<array{icon: string, title: string, detail: string}>
     */
    public static function for(string $listingType, ?iterable $facilities = null): array
    {
        return match ($listingType) {
            'packages' => self::packages(),
            default => self::rooms($facilities),
        };
    }

    /**
     * Six inclusions shared by every room — not room-specific extras.
     *
     * @param  iterable<int, mixed>|null  $facilities
     * @return list<array{icon: string, title: string, detail: string}>
     */
    public static function rooms(?iterable $facilities = null): array
    {
        $catalog = self::amenityCatalog($facilities);
        $items = [];

        foreach (self::SHARED_ROOM_AMENITY_NAMES as $name) {
            $amenity = $catalog->get(mb_strtolower($name));
            $items[] = [
                'icon' => self::iconFor($amenity instanceof Amenity ? $amenity->icon : null, $name),
                'title' => $name,
                'detail' => self::ROOM_DETAILS[$name] ?? 'Available with your stay',
            ];
        }

        return $items;
    }

    /**
     * Package listing copy from amenities shared by every flyer package.
     *
     * @return list<array{icon: string, title: string, detail: string}>
     */
    public static function packages(): array
    {
        return [
            ['icon' => 'wifi', 'title' => 'Free Wi-Fi', 'detail' => 'On every package'],
            ['icon' => 'water', 'title' => 'Private pool', 'detail' => 'Included with Package I and II'],
            ['icon' => 'hall', 'title' => 'Function hall', 'detail' => 'Private hall or Sapphire Hall'],
            ['icon' => 'parking', 'title' => 'Free parking', 'detail' => 'Exclusive villa grounds'],
            ['icon' => 'villa', 'title' => 'Exclusive villa use', 'detail' => 'Grounds reserved with your booking'],
            ['icon' => 'grill', 'title' => 'Use of grill', 'detail' => 'Outdoor cooking included'],
        ];
    }

    /**
     * @return list<array{icon: string, title: string, detail: string}>
     */
    public static function canonicalRooms(): array
    {
        return self::rooms();
    }

    /**
     * @param  iterable<int, mixed>|null  $facilities
     * @return Collection<string, Amenity>
     */
    private static function amenityCatalog(?iterable $facilities): Collection
    {
        if ($facilities === null) {
            return collect();
        }

        return Collection::make($facilities)
            ->filter(fn ($facility) => is_object($facility) && isset($facility->amenities))
            ->flatMap(function ($facility) {
                return $facility->amenities instanceof Collection
                    ? $facility->amenities
                    : collect();
            })
            ->filter(fn ($amenity) => $amenity instanceof Amenity && filled($amenity->name))
            ->keyBy(fn (Amenity $amenity) => mb_strtolower($amenity->name));
    }

    private static function iconFor(?string $icon, string $name): string
    {
        $normalized = strtolower(trim((string) $icon));
        $aliases = [
            'wifi' => 'wifi',
            'snowflake' => 'snowflake',
            'car' => 'parking',
            'parking' => 'parking',
            'water' => 'water',
            'coffee' => 'coffee',
            'shower' => 'shower',
            'towel' => 'towel',
            'bottle' => 'bottle',
            'bath' => 'bath',
            'sparkles' => 'sparkles',
        ];

        if (isset($aliases[$normalized])) {
            return $aliases[$normalized];
        }

        $haystack = strtolower($name);

        return match (true) {
            str_contains($haystack, 'wifi') || str_contains($haystack, 'wi-fi') => 'wifi',
            str_contains($haystack, 'air') || str_contains($haystack, 'condition') => 'snowflake',
            str_contains($haystack, 'shower') => 'shower',
            str_contains($haystack, 'towel') => 'towel',
            str_contains($haystack, 'coffee') => 'coffee',
            str_contains($haystack, 'water') && str_contains($haystack, 'bottle') => 'bottle',
            str_contains($haystack, 'pool') => 'water',
            str_contains($haystack, 'park') => 'parking',
            default => 'sparkles',
        };
    }
}
