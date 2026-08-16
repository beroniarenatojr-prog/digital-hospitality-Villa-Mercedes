<?php

namespace App\Services;

use App\Enums\RateType;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Collection;

class FacilityQueryService
{
    /**
     * Retrieve active facilities for a specific facility type.
     * Eager loads amenities, base rates, and gallery images.
     * 
     * @param string $typeSlug
     * @return Collection
     */
    public function getActiveFacilitiesByType(string $typeSlug): Collection
    {
        return Facility::whereHas('facilityType', function ($query) use ($typeSlug) {
            $query->where('slug', $typeSlug);
        })
        ->where('is_active', true)
        ->with([
            'amenities',
            'galleryImages',
            'rates' => function ($query) {
                $query->where('type', RateType::Base->value)->where('is_active', true);
            }
        ])
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get();
    }

    /**
     * Retrieve a deterministic selection of featured facilities for the home page.
     * 
     * @param string $typeSlug
     * @param int $limit
     * @return Collection
     */
    public function getFeaturedFacilitiesByType(string $typeSlug, int $limit = 3): Collection
    {
        return Facility::whereHas('facilityType', function ($query) use ($typeSlug) {
            $query->where('slug', $typeSlug);
        })
        ->where('is_active', true)
        ->with([
            'amenities',
            'galleryImages',
            'rates' => function ($query) {
                $query->where('type', RateType::Base->value)->where('is_active', true);
            }
        ])
        ->orderBy('sort_order')
        ->orderBy('id')
        ->take($limit)
        ->get();
    }

    /**
     * Eager load necessary relationships for the public detail view.
     * 
     * @param Facility $facility
     * @return Facility
     */
    public function loadFacilityDetails(Facility $facility): Facility
    {
        $facility->load([
            'amenities',
            'galleryImages',
            'packageComponents.componentFacility',
            'rates' => function ($query) {
                $query->where('is_active', true);
            }
        ]);

        return $facility;
    }
}
