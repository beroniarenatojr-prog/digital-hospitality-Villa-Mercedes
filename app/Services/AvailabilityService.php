<?php

namespace App\Services;

use App\DTOs\AvailabilityResult;
use App\Enums\FacilityStatus;
use App\Enums\FacilityType;
use App\Enums\ReservationStatus;
use App\Exceptions\Availability\InvalidAvailabilityPeriodException;
use App\Models\Facility;
use App\Models\Reservation;
use Carbon\Carbon;

class AvailabilityService
{
    /**
     * Determines whether a facility can be reserved for a requested period.
     *
     * @param Facility $facility
     * @param Carbon $checkIn
     * @param Carbon $checkOut
     * @param int|null $excludeReservationId
     * @param int|null $guestCount
     * @return AvailabilityResult
     *
     * @throws InvalidAvailabilityPeriodException
     */
    public function isAvailable(
        Facility $facility,
        Carbon $checkIn,
        Carbon $checkOut,
        ?int $excludeReservationId = null,
        ?int $guestCount = null
    ): AvailabilityResult {
        // 1. Validation
        if ($checkOut->lessThanOrEqualTo($checkIn)) {
            throw new InvalidAvailabilityPeriodException('Invalid reservation period: check-out must be after check-in.');
        }

        // 2. Facility Operational Status Check
        if (! $facility->is_active || $facility->trashed()) {
            return new AvailabilityResult(false, 'Facility is not active or has been removed.');
        }

        if ($facility->status === FacilityStatus::Unavailable) {
            return new AvailabilityResult(false, 'Facility is currently unavailable.');
        }

        if ($facility->status === FacilityStatus::Maintenance) {
            return new AvailabilityResult(false, 'Facility is under maintenance.');
        }

        // 3. Capacity Check
        if ($guestCount !== null) {
            $maxCapacity = $facility->effectiveMaxCapacity();
            if ($guestCount > $maxCapacity) {
                return new AvailabilityResult(
                    false,
                    "Guest count ({$guestCount}) exceeds facility maximum capacity ({$maxCapacity})."
                );
            }
        }

        // 4. Facility Type Specifics (Resort Packages)
        $facility->loadMissing('facilityType');
        
        if ($facility->facilityType->slug === FacilityType::ResortPackage->value || $facility->facilityType->slug === FacilityType::ResortPackage->name) {
            $components = $facility->packageComponents()->with('componentFacility')->get();
            
            if ($components->isEmpty()) {
                return new AvailabilityResult(false, 'This package has no configured components.');
            }
            
            foreach ($components as $component) {
                $componentResult = $this->checkFacilityOverlap(
                    $component->componentFacility,
                    $checkIn,
                    $checkOut,
                    $excludeReservationId
                );
                
                if (!$componentResult->isAvailable) {
                    return new AvailabilityResult(
                        false,
                        "Package component '{$component->componentFacility->name}' is not available: {$componentResult->reason}",
                        $componentResult->conflictingReservationId
                    );
                }
            }
        } else {
            // If it's a regular facility, check if any packages containing it are booked
            $parentPackages = $facility->partOfPackages()->get();
            
            foreach ($parentPackages as $parentPackage) {
                $parentResult = $this->checkFacilityOverlap(
                    $parentPackage,
                    $checkIn,
                    $checkOut,
                    $excludeReservationId
                );
                
                if (!$parentResult->isAvailable) {
                    return new AvailabilityResult(
                        false,
                        "Facility is unavailable because its parent package '{$parentPackage->name}' is booked for the requested period.",
                        $parentResult->conflictingReservationId
                    );
                }
            }
        }

        // 5. Overlap Check Query for the primary facility
        return $this->checkFacilityOverlap($facility, $checkIn, $checkOut, $excludeReservationId);
    }

    private function checkFacilityOverlap(
        Facility $facility,
        Carbon $checkIn,
        Carbon $checkOut,
        ?int $excludeReservationId = null
    ): AvailabilityResult {
        $blockingStatuses = [
            ReservationStatus::Pending->value,
            ReservationStatus::Approved->value,
        ];

        $query = Reservation::query()
            ->where('facility_id', $facility->id)
            ->whereIn('status', $blockingStatuses)
            ->where(function ($q) use ($checkIn, $checkOut) {
                // Overlap algorithm: existing.check_in < requested.check_out AND existing.check_out > requested.check_in
                $q->where('check_in', '<', $checkOut)
                  ->where('check_out', '>', $checkIn);
            });

        if ($excludeReservationId) {
            $query->where('id', '!=', $excludeReservationId);
        }

        $conflict = $query->first();

        if ($conflict) {
            return new AvailabilityResult(false, 'Facility is already booked for the requested period.', $conflict->id);
        }

        return new AvailabilityResult(true);
    }
}
