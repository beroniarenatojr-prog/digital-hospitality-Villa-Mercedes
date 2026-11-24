<?php

namespace App\Services;

use App\Enums\FacilityStatus;
use App\Enums\FacilityType as FacilityTypeEnum;
use App\Enums\RateType;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\GalleryImage;
use App\Models\Rate;
use App\Support\PublicHomeCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class FacilityQueryService
{
    /**
     * Retrieve active facilities for a specific facility type.
     * Eager loads amenities, base rates, and gallery images.
     */
    public function getActiveFacilitiesByType(string $typeSlug): Collection
    {
        return $this->activeByType($typeSlug)
            ->with($this->publicRelations())
            ->get();
    }

    /**
     * Compact catalog used by availability search: no amenities, no full gallery.
     */
    public function getSearchFacilitiesByType(string $typeSlug): Collection
    {
        return $this->activeByType($typeSlug)
            ->select('facilities.*')
            ->selectSub($this->coverImagePathSubquery(), 'cover_image_path')
            ->with($this->searchRelations())
            ->get();
    }

    /**
     * Published name/rate rows for the public chat catalog. Caps at a handful
     * so the assistant does not hydrate the entire listing.
     */
    public function getCatalogSummaries(string $typeSlug, int $limit = 4): Collection
    {
        return $this->activeByType($typeSlug)
            ->select(['id', 'name', 'slug', 'capacity', 'max_capacity', 'status', 'is_active', 'facility_type_id'])
            ->with([
                'rates' => function ($query) {
                    $query->where('type', RateType::Base->value)->where('is_active', true);
                },
            ])
            ->limit($limit)
            ->get();
    }

    /**
     * Public listing catalog with optional capacity filter and sort.
     *
     * @param  array{min_capacity?: int, sort?: string}  $filters
     */
    public function listPublicFacilities(string $typeSlug, array $filters = []): Collection
    {
        $facilities = $this->activeByType($typeSlug)
            ->select('facilities.*')
            ->selectSub($this->coverImagePathSubquery(), 'cover_image_path')
            ->withCount([
                'galleryImages as gallery_photo_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->with($this->listingRelations())
            ->get();

        $minCapacity = max(0, (int) ($filters['min_capacity'] ?? 0));
        $sort = $filters['sort'] ?? 'recommended';

        if ($minCapacity > 0) {
            $facilities = $facilities->filter(
                fn (Facility $facility) => $facility->effectiveMaxCapacity() >= $minCapacity
            )->values();
        }

        return match ($sort) {
            'price-asc' => $facilities->sortBy(fn (Facility $facility) => $this->listingPrice($facility))->values(),
            'price-desc' => $facilities->sortByDesc(fn (Facility $facility) => $this->listingPrice($facility))->values(),
            'capacity' => $facilities->sortByDesc(fn (Facility $facility) => $facility->effectiveMaxCapacity())->values(),
            default => $facilities,
        };
    }

    /**
     * The order the picker offers the spaces that are free: by what the guest
     * asked for, rather than by name.
     *
     * A list sorted A–Z is sorted by the one thing the guest did not mention. So
     * the three things they did mention decide the order between them, in the order
     * they would be raised:
     *
     *  1. **Party size.** A space that takes the whole party inside its included
     *     count leads, because the rate on its card is then the rate they pay; one
     *     that would add an extra-person charge follows, however cheap it looks.
     *     A space that publishes no included count (a hall, a cottage) is not
     *     treated as charging for one.
     *  2. **Rate** — the published starting rate the card prints, cheapest first.
     *     A space with no published rate cannot be compared with one that has, so
     *     it goes last rather than pretending to be free.
     *  3. **Photos** — a card a guest can look at before one they cannot.
     *
     * The name is the last word on purpose: pagination is a promise that page two
     * follows page one, so two spaces that are equal on every signal they offered
     * must still come back in the same order every time.
     *
     * @param  Collection<int, Facility>|SupportCollection<int, Facility>  $facilities
     * @return SupportCollection<int, Facility>
     */
    public function rankForGuests(Collection|SupportCollection $facilities, ?int $guests): SupportCollection
    {
        return collect($facilities->all())
            ->sort(fn (Facility $a, Facility $b): int => $this->rankAgainst($a, $b, $guests))
            ->values();
    }

    private function rankAgainst(Facility $a, Facility $b, ?int $guests): int
    {
        return $this->includedFitRank($a, $guests) <=> $this->includedFitRank($b, $guests)
            ?: $this->listingPrice($a) <=> $this->listingPrice($b)
            ?: $this->photoRank($a) <=> $this->photoRank($b)
            ?: strcasecmp($a->name, $b->name);
    }

    /** 0 when the party fits what this space includes, 1 when it would be charged for. */
    private function includedFitRank(Facility $facility, ?int $guests): int
    {
        $included = $facility->capacity;

        if ($guests === null || $included === null) {
            return 0;
        }

        return $guests <= (int) $included ? 0 : 1;
    }

    /** 0 when the card shows a photo, 1 when it has none to show. */
    private function photoRank(Facility $facility): int
    {
        // The picker eager-loads the gallery, and this order is only asked for
        // there; `relationLoaded` keeps a missing relation from turning one sort
        // into a query per comparison.
        return $facility->relationLoaded('galleryImages') && $facility->galleryImages->isNotEmpty() ? 0 : 1;
    }

    /**
     * Group comparable room units by shared listing category
     * (e.g. "Superior" from "Superior (Room 01)").
     *
     * @param  Collection<int, Facility>|SupportCollection<int, Facility>  $facilities
     * @return SupportCollection<string, SupportCollection<int, Facility>>
     */
    public function groupByListingCategory(Collection|SupportCollection $facilities): SupportCollection
    {
        return $facilities->groupBy(fn (Facility $facility) => $facility->listingCategory());
    }

    /**
     * Group flyer catalog packages by PackageFamily value.
     * Facilities without a package_family are omitted (handled as ungrouped).
     *
     * @param  Collection<int, Facility>|SupportCollection<int, Facility>  $facilities
     * @return SupportCollection<string, SupportCollection<int, Facility>>
     */
    public function groupFlyerPackages(Collection|SupportCollection $facilities): SupportCollection
    {
        return $facilities
            ->filter(fn (Facility $facility) => $facility->package_family !== null)
            ->groupBy(fn (Facility $facility) => $facility->package_family->value);
    }

    private function listingPrice(Facility $facility): float
    {
        $amount = $facility->rates->firstWhere('type', RateType::Base)?->amount
            ?? $facility->rates->first()?->amount;

        return $amount === null ? PHP_FLOAT_MAX : (float) $amount;
    }

    /**
     * Retrieve a deterministic selection of featured facilities for the home page.
     */
    public function getFeaturedFacilitiesByType(string $typeSlug, int $limit = 3): Collection
    {
        return $this->activeByType($typeSlug)
            ->with($this->publicRelations())
            ->take($limit)
            ->get();
    }

    /**
     * The three images used by the service cards on the home page.
     *
     * Each type is queried independently so the database returns one row per
     * card instead of loading every active facility merely to find its first
     * image. The result is public and invalidated by model observers.
     *
     * @return array<string, string|null>
     */
    public function getHomeServiceMedia(): array
    {
        return PublicHomeCache::serviceMedia(function (): array {
            $typeIds = $this->facilityTypeIds();
            $media = [];

            foreach (FacilityTypeEnum::cases() as $type) {
                $typeId = $typeIds[$type->value] ?? null;

                if ($typeId === null) {
                    $media[$type->value] = null;

                    continue;
                }

                $facility = Facility::query()
                    ->listed()
                    ->where('facility_type_id', $typeId)
                    ->select('id')
                    ->selectSub($this->coverImagePathSubquery(), 'cover_image_path')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->first();

                $media[$type->value] = $facility?->cover_image_path;
            }

            return $media;
        });
    }

    /**
     * A compact, cacheable set of rooms for the deferred home-page section.
     * No amenities, package components, or full image galleries are fetched.
     *
     * @return SupportCollection<int, Facility>
     */
    public function getHomeFeaturedRooms(int $limit = 3): SupportCollection
    {
        return PublicHomeCache::featuredRooms(function () use ($limit): SupportCollection {
            $featuredSlugs = [
                'deluxe-room-26',
                'standard-family-room-21',
                'superior-barkada-room-10',
            ];

            $typeId = $this->facilityTypeId(FacilityTypeEnum::Room->value);

            if ($typeId === null) {
                return collect();
            }

            return Facility::query()
                ->select([
                    'id',
                    'name',
                    'slug',
                    'description',
                    'capacity',
                    'max_capacity',
                    'status',
                ])
                ->selectSub($this->coverImagePathSubquery(), 'cover_image_path')
                ->selectSub($this->baseRateSubquery('amount'), 'base_rate_amount')
                ->selectSub($this->baseRateSubquery('unit'), 'base_rate_unit')
                ->listed()
                ->where('facility_type_id', $typeId)
                ->where('status', FacilityStatus::Available->value)
                ->orderByRaw(
                    'CASE WHEN slug IN (?, ?, ?) THEN 0 ELSE 1 END, FIELD(slug, ?, ?, ?), sort_order, id',
                    [...$featuredSlugs, ...$featuredSlugs]
                )
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Eager load necessary relationships for the public detail view.
     */
    public function loadFacilityDetails(Facility $facility): Facility
    {
        $facility->load([
            ...$this->publicRelations(),
            'packageComponents.componentFacility',
            'rates' => function ($query) {
                $query->where('is_active', true);
            },
        ]);

        return $facility;
    }

    /**
     * The published catalog of one facility type.
     *
     * Everything this class hands to a guest goes through here, so the
     * catalog contract lives in one name rather than in each caller's memory:
     * `Facility::listed()` — active and listed. Filtering on `is_active` alone
     * put the inventory-only Private Function Hall in front of guests as a
     * bookable card of its own.
     *
     * @return Builder<Facility>
     */
    private function activeByType(string $typeSlug): Builder
    {
        $typeId = $this->facilityTypeId($typeSlug);

        return Facility::query()
            ->listed()
            ->where('facility_type_id', $typeId ?? 0)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    private function facilityTypeId(string $typeSlug): ?int
    {
        $id = FacilityType::query()->where('slug', $typeSlug)->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @return array<string, int>
     */
    private function facilityTypeIds(): array
    {
        return FacilityType::query()
            ->whereIn('slug', array_column(FacilityTypeEnum::cases(), 'value'))
            ->pluck('id', 'slug')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return array<string, \Closure|string>
     */
    private function publicRelations(): array
    {
        return [
            ...$this->listingRelations(),
            'galleryImages' => function ($query) {
                $query->where('is_active', true);
            },
        ];
    }

    /**
     * @return array<string, \Closure|string>
     */
    private function listingRelations(): array
    {
        return [
            'facilityType',
            'amenities',
            // Detail-first cards still show package composition where relevant.
            'packageComponents',
            // Both rates the card can print. Loading only the base rate made the
            // loaded relation an incomplete answer, so Facility::extraPersonRate()
            // found nothing in it and every listing card silently hid the
            // extra-person fee a guest is charged at check-in.
            'rates' => function ($query) {
                $query->whereIn('type', [RateType::Base->value, RateType::ExtraPerson->value])
                    ->where('is_active', true);
            },
        ];
    }

    /**
     * @return array<string, \Closure|string>
     */
    private function searchRelations(): array
    {
        return [
            'facilityType',
            'packageComponents.componentFacility',
            'partOfPackages',
            'rates' => function ($query) {
                $query->where('is_active', true);
            },
        ];
    }

    private function coverImagePathSubquery(): Builder
    {
        return GalleryImage::query()
            ->select('image_path')
            ->whereColumn('facility_id', 'facilities.id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(1);
    }

    private function baseRateSubquery(string $column): Builder
    {
        return Rate::query()
            ->select($column)
            ->whereColumn('facility_id', 'facilities.id')
            ->where('type', RateType::Base->value)
            ->where('is_active', true)
            ->orderBy('id')
            ->limit(1);
    }
}
