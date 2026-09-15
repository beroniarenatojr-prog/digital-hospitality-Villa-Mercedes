<?php

namespace App\Services;

use App\DTOs\AvailabilityResult;
use App\Enums\FacilityStatus;
use App\Enums\FacilityType;
use App\Exceptions\Availability\InvalidAvailabilityPeriodException;
use App\Models\Facility;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AvailabilityService
{
    public const SAPPHIRE_UNAVAILABLE = 'Sapphire Hall is unavailable at the selected date and time.';

    /**
     * Blocking reservations keyed by facility id, loaded once per search window.
     *
     * @var array<int, Collection<int, Reservation>>|null
     */
    private ?array $overlapIndex = null;

    /**
     * Load every blocking reservation that could collide with the window, for
     * the given facilities and their package counterparts, in a single query.
     *
     * @param  iterable<int, Facility>  $facilities
     */
    public function primeOverlaps(iterable $facilities, Carbon $from, Carbon $to): void
    {
        $catalog = collect($facilities)->unique('id')->values();

        foreach ($catalog as $facility) {
            $facility->loadMissing(['packageComponents.componentFacility.partOfPackages', 'partOfPackages']);
        }

        $ids = $catalog
            ->flatMap(function (Facility $facility): array {
                return [
                    $facility->id,
                    ...$facility->packageComponents->pluck('component_facility_id')->all(),
                    ...$facility->partOfPackages->pluck('id')->all(),
                    ...$facility->packageComponents->flatMap(
                        fn ($component) => $component->componentFacility?->partOfPackages->pluck('id') ?? []
                    )->all(),
                ];
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            $this->overlapIndex = [];

            return;
        }

        [$paddedFrom, $paddedTo] = $this->paddedWindow($from, $to);

        $grouped = Reservation::query()
            ->whereIn('facility_id', $ids)
            ->whereIn('status', Reservation::blockingStatuses())
            ->where('check_in', '<', $paddedTo)
            ->where('check_out', '>', $paddedFrom)
            ->get($this->overlapColumns())
            ->groupBy('facility_id');

        $index = [];

        foreach ($ids as $id) {
            $index[(int) $id] = $grouped->get($id, collect());
        }

        $this->overlapIndex = $index;
    }

    public function forgetPrimedOverlaps(): void
    {
        $this->overlapIndex = null;
    }

    /**
     * Determines whether a facility can be reserved for a requested period.
     *
     * Availability is evaluated against the *occupancy* window rather than the
     * booked period: paid early check-in and late check-out hours hold the space
     * just as firmly as the nights between them.
     *
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
        int $lateCheckOutHours = 0,
        bool $lockForUpdate = false,
        ?int $paxTier = null
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
            if ($paxTier !== null) {
                $maxCapacity = $maxCapacity === null ? $paxTier : min($maxCapacity, $paxTier);
            }
            if ($maxCapacity !== null && $guestCount > $maxCapacity) {
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
            if ($lockForUpdate) {
                $facility->setRelation('packageComponents', $facility->packageComponents()->lockForUpdate()->get());
            }
            $facility->loadMissing('packageComponents.componentFacility.facilityType');
            $components = $facility->packageComponents;

            if ($components->isEmpty()) {
                return new AvailabilityResult(false, 'This package has no configured components.');
            }

            foreach ($components as $component) {
                $componentFacility = $component->componentFacility;

                if (! $componentFacility || $componentFacility->isPackage()) {
                    return new AvailabilityResult(false, 'This package has a missing or unsupported component.');
                }

                if ($lockForUpdate) {
                    $componentFacility = Facility::withTrashed()->whereKey($componentFacility->id)->lockForUpdate()->firstOrFail();
                }

                // A component's operational state and every package that uses
                // it apply equally when booking the component through a package.
                $componentResult = $this->isAvailable(
                    $componentFacility,
                    $checkIn,
                    $checkOut,
                    $excludeReservationId,
                    null,
                    $earlyCheckInHours,
                    $lateCheckOutHours,
                    $lockForUpdate
                );

                if (! $componentResult->isAvailable) {
                    return new AvailabilityResult(
                        false,
                        "Package component '{$componentFacility->name}' is not available: {$componentResult->reason}",
                        $componentResult->conflictingReservationId
                    );
                }
            }
        } else {
            if ($lockForUpdate) {
                $facility->setRelation('partOfPackages', $facility->partOfPackages()->lockForUpdate()->get());
            }
            $facility->loadMissing('partOfPackages');

            foreach ($facility->partOfPackages as $parentPackage) {
                $parentResult = $this->checkFacilityOverlap(
                    $parentPackage,
                    $occupancyStart,
                    $occupancyEnd,
                    $excludeReservationId,
                    $lockForUpdate
                );

                if (! $parentResult->isAvailable) {
                    return new AvailabilityResult(
                        false,
                        $this->unavailableReason(
                            $facility,
                            "Facility is unavailable because its parent package '{$parentPackage->name}' is booked for the requested period."
                        ),
                        $parentResult->conflictingReservationId
                    );
                }
            }
        }

        // 5. Overlap Check Query for the primary facility
        return $this->checkFacilityOverlap($facility, $occupancyStart, $occupancyEnd, $excludeReservationId, $lockForUpdate);
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
        ?int $excludeReservationId = null,
        bool $lockForUpdate = false
    ): AvailabilityResult {
        $candidates = $this->overlapCandidates($facility, $from, $to, $excludeReservationId, $lockForUpdate);

        foreach ($candidates as $existing) {
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

            return new AvailabilityResult(false, $this->unavailableReason($facility, $reason), $existing->id);
        }

        return new AvailabilityResult(true);
    }

    /**
     * @return Collection<int, Reservation>
     */
    private function overlapCandidates(
        Facility $facility,
        Carbon $from,
        Carbon $to,
        ?int $excludeReservationId,
        bool $lockForUpdate
    ): Collection {
        if (! $lockForUpdate && $this->overlapIndex !== null && array_key_exists($facility->id, $this->overlapIndex)) {
            $candidates = $this->overlapIndex[$facility->id];

            if ($excludeReservationId) {
                $candidates = $candidates->reject(
                    fn (Reservation $reservation): bool => $reservation->id === $excludeReservationId
                );
            }

            return $candidates;
        }

        [$paddedFrom, $paddedTo] = $this->paddedWindow($from, $to);

        $query = Reservation::query()
            ->where('facility_id', $facility->id)
            ->whereIn('status', Reservation::blockingStatuses())
            ->where('check_in', '<', $paddedTo)
            ->where('check_out', '>', $paddedFrom);

        if ($excludeReservationId) {
            $query->where('id', '!=', $excludeReservationId);
        }

        // Booking mutations require a current read after waiting for facility
        // locks; a MySQL repeatable-read snapshot may predate that wait.
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get($this->overlapColumns());
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function paddedWindow(Carbon $from, Carbon $to): array
    {
        $pad = (int) config('resort.stay.max_additional_hours', 12);

        return [$from->copy()->subHours($pad), $to->copy()->addHours($pad)];
    }

    private function unavailableReason(Facility $facility, string $fallback): string
    {
        return $facility->name === 'Sapphire Hall' ? self::SAPPHIRE_UNAVAILABLE : $fallback;
    }

    /**
     * @return list<string>
     */
    private function overlapColumns(): array
    {
        return [
            'id',
            'facility_id',
            'reservation_code',
            'check_in',
            'check_out',
            'early_check_in_hours',
            'late_check_out_hours',
        ];
    }
}
