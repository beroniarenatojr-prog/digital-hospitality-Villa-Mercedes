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
     *   late_check_out_hours?: int
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
            // Lock the facility row so no other transaction can concurrently book this facility.
            $lockedFacility = Facility::withTrashed()->where('id', $facility->id)->lockForUpdate()->firstOrFail();

            // 2. Check Availability
            $availability = $this->availabilityService->isAvailable(
                $lockedFacility,
                $checkIn,
                $checkOut,
                null,
                $guestCount
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
                'facility_id' => $lockedFacility->id,
                'reservation_code' => $reservationCode,
                'reservation_date' => now()->toDateString(),
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guest_count' => $guestCount,
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
        $earlyCheckInHours = $options['early_check_in_hours'] ?? 0;
        $lateCheckOutHours = $options['late_check_out_hours'] ?? 0;

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
            // Lock both the old and new facility rows if they differ, or just the new one
            $lockedNewFacility = Facility::withTrashed()->where('id', $newFacility->id)->lockForUpdate()->firstOrFail();

            // Lock the reservation
            $lockedReservation = Reservation::where('id', $reservation->id)->lockForUpdate()->firstOrFail();

            // Check availability (excluding this reservation's own ID)
            $availability = $this->availabilityService->isAvailable(
                $lockedNewFacility,
                $newCheckIn,
                $newCheckOut,
                $lockedReservation->id,
                $newGuestCount
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
}
