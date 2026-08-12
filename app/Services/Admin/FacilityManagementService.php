<?php

namespace App\Services\Admin;

use App\Enums\FacilityStatus;
use App\Enums\Role;
use App\Exceptions\Admin\UnauthorizedAdminActionException;
use App\Models\Amenity;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FacilityManagementService
{
    /**
     * Ensure the actor is an Administrator.
     */
    private function authorizeAdmin(User $actor): void
    {
        if (!$actor->hasRole(Role::Administrator->value)) {
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
            $facility = new Facility();
            
            $facility->facility_type_id = $data['facility_type_id'];
            $facility->name = $data['name'];
            $facility->slug = $data['slug'] ?? Str::slug($data['name']);
            $facility->description = $data['description'] ?? null;
            $facility->capacity = $data['capacity'];
            
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
}
