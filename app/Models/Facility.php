<?php

namespace App\Models;

use App\Enums\FacilityStatus;
use Database\Factories\FacilityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Facility extends Model
{
    /** @use HasFactory<FacilityFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'facility_type_id',
        'name',
        'slug',
        'description',
        'exclusions',
        'cleaning_required_at',
        'cleaning_completed_at',
        'capacity',
        'max_capacity',
        'status',
        'is_active',
        'sort_order',
        'package_family',
        'stay_category',
        'stay_start_time',
        'stay_end_time',
        'stay_spans_midnight',
        'is_listed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cleaning_required_at' => 'datetime',
            'cleaning_completed_at' => 'datetime',
            'status' => FacilityStatus::class,
            'is_active' => 'boolean',
            'package_family' => \App\Enums\PackageFamily::class,
            'stay_category' => \App\Enums\StayCategory::class,
            'stay_spans_midnight' => 'boolean',
            'is_listed' => 'boolean',
        ];
    }

    protected function baseRateUnit(): Attribute
    {
        return Attribute::get(fn (?string $value): ?string => $value === null ? null : \App\Enums\RateUnit::normalize($value));
    }

    /**
     * @return BelongsTo<FacilityType, $this>
     */
    public function facilityType(): BelongsTo
    {
        return $this->belongsTo(FacilityType::class);
    }

    /**
     * @return BelongsToMany<Amenity, $this>
     */
    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class);
    }

    /**
     * @return HasMany<Rate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(Rate::class);
    }

    /**
     * @return HasMany<GalleryImage, $this>
     */
    public function galleryImages(): HasMany
    {
        return $this->hasMany(GalleryImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * For packages: the component entries linking to constituent facilities.
     *
     * @return HasMany<PackageComponent, $this>
     */
    public function packageComponents(): HasMany
    {
        return $this->hasMany(PackageComponent::class, 'package_facility_id');
    }

    /**
     * For component facilities: the packages that include this facility.
     *
     * @return BelongsToMany<Facility, $this>
     */
    public function partOfPackages(): BelongsToMany
    {
        return $this->belongsToMany(
            Facility::class,
            'package_components',
            'component_facility_id',
            'package_facility_id'
        )->withTrashed();
    }

    /**
     * Returns the effective maximum capacity.
     * Falls back to standard capacity if max_capacity is not set.
     */
    public function effectiveMaxCapacity(): ?int
    {
        return $this->max_capacity ?? $this->capacity;
    }

    /**
     * Category shared by comparable units, e.g. "Superior" from "Superior (Room 01)".
     */
    public function listingCategory(): string
    {
        $name = trim($this->name);

        if (preg_match('/^(.*?)\s*\((?:Room\s+)?[^)]+\)\s*$/iu', $name, $matches) && trim($matches[1]) !== '') {
            return trim($matches[1]);
        }

        return $name;
    }

    /**
     * The unit identifier in parentheses, e.g. "Room 01".
     */
    public function unitLabel(): ?string
    {
        if (preg_match('/\(([^)]+)\)\s*$/u', $this->name, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    public function includedGuestCount(): int
    {
        return max(1, (int) $this->capacity);
    }

    public function maxGuestCount(): int
    {
        return max($this->includedGuestCount(), (int) ($this->effectiveMaxCapacity() ?? $this->includedGuestCount()));
    }

    /**
     * Sleeping setup inferred from the room category and included occupancy.
     * Halls, packages, and cottages have no bed layout to advertise.
     */
    public function bedSetupSummary(): ?string
    {
        if (! $this->isRoom()) {
            return null;
        }

        $category = mb_strtolower($this->listingCategory());
        $beds = $this->includedGuestCount();

        if (str_contains($category, 'dorm')) {
            return $beds === 1 ? '1 dorm bed' : $beds.' dorm beds';
        }

        if ($beds <= 2 && ! str_contains($category, 'family') && ! str_contains($category, 'barkada')) {
            return '1 bed';
        }

        return $beds === 1 ? '1 bed' : $beds.' beds';
    }

    public function occupancySummary(): string
    {
        return $this->includedGuestCount().' included · max '.$this->maxGuestCount();
    }

    public function extraPersonRate(): ?Rate
    {
        if (! $this->relationLoaded('rates')) {
            return $this->rates()
                ->where('type', \App\Enums\RateType::ExtraPerson->value)
                ->where('is_active', true)
                ->first();
        }

        return $this->rates->first(
            fn (Rate $rate) => $rate->type === \App\Enums\RateType::ExtraPerson && $rate->is_active
        );
    }

    public function isRoom(): bool
    {
        $this->loadMissing('facilityType');

        return $this->facilityType?->slug === \App\Enums\FacilityType::Room->value;
    }

    public function isCottage(): bool
    {
        $this->loadMissing('facilityType');

        return $this->facilityType?->slug === \App\Enums\FacilityType::Cottage->value;
    }

    public function isPackage(): bool
    {
        $this->loadMissing('facilityType');

        return $this->facilityType?->slug === \App\Enums\FacilityType::ResortPackage->value;
    }

    public function isFunctionHall(): bool
    {
        $this->loadMissing('facilityType');

        return $this->facilityType?->slug === \App\Enums\FacilityType::FunctionHall->value;
    }

    /**
     * The resort's published catalog of a facility type.
     *
     * `is_active` and `is_listed` answer two different questions, and a guest
     * catalog needs both: inactive means withdrawn from sale, unlisted means
     * inventory-only — sold *inside* a package (the Private Function Hall
     * belongs to Package I) but never offered as a card or a suggestion of its
     * own. Querying on `is_active` alone is how it reached /halls.
     *
     * @param  Builder<Facility>  $query
     * @return Builder<Facility>
     */
    public function scopeListed(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_listed', true);
    }

    /**
     * Packages without bookable resource components cannot be reserved online.
     * Amenities are descriptive only and do not occupy inventory.
     * Unlisted facilities (e.g. Private Function Hall) are inventory-only.
     */
    public function isOnlineBookable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (! $this->is_listed) {
            return false;
        }

        if ($this->isPackage()) {
            if ($this->relationLoaded('packageComponents')) {
                return $this->packageComponents->isNotEmpty();
            }

            return $this->packageComponents()->exists();
        }

        return true;
    }

    public function isFlyerPackage(): bool
    {
        return $this->isPackage() && $this->package_family !== null && $this->stay_category !== null;
    }

    /**
     * @return list<int>
     */
    public function paxTiers(): array
    {
        $tiers = $this->relationLoaded('rates')
            ? $this->rates
                ->filter(fn (Rate $rate): bool => $rate->type === \App\Enums\RateType::Base && $rate->is_active && $rate->max_occupancy)
                ->pluck('max_occupancy')
            : $this->rates()
                ->where('type', \App\Enums\RateType::Base->value)
                ->where('is_active', true)
                ->whereNotNull('max_occupancy')
                ->pluck('max_occupancy');

        return $tiers
            ->map(fn ($value): int => (int) $value)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function hasPaxTiers(): bool
    {
        return count($this->paxTiers()) > 1;
    }

    public function rateForPaxTier(?int $paxTier): ?Rate
    {
        $baseRates = $this->relationLoaded('rates')
            ? $this->rates->filter(fn (Rate $rate): bool => $rate->type === \App\Enums\RateType::Base && $rate->is_active)
            : $this->rates()->where('type', \App\Enums\RateType::Base->value)->where('is_active', true)->get();

        if ($paxTier !== null) {
            $tierRate = $baseRates->first(fn (Rate $rate): bool => (int) $rate->max_occupancy === $paxTier);

            if ($tierRate) {
                return $tierRate;
            }
        }

        return $baseRates->sortBy(fn (Rate $rate): int => (int) ($rate->max_occupancy ?? PHP_INT_MAX))->first();
    }

    public function stayStartTimeString(): ?string
    {
        return $this->formatStayTime($this->stay_start_time);
    }

    public function stayEndTimeString(): ?string
    {
        return $this->formatStayTime($this->stay_end_time);
    }

    /**
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    public function occupancyWindowForDate(\Carbon\CarbonInterface $startDate, ?\Carbon\CarbonInterface $endDate = null): array
    {
        $start = $this->stayStartTimeString() ?? '09:00';
        $end = $this->stayEndTimeString() ?? '17:00';
        $arrival = \Carbon\Carbon::parse($startDate)->copy()->setTimeFromTimeString($start);

        if ($this->stay_spans_midnight) {
            $departureDay = $endDate !== null && ! $endDate->isSameDay($startDate)
                ? \Carbon\Carbon::parse($endDate)
                : $arrival->copy()->addDay();
            $departure = $departureDay->copy()->setTimeFromTimeString($end);
        } else {
            $departure = $arrival->copy()->setTimeFromTimeString($end);
        }

        return [$arrival, $departure];
    }

    public function stayScheduleLabel(): ?string
    {
        $start = $this->stayStartTimeString();
        $end = $this->stayEndTimeString();

        if ($start === null || $end === null) {
            return null;
        }

        $startLabel = \Carbon\Carbon::createFromFormat('H:i', $start)->format('g:i A');
        $endLabel = \Carbon\Carbon::createFromFormat('H:i', $end)->format('g:i A');

        if ($this->stay_spans_midnight) {
            return $startLabel.'–'.$endLabel.' next day';
        }

        return $startLabel.'–'.$endLabel;
    }

    private function formatStayTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('H:i');
        }

        return substr((string) $value, 0, 5);
    }

    /**
     * Facilities whose availability is coupled to this one: a package is coupled
     * to its components, and a component is coupled to its parent packages.
     * Booking either side takes the other off the market.
     *
     * @return Collection<int, Facility>
     */
    public function contendingFacilities(): Collection
    {
        if ($this->isPackage()) {
            return $this->packageComponents()
                ->with('componentFacility.facilityType')
                ->get()
                ->pluck('componentFacility')
                ->filter()
                ->values();
        }

        return $this->partOfPackages()->with('facilityType')->get()->values();
    }
}
