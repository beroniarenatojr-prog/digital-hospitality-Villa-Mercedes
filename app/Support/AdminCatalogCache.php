<?php

namespace App\Support;

use App\Models\Facility;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class AdminCatalogCache
{
    private const BOOKABLE_KEY = 'admin:bookable-facilities:v1';

    /**
     * @return Collection<int, Facility>
     */
    public static function bookableFacilities(): Collection
    {
        /** @var Collection<int, Facility> $facilities */
        $facilities = Cache::remember(self::BOOKABLE_KEY, now()->addMinutes(5), function (): Collection {
            return Facility::query()
                ->with(['facilityType', 'packageComponents', 'galleryImages', 'amenities', 'rates'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->filter(fn (Facility $facility) => $facility->isOnlineBookable())
                ->values();
        });

        return $facilities;
    }

    public static function forget(): void
    {
        Cache::forget(self::BOOKABLE_KEY);
    }
}
