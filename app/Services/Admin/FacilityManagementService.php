<?php

namespace App\Services\Admin;

use App\Enums\FacilityStatus;
use App\Enums\FacilityType;
use App\Enums\Role;
use App\Exceptions\Admin\PhotoLimitExceededException;
use App\Exceptions\Admin\UnauthorizedAdminActionException;
use App\Models\Amenity;
use App\Models\Facility;
use App\Models\FacilityType as FacilityTypeModel;
use App\Models\GalleryImage;
use App\Models\PackageComponent;
use App\Models\User;
use App\Services\PublicImageOptimizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FacilityManagementService
{
    public function __construct(
        private readonly PublicImageOptimizer $imageOptimizer
    ) {}

    /**
     * Ensure the actor is an Administrator.
     */
    private function authorizeAdmin(User $actor): void
    {
        if (! $actor->hasRole(Role::Administrator->value)) {
            throw new UnauthorizedAdminActionException('Only administrators can manage facilities.');
        }
    }

    /**
     * Create a new facility.
     */
    public function createFacility(User $actor, array $data): Facility
    {
        $this->authorizeAdmin($actor);

        return DB::transaction(function () use ($actor, $data) {
            $facility = new Facility;

            $facility->facility_type_id = $data['facility_type_id'];
            $facility->name = $data['name'];
            $facility->slug = $data['slug'] ?? Str::slug($data['name']);
            $facility->description = $data['description'] ?? null;
            $facility->capacity = $data['capacity'] ?? $data['base_capacity'] ?? null;
            $facility->max_capacity = array_key_exists('max_capacity', $data) ? $data['max_capacity'] : $facility->capacity;

            if (isset($data['status']) && $data['status'] instanceof FacilityStatus) {
                $facility->status = $data['status'];
            } else {
                $facility->status = FacilityStatus::Available;
            }

            $facility->is_active = $data['is_active'] ?? true;
            $facility->sort_order = $data['sort_order'] ?? 0;

            $facility->save();

            activity()
                ->causedBy($actor)
                ->performedOn($facility)
                ->event('facility_created')
                ->log("Facility '{$facility->name}' was created.");

            if (! empty($data['images'])) {
                $this->uploadImages($actor, $facility, $data['images']);
            }

            return $facility;
        });
    }

    /**
     * Update an existing facility.
     */
    public function updateFacility(User $actor, Facility $facility, array $data): Facility
    {
        $this->authorizeAdmin($actor);

        return DB::transaction(function () use ($actor, $facility, $data) {
            if (array_key_exists('facility_type_id', $data)) {
                $facility->facility_type_id = $data['facility_type_id'];
            }
            if (array_key_exists('name', $data)) {
                $facility->name = $data['name'];
            }
            if (array_key_exists('slug', $data)) {
                $facility->slug = $data['slug'];
            }
            if (array_key_exists('description', $data)) {
                $facility->description = $data['description'];
            }
            if (array_key_exists('capacity', $data)) {
                $facility->capacity = $data['capacity'];
            }
            if (array_key_exists('base_capacity', $data)) {
                $facility->capacity = $data['base_capacity'];
            }
            if (array_key_exists('max_capacity', $data)) {
                $facility->max_capacity = $data['max_capacity'];
            }
            if (array_key_exists('is_active', $data)) {
                $facility->is_active = $data['is_active'];
            }
            if (array_key_exists('sort_order', $data)) {
                $facility->sort_order = $data['sort_order'];
            }
            if (array_key_exists('status', $data) && $data['status'] instanceof FacilityStatus) {
                $facility->status = $data['status'];
            }

            $facility->save();

            activity()
                ->causedBy($actor)
                ->performedOn($facility)
                ->event('facility_updated')
                ->log("Facility '{$facility->name}' was updated.");

            if (! empty($data['captions'])) {
                $this->updateImageCaptions($facility, $data['captions']);
            }

            if (! empty($data['images'])) {
                $this->uploadImages($actor, $facility, $data['images']);
            }

            return $facility;
        });
    }

    /**
     * Change facility status explicitly.
     */
    public function changeStatus(User $actor, Facility $facility, FacilityStatus $status): Facility
    {
        $this->authorizeAdmin($actor);

        $facility->status = $status;
        $facility->save();

        activity()
            ->causedBy($actor)
            ->performedOn($facility)
            ->event('facility_status_changed')
            ->log("Facility '{$facility->name}' status changed to '{$status->label()}'.");

        return $facility;
    }

    /**
     * Synchronize amenities for a facility.
     */
    public function assignAmenities(User $actor, Facility $facility, array $amenityIds): void
    {
        $this->authorizeAdmin($actor);

        // Verify all amenities exist
        $existingCount = Amenity::whereIn('id', $amenityIds)->count();
        if ($existingCount !== count(array_unique($amenityIds))) {
            throw new InvalidArgumentException('One or more provided amenity IDs do not exist.');
        }

        DB::transaction(function () use ($actor, $facility, $amenityIds) {
            $facility->amenities()->sync($amenityIds);

            activity()
                ->causedBy($actor)
                ->performedOn($facility)
                ->event('amenities_assigned')
                ->log("Amenities synchronized for facility '{$facility->name}'.");
        });
    }

    /**
     * Safely delete a facility using SoftDeletes.
     */
    public function deleteFacility(User $actor, Facility $facility): void
    {
        $this->authorizeAdmin($actor);

        DB::transaction(function () use ($actor, $facility) {
            $facility->delete(); // Soft delete behavior natively applied by model

            activity()
                ->causedBy($actor)
                ->performedOn($facility)
                ->event('facility_deleted')
                ->log("Facility '{$facility->name}' was safely deleted.");
        });
    }

    /**
     * Synchronize bookable resource components for a package facility.
     */
    public function syncComponents(User $actor, Facility $package, array $componentFacilityIds): void
    {
        $this->authorizeAdmin($actor);

        // Ensure the facility is actually a package
        $packageType = FacilityTypeModel::where('slug', FacilityType::ResortPackage->value)->first();
        if (! $packageType || $package->facility_type_id !== $packageType->id) {
            throw new InvalidArgumentException('Only resort packages can have bookable resource components.');
        }

        // Prevent self-referential components
        $componentFacilityIds = array_unique($componentFacilityIds);
        if (in_array($package->id, $componentFacilityIds)) {
            throw new InvalidArgumentException('A package cannot include itself as a component.');
        }

        // Verify all component facilities exist and are not packages themselves
        $existingCount = Facility::whereIn('id', $componentFacilityIds)
            ->where('facility_type_id', '!=', $packageType->id)->count();
        if ($existingCount !== count($componentFacilityIds)) {
            throw new InvalidArgumentException('Package components must be existing individual facilities, not other packages.');
        }

        DB::transaction(function () use ($actor, $package, $componentFacilityIds) {
            // Delete existing and re-create
            PackageComponent::where('package_facility_id', $package->id)->delete();

            foreach ($componentFacilityIds as $componentId) {
                PackageComponent::create([
                    'package_facility_id' => $package->id,
                    'component_facility_id' => $componentId,
                ]);
            }

            activity()
                ->causedBy($actor)
                ->performedOn($package)
                ->event('package_components_synced')
                ->log("Package components synchronized for '{$package->name}'.");
        });
    }

    /**
     * Upload images and attach to facility.
     */
    public function uploadImages(User $actor, Facility $facility, array $images): void
    {
        $this->authorizeAdmin($actor);

        $existing = $facility->galleryImages()->count();
        $incoming = count($images);

        if ($existing + $incoming > GalleryImage::MAX_COUNT) {
            throw new PhotoLimitExceededException(
                'Each facility can show at most '.GalleryImage::MAX_COUNT." photos. This one already has {$existing}."
            );
        }

        $maxSortOrder = $facility->galleryImages()->max('sort_order') ?? 0;

        foreach ($images as $image) {
            $path = $this->imageOptimizer->store($image, 'facilities');

            $facility->galleryImages()->create([
                'image_path' => $path,
                'sort_order' => ++$maxSortOrder,
                'is_active' => true,
            ]);
        }

        activity()
            ->causedBy($actor)
            ->performedOn($facility)
            ->event('facility_images_uploaded')
            ->log(count($images)." image(s) uploaded for facility '{$facility->name}'.");
    }

    /**
     * Delete a specific gallery image.
     */
    public function deleteImage(User $actor, Facility $facility, GalleryImage $image): void
    {
        $this->authorizeAdmin($actor);

        if ($image->facility_id !== $facility->id) {
            throw new InvalidArgumentException('Image does not belong to this facility.');
        }

        if (Storage::disk('public')->exists($image->image_path)) {
            Storage::disk('public')->delete($image->image_path);
        }

        $image->delete();

        activity()
            ->causedBy($actor)
            ->performedOn($facility)
            ->event('facility_image_deleted')
            ->log("An image was deleted from facility '{$facility->name}'.");
    }

    /**
     * @param  array<int|string, string|null>  $captions
     */
    public function updateImageCaptions(Facility $facility, array $captions): void
    {
        foreach ($captions as $imageId => $caption) {
            $image = $facility->galleryImages()->whereKey((int) $imageId)->first();

            if (! $image) {
                continue;
            }

            $trimmed = is_string($caption) ? trim($caption) : '';
            $image->caption = $trimmed === '' ? null : $trimmed;
            $image->save();
        }
    }
}
