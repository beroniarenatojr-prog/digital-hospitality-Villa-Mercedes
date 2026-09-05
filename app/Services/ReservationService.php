<?php

namespace App\Services;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Exceptions\Reservation\InvalidReservationTransitionException;
use App\Exceptions\Reservation\ReservationCancellationException;
use App\Exceptions\Reservation\ReservationConflictException;
use App\Exceptions\Reservation\UnauthorizedReservationSourceException;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReservationService
{
    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly PricingService $pricingService
    ) {}

    /**
     * Create a new reservation.
     *
     * @param Facility $facility
     * @param User $actor The user making/creating the reservation (customer or staff)
     * @param Carbon $checkIn
     * @param Carbon $checkOut
     * @param ReservationSource $source
     * @param int $guestCount
     * @param array{
     *   special_requests?: string|null,
     *   admin_notes?: string|null,
     *   early_check_in_hours?: int,
     *   late_check_out_hours?: int,
     *   guest_name?: string|null,
     *   guest_email?: string|null,
     *   guest_phone?: string|null
     * } $options
     * @return Reservation
     * 
     * @throws ReservationConflictException
     * @throws \App\Exceptions\Availability\InvalidAvailabilityPeriodException
     * @throws \App\Exceptions\Availability\PackageAvailabilityLimitationException
     * @throws \App\Exceptions\Pricing\InvalidPricingPeriodException
     * @throws \App\Exceptions\Pricing\OverlappingSeasonalRatesException
     * @throws \App\Exceptions\Pricing\NoApplicableRateException
     */
    public function createReservation(
        Facility $facility,
        User $actor,
        Carbon $checkIn,
        Carbon $checkOut,
        ReservationSource $source,
        int $guestCount,
        array $options = []
    ): Reservation {
        $earlyCheckInHours = $options['early_check_in_hours'] ?? 0;
        $lateCheckOutHours = $options['late_check_out_hours'] ?? 0;

        if ($source === ReservationSource::WalkIn) {
            if (! $actor->hasRole([\App\Enums\Role::Administrator->value])) {
                throw new UnauthorizedReservationSourceException('Only authorized staff can create walk-in reservations.');
            }
        }

        return DB::transaction(function () use (
            $facility,
            $actor,
            $checkIn,
            $checkOut,
            $source,
            $guestCount,
            $options,
            $earlyCheckInHours,
            $lateCheckOutHours
        ) {
            // 1. Concurrency Protection
            // Lock the facility row and all its related package/component rows in deterministic order.
            $lockedFacilities = $this->lockFacilityGraph($facility);
            $lockedFacility = $lockedFacilities->firstWhere('id', $facility->id);

            // 2. Check Availability
            $availability = $this->availabilityService->isAvailable(
                $lockedFacility,
                $checkIn,
                $checkOut,
                null,
                $guestCount,
                $earlyCheckInHours,
                $lateCheckOutHours
            );

            if (! $availability->isAvailable) {
                throw new ReservationConflictException(
                    $availability->reason ?? 'Facility is already booked for the requested period.'
                );
            }

            // 3. Calculate Pricing
            $priceBreakdown = $this->pricingService->calculate(
                $lockedFacility,
                $checkIn,
                $checkOut,
                $earlyCheckInHours,
                $lateCheckOutHours,
                $guestCount
            );

            // 4. Determine Initial Status
            $status = match ($source) {
                ReservationSource::WalkIn => ReservationStatus::Approved,
                ReservationSource::Online => ReservationStatus::Pending,
            };

            // 5. Generate unique reservation code
            $reservationCode = $this->generateUniqueReservationCode();

            // 6. Create Reservation (Convert cents to exact decimal representation)
            $reservation = Reservation::create([
                'user_id' => $actor->id,
                'guest_name' => $options['guest_name'] ?? null,
                'guest_email' => $options['guest_email'] ?? null,
                'guest_phone' => $options['guest_phone'] ?? null,
                'facility_id' => $lockedFacility->id,
                'reservation_code' => $reservationCode,
                'reservation_date' => now()->toDateString(),
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guest_count' => $guestCount,
                'early_check_in_hours' => $earlyCheckInHours,
                'late_check_out_hours' => $lateCheckOutHours,
                'special_requests' => $options['special_requests'] ?? null,
                'admin_notes' => $options['admin_notes'] ?? null,
                'base_price' => number_format($priceBreakdown->baseAmount / 100, 2, '.', ''),
                'additional_charges' => number_format(($priceBreakdown->earlyCheckInCharge + $priceBreakdown->lateCheckOutCharge + $priceBreakdown->extraPersonCharge) / 100, 2, '.', ''),
                'total_price' => number_format($priceBreakdown->totalAmount / 100, 2, '.', ''),
                'status' => $status->value,
                'reservation_source' => $source->value,
                'approved_at' => $status === ReservationStatus::Approved ? now() : null,
            ]);

            // 7. Activity Logging
            activity()
                ->performedOn($reservation)
                ->causedBy($actor)
                ->event('created')
                ->log("Reservation created via {$source->label()} with status {$status->label()}.");

            return $reservation;
        });
    }

    /**
     * Reschedule an existing reservation.
     *
     * @param Reservation $reservation
     * @param Facility $newFacility
     * @param Carbon $newCheckIn
     * @param Carbon $newCheckOut
     * @param User $actor
     * @param int $newGuestCount
     * @param array{
     *   early_check_in_hours?: int,
     *   late_check_out_hours?: int
     * } $options
     * @return Reservation
     *
     * @throws InvalidReservationTransitionException
     * @throws ReservationConflictException
     * @throws \App\Exceptions\Availability\InvalidAvailabilityPeriodException
     * @throws \App\Exceptions\Pricing\InvalidPricingPeriodException
     * @throws \App\Exceptions\Pricing\OverlappingSeasonalRatesException
     * @throws \App\Exceptions\Pricing\NoApplicableRateException
     */
    public function reschedule(
        Reservation $reservation,
        Facility $newFacility,
        Carbon $newCheckIn,
        Carbon $newCheckOut,
        User $actor,
        int $newGuestCount,
        array $options = []
    ): Reservation {
        $earlyCheckInHours = array_key_exists('early_check_in_hours', $options)
            ? (int) $options['early_check_in_hours']
            : (int) ($reservation->early_check_in_hours ?? 0);
        $lateCheckOutHours = array_key_exists('late_check_out_hours', $options)
            ? (int) $options['late_check_out_hours']
            : (int) ($reservation->late_check_out_hours ?? 0);

        if (!in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Approved], true)) {
            throw new InvalidReservationTransitionException(
                "Cannot reschedule a reservation that is currently {$reservation->status->label()}."
            );
        }

        return DB::transaction(function () use (
            $reservation,
            $newFacility,
            $newCheckIn,
            $newCheckOut,
            $actor,
            $newGuestCount,
            $earlyCheckInHours,
            $lateCheckOutHours
        ) {
            // Lock the old and new facility graphs in deterministic order
            $oldFacility = Facility::withTrashed()->findOrFail($reservation->facility_id);
            $lockedFacilities = $this->lockFacilityGraph([$oldFacility, $newFacility]);
            $lockedNewFacility = $lockedFacilities->firstWhere('id', $newFacility->id);

            // Lock the reservation
            $lockedReservation = Reservation::where('id', $reservation->id)->lockForUpdate()->firstOrFail();

            // Check availability (excluding this reservation's own ID)
            $availability = $this->availabilityService->isAvailable(
                $lockedNewFacility,
                $newCheckIn,
                $newCheckOut,
                $lockedReservation->id,
                $newGuestCount,
                $earlyCheckInHours,
                $lateCheckOutHours
            );

            if (!$availability->isAvailable) {
                throw new ReservationConflictException(
                    $availability->reason ?? 'Facility is already booked for the requested period.'
                );
            }

            // Calculate new pricing
            $priceBreakdown = $this->pricingService->calculate(
                $lockedNewFacility,
                $newCheckIn,
                $newCheckOut,
                $earlyCheckInHours,
                $lateCheckOutHours,
                $newGuestCount
            );

            // Keep the old values for activity logging properties
            $oldProperties = [
                'facility_id' => $lockedReservation->facility_id,
                'check_in' => $lockedReservation->check_in->toDateTimeString(),
                'check_out' => $lockedReservation->check_out->toDateTimeString(),
                'guest_count' => $lockedReservation->guest_count,
                'total_price' => $lockedReservation->total_price,
            ];

            // Update reservation
            $lockedReservation->update([
                'facility_id' => $lockedNewFacility->id,
                'check_in' => $newCheckIn,
                'check_out' => $newCheckOut,
                'guest_count' => $newGuestCount,
                'early_check_in_hours' => $earlyCheckInHours,
                'late_check_out_hours' => $lateCheckOutHours,
                'base_price' => number_format($priceBreakdown->baseAmount / 100, 2, '.', ''),
                'additional_charges' => number_format(($priceBreakdown->earlyCheckInCharge + $priceBreakdown->lateCheckOutCharge + $priceBreakdown->extraPersonCharge) / 100, 2, '.', ''),
                'total_price' => number_format($priceBreakdown->totalAmount / 100, 2, '.', ''),
            ]);

            $newProperties = [
                'facility_id' => $lockedNewFacility->id,
                'check_in' => $newCheckIn->toDateTimeString(),
                'check_out' => $newCheckOut->toDateTimeString(),
                'guest_count' => $newGuestCount,
                'total_price' => $lockedReservation->total_price,
            ];

            activity()
                ->performedOn($lockedReservation)
                ->causedBy($actor)
                ->event('rescheduled')
                ->withProperties([
                    'old' => $oldProperties,
                    'new' => $newProperties,
                ])
                ->log("Reservation rescheduled to new dates/facility.");

            return $lockedReservation;
        });
    }

    /**
     * Change the status of a reservation.
     * 
     * Valid transitions:
     * Pending -> Approved, Rejected
     * Approved -> Cancelled, Rescheduled, Completed
     *
     * @param Reservation $reservation
     * @param ReservationStatus $newStatus
     * @param User $actor
     * @param string|null $reason Used for Rejection or Cancellation
     * @return void
     * 
     * @throws InvalidReservationTransitionException
     */
    public function changeStatus(Reservation $reservation, ReservationStatus $newStatus, User $actor, ?string $reason = null): void
    {
        $currentStatus = $reservation->status;

        if ($currentStatus === $newStatus) {
            return;
        }

        $this->validateTransition($currentStatus, $newStatus);

        $updates = ['status' => $newStatus->value];

        if ($newStatus === ReservationStatus::Approved) {
            $updates['approved_at'] = now();
        } elseif ($newStatus === ReservationStatus::Rejected) {
            $updates['rejected_at'] = now();
            if ($reason) {
                $updates['rejection_reason'] = $reason;
            }
        } elseif ($newStatus === ReservationStatus::Cancelled) {
            $updates['cancelled_at'] = now();
            if ($reason) {
                $updates['cancellation_reason'] = $reason;
            }
        } elseif ($newStatus === ReservationStatus::Completed) {
            $updates['completed_at'] = now();
        }

        $reservation->update($updates);

        activity()
            ->performedOn($reservation)
            ->causedBy($actor)
            ->event('status_changed')
            ->log("Reservation status changed from {$currentStatus->label()} to {$newStatus->label()}.");
    }

    /**
     * Record the guest as physically on the property.
     *
     * @throws InvalidReservationTransitionException
     */
    public function checkIn(Reservation $reservation, User $actor): Reservation
    {
        if ($reservation->status !== ReservationStatus::Approved) {
            throw new InvalidReservationTransitionException(
                "Only approved reservations can be checked in. This one is {$reservation->status->label()}."
            );
        }

        if ($reservation->checked_in_at !== null) {
            throw new InvalidReservationTransitionException(
                'This reservation was already checked in on ' . $reservation->checked_in_at->format('M d, Y g:i A') . '.'
            );
        }

        $reservation->update(['checked_in_at' => now()]);

        activity()
            ->performedOn($reservation)
            ->causedBy($actor)
            ->event('checked_in')
            ->log("Guest checked in for reservation {$reservation->reservation_code}.");

        return $reservation;
    }

    /**
     * Record departure and close the stay. Completing the reservation is what
     * releases the space, so check-out and completion happen together.
     *
     * @throws InvalidReservationTransitionException
     */
    public function checkOut(Reservation $reservation, User $actor): Reservation
    {
        if ($reservation->checked_in_at === null) {
            throw new InvalidReservationTransitionException('This reservation has not been checked in yet.');
        }

        if ($reservation->checked_out_at !== null) {
            throw new InvalidReservationTransitionException(
                'This reservation was already checked out on ' . $reservation->checked_out_at->format('M d, Y g:i A') . '.'
            );
        }

        $reservation->update(['checked_out_at' => now()]);
        $this->changeStatus($reservation, ReservationStatus::Completed, $actor);

        activity()
            ->performedOn($reservation)
            ->causedBy($actor)
            ->event('checked_out')
            ->log("Guest checked out for reservation {$reservation->reservation_code}.");

        return $reservation;
    }

    /**
     * Cancel a reservation.
     *
     * @param Reservation $reservation
     * @param User $actor
     * @param string|null $reason
     * @return void
     * 
     * @throws ReservationCancellationException
     */
    public function cancel(Reservation $reservation, User $actor, ?string $reason = null): void
    {
        try {
            $this->changeStatus($reservation, ReservationStatus::Cancelled, $actor, $reason);
        } catch (InvalidReservationTransitionException $e) {
            throw new ReservationCancellationException("Reservation cannot be cancelled from its current status: {$reservation->status->label()}");
        }
    }

    /**
     * Generates a unique reservation code handling potential collisions safely.
     *
     * @return string
     */
    private function generateUniqueReservationCode(): string
    {
        $datePart = now()->format('Ymd');

        do {
            $randomPart = strtoupper(Str::random(6));
            $code = "VM-{$datePart}-{$randomPart}";
        } while (Reservation::where('reservation_code', $code)->exists());

        return $code;
    }

    /**
     * @throws InvalidReservationTransitionException
     */
    private function validateTransition(ReservationStatus $currentStatus, ReservationStatus $newStatus): void
    {
        $validTransitions = [
            ReservationStatus::Pending->value => [
                ReservationStatus::Approved,
                ReservationStatus::Rejected,
            ],
            ReservationStatus::Approved->value => [
                ReservationStatus::Cancelled,
                ReservationStatus::Completed,
            ],
            // No transitions allowed from Rejected, Cancelled, Rescheduled (for now), or Completed
            ReservationStatus::Rejected->value => [],
            ReservationStatus::Cancelled->value => [],
            ReservationStatus::Rescheduled->value => [],
            ReservationStatus::Completed->value => [],
        ];

        $allowedNextStatuses = $validTransitions[$currentStatus->value] ?? [];

        if (! in_array($newStatus, $allowedNextStatuses, true)) {
            throw new InvalidReservationTransitionException(
                "Invalid status transition from {$currentStatus->label()} to {$newStatus->label()}."
            );
        }
    }
    /**
     * Locks a facility and its related component/parent facilities in a deterministic order to prevent deadlocks.
     *
     * @param Facility|Facility[] $facilities
     * @return \Illuminate\Database\Eloquent\Collection<int, Facility>
     */
    private function lockFacilityGraph(Facility|array $facilities): \Illuminate\Database\Eloquent\Collection
    {
        $facilities = is_array($facilities) ? $facilities : [$facilities];
        $ids = [];

        foreach ($facilities as $facility) {
            $ids[] = $facility->id;
            
            $facility->loadMissing('facilityType');
            $typeSlug = $facility->facilityType?->slug;

            if ($typeSlug === \App\Enums\FacilityType::ResortPackage->value || $typeSlug === \App\Enums\FacilityType::ResortPackage->name) {
                $componentIds = $facility->packageComponents()->pluck('component_facility_id')->toArray();
                $ids = array_merge($ids, $componentIds);
            } else {
                $parentIds = $facility->partOfPackages()->pluck('package_facility_id')->toArray();
                $ids = array_merge($ids, $parentIds);
            }
        }

        $uniqueIds = array_unique($ids);
        sort($uniqueIds);

        return Facility::withTrashed()->whereIn('id', $uniqueIds)->orderBy('id')->lockForUpdate()->get();
    }
}
