<?php

namespace App\Services;

use App\DTOs\AvailabilityResult;
use App\Enums\FacilityStatus;
use App\Enums\FacilityType;
use App\Exceptions\Availability\InvalidAvailabilityPeriodException;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\TrainingBatch;
use Carbon\Carbon;

class AvailabilityService
{
    /**
     * Determines whether a facility can be reserved for a requested period.
     *
     * Availability is evaluated against the *occupancy* window rather than the
     * booked period: paid early check-in and late check-out hours hold the space
     * just as firmly as the nights between them.
     *
     * @param Facility $facility
     * @param Carbon $checkIn
     * @param Carbon $checkOut
     * @param int|null $excludeReservationId
     * @param int|null $guestCount
     * @param int $earlyCheckInHours
     * @param int $lateCheckOutHours
     * @return AvailabilityResult
     *
     * @throws InvalidAvailabilityPeriodException
     */
    public function isAvailable(
        Facility $facility,
        Carbon $checkIn,
        Carbon $checkOut,
        ?int $excludeReservationId = null,
        ?int $guestCount = null,
        int $earlyCheckInHours = 0,
        int $lateCheckOutHours = 0
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

        $occupancyStart = $checkIn->copy()->subHours(max(0, $earlyCheckInHours));
        $occupancyEnd = $checkOut->copy()->addHours(max(0, $lateCheckOutHours));

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
                    $occupancyStart,
                    $occupancyEnd,
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
                    $occupancyStart,
                    $occupancyEnd,
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

        // 5. TESDA training holds the same physical spaces as guests do.
        $trainingConflict = $this->checkTrainingOverlap($facility, $occupancyStart, $occupancyEnd);

        if ($trainingConflict !== null) {
            return new AvailabilityResult(false, $trainingConflict);
        }

        // 6. Overlap Check Query for the primary facility
        return $this->checkFacilityOverlap($facility, $occupancyStart, $occupancyEnd, $excludeReservationId);
    }

    /**
     * Reports the reason a training batch holds this facility (or a facility
     * coupled to it) during the requested window, or null when it is clear.
     */
    private function checkTrainingOverlap(Facility $facility, Carbon $from, Carbon $to): ?string
    {
        $coupledIds = collect([$facility->id])
            ->merge($facility->contendingFacilities()->pluck('id'))
            ->unique()
            ->all();

        $batches = TrainingBatch::with('facilities')
            ->blocking()
            ->whereHas('facilities', fn ($q) => $q->whereIn('facilities.id', $coupledIds))
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->get();

        foreach ($batches as $batch) {
            if (! $batch->overlapsWindow($from, $to)) {
                continue;
            }

            $heldFacility = $batch->facilities->firstWhere('id', $facility->id)
                ?? $batch->facilities->whereIn('id', $coupledIds)->first();

            $heldName = $heldFacility?->name ?? $facility->name;

            return sprintf(
                'Held by TESDA training batch %s (%s) using %s from %s until %s.',
                $batch->cohort_code,
                $batch->program,
                $heldName,
                $batch->start_date->format('M d, Y'),
                $batch->end_date->format('M d, Y')
            );
        }

        return null;
    }

    /**
     * Compares occupancy windows rather than raw check-in/check-out columns.
     *
     * Candidates are fetched with a padding of the maximum additional hours so
     * the precise comparison can be done in PHP against each reservation's own
     * early/late hours. Keeps the check exact without database-specific date
     * arithmetic.
     */
    private function checkFacilityOverlap(
        Facility $facility,
        Carbon $from,
        Carbon $to,
        ?int $excludeReservationId = null
    ): AvailabilityResult {
        $pad = (int) config('resort.stay.max_additional_hours', 12);

        $query = Reservation::query()
            ->where('facility_id', $facility->id)
            ->whereIn('status', Reservation::blockingStatuses())
            ->where('check_in', '<', $to->copy()->addHours($pad))
            ->where('check_out', '>', $from->copy()->subHours($pad));

        if ($excludeReservationId) {
            $query->where('id', '!=', $excludeReservationId);
        }

        foreach ($query->get() as $existing) {
            if ($existing->occupancyStart()->greaterThanOrEqualTo($to) || $existing->occupancyEnd()->lessThanOrEqualTo($from)) {
                continue;
            }

            $reason = 'Facility is already booked for the requested period.';

            if ($existing->early_check_in_hours || $existing->late_check_out_hours) {
                $reason = sprintf(
                    'Facility is already booked for the requested period. Reservation %s occupies it until %s (includes %dh late check-out).',
                    $existing->reservation_code,
                    $existing->occupancyEnd()->format('M d, g:i A'),
                    (int) $existing->late_check_out_hours
                );
            }

            return new AvailabilityResult(false, $reason, $existing->id);
        }

        return new AvailabilityResult(true);
    }
}
