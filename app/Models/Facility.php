<?php

namespace App\Models;

use App\Enums\FacilityStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Facility extends Model
{
    /** @use HasFactory<\Database\Factories\FacilityFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'facility_type_id',
        'name',
        'slug',
        'description',
        'capacity',
        'max_capacity',
        'status',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FacilityStatus::class,
            'is_active' => 'boolean',
        ];
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
        return $this->hasMany(GalleryImage::class);
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
        );
    }

    /**
     * Returns the effective maximum capacity.
     * Falls back to standard capacity if max_capacity is not set.
     */
    public function effectiveMaxCapacity(): int
    {
        return $this->max_capacity ?? $this->capacity;
    }

    public function isPackage(): bool
    {
        $this->loadMissing('facilityType');

        return $this->facilityType?->slug === \App\Enums\FacilityType::ResortPackage->value;
    }

    /**
     * Facilities whose availability is coupled to this one: a package is coupled
     * to its components, and a component is coupled to its parent packages.
     * Booking either side takes the other off the market.
     *
     * @return \Illuminate\Support\Collection<int, Facility>
     */
    public function contendingFacilities(): \Illuminate\Support\Collection
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
