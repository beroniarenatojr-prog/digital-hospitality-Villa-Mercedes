<?php

namespace App\Services;

use App\Enums\FacilityType;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Exceptions\Availability\InvalidAvailabilityPeriodException;
use App\Exceptions\Availability\PackageAvailabilityLimitationException;
use App\Exceptions\Pricing\InvalidPricingPeriodException;
use App\Exceptions\Pricing\NoApplicableRateException;
use App\Exceptions\Pricing\OverlappingSeasonalRatesException;
use App\Exceptions\Reservation\InvalidReservationTransitionException;
use App\Exceptions\Reservation\ReservationCancellationException;
use App\Exceptions\Reservation\ReservationConflictException;
use App\Exceptions\Reservation\UnauthorizedReservationSourceException;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReservationService
{
    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly PricingService $pricingService,
        private readonly CottageBookingService $cottageBookingService,
    ) {}

    /**
     * Create a new reservation.
     *
     * @param  User  $actor  The user making/creating the reservation (customer or staff)
     * @param array{
     *   special_requests?: string|null,
     *   policies_accepted_at?: \Carbon\CarbonInterface|null,
     *   admin_notes?: string|null,
     *   early_check_in_hours?: int,
     *   late_check_out_hours?: int,
     *   guest_name?: string|null,
     *   guest_email?: string|null,
     *   guest_phone?: string|null
     * } $options
     *
     * @throws ReservationConflictException
     * @throws InvalidAvailabilityPeriodException
     * @throws PackageAvailabilityLimitationException
     * @throws InvalidPricingPeriodException
     * @throws OverlappingSeasonalRatesException
     * @throws NoApplicableRateException
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
        $paxTier = isset($options['pax_tier']) ? (int) $options['pax_tier'] : $this->inferPaxTier($facility, $guestCount);

        if ($source === ReservationSource::WalkIn) {
            if (! $actor->hasRole([Role::Administrator->value])) {
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
            $lateCheckOutHours,
            $paxTier
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
                $lateCheckOutHours,
                lockForUpdate: true,
                paxTier: $paxTier
            );

            if (! $availability->isAvailable) {
                throw new ReservationConflictException(
                    $availability->reason ?? 'Facility is already booked for the requested period.'
                );
            }

            // 3. Calculate Pricing
            $priceBreakdown = $lockedFacility->isCottage()
                ? $this->cottageBookingService->price(
                    $lockedFacility,
                    $options['swimming_session_id'] ?? null,
                    $checkIn,
                    $checkOut
                )
                : $this->pricingService->calculate(
                    $lockedFacility,
                    $checkIn,
                    $checkOut,
                    $earlyCheckInHours,
                    $lateCheckOutHours,
                    $guestCount,
                    $paxTier
                );

            $usesDeposit = config('payments.deposit_workflow') && $source === ReservationSource::Online
                && $lockedFacility->facilityType->slug !== 'function_hall';
            $depositDeadline = $usesDeposit ? \App\Support\DepositDeadline::forVisit($checkIn->copy()->subHours($earlyCheckInHours)) : null;
            if ($usesDeposit && $depositDeadline->lte(now())) {
                throw new ReservationConflictException('The visit has already started in Philippine time. Contact the resort to arrange arrival.');
            }
            if ($usesDeposit && ! \App\Models\PaymentDestination::where('is_active', true)->lockForUpdate()->exists()) {
                throw new ReservationConflictException('Deposit payment instructions are not available yet. Please contact the resort before reserving.');
            }

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
                'deposit_required' => $usesDeposit ? ceil($priceBreakdown->totalAmount / 2) / 100 : null,
                'deposit_due_at' => $depositDeadline,
                'guest_name' => $options['guest_name'] ?? null,
                'guest_email' => $options['guest_email'] ?? null,
                'guest_phone' => $options['guest_phone'] ?? null,
                'guest_place_of_origin' => $options['guest_place_of_origin'] ?? null,
                'guest_nationality' => $options['guest_nationality'] ?? null,
                'guest_company' => $options['guest_company'] ?? null,
                'guest_valid_id' => $options['guest_valid_id'] ?? null,
                'guest_gender' => $options['guest_gender'] ?? null,
                'other_guest_names' => $options['other_guest_names'] ?? [],
                'facility_id' => $lockedFacility->id,
                'swimming_session_id' => $options['swimming_session_id'] ?? null,
                'reservation_code' => $reservationCode,
                'reservation_date' => now()->toDateString(),
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guest_count' => $guestCount,
                'pax_tier' => $paxTier,
                'early_check_in_hours' => $earlyCheckInHours,
                'late_check_out_hours' => $lateCheckOutHours,
                'special_requests' => filled($options['special_requests'] ?? null)
                    ? trim((string) $options['special_requests'])
                    : null,
                'policies_accepted_at' => $options['policies_accepted_at'] ?? null,
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
        }, 5);
    }

    /**
     * Reschedule an existing reservation.
     *
     * @param array{
     *   early_check_in_hours?: int,
     *   late_check_out_hours?: int
     * } $options
     *
     * @throws InvalidReservationTransitionException
     * @throws ReservationConflictException
     * @throws InvalidAvailabilityPeriodException
     * @throws InvalidPricingPeriodException
     * @throws OverlappingSeasonalRatesException
     * @throws NoApplicableRateException
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
        return DB::transaction(function () use (
            $reservation,
            $newFacility,
            $newCheckIn,
            $newCheckOut,
            $actor,
            $newGuestCount,
            $options
        ) {
            // Lock the resources being claimed before locking the reservation,
            // matching the order used by booking creation and overlap checks.
            $lockedFacilities = $this->lockFacilityGraph($newFacility);
            $lockedNewFacility = $lockedFacilities->firstWhere('id', $newFacility->id);

            // Lock the reservation
            $lockedReservation = Reservation::where('id', $reservation->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedReservation->status, [ReservationStatus::Pending, ReservationStatus::Approved], true)) {
                throw new InvalidReservationTransitionException(
                    "Cannot reschedule a reservation that is currently {$lockedReservation->status->label()}."
                );
            }

            $earlyCheckInHours = (int) ($options['early_check_in_hours'] ?? $lockedReservation->early_check_in_hours ?? 0);
            $lateCheckOutHours = (int) ($options['late_check_out_hours'] ?? $lockedReservation->late_check_out_hours ?? 0);
            $paxTier = isset($options['pax_tier'])
                ? (int) $options['pax_tier']
                : ($lockedReservation->pax_tier ?? $this->inferPaxTier($lockedNewFacility, $newGuestCount));
            $sessionId = $lockedNewFacility->isCottage()
                ? ($options['swimming_session_id'] ?? $lockedReservation->swimming_session_id)
                : null;

            if ($lockedNewFacility->isCottage()) {
                $earlyCheckInHours = 0;
                $lateCheckOutHours = 0;
            }

            // Check availability (excluding this reservation's own ID)
            $availability = $this->availabilityService->isAvailable(
                $lockedNewFacility,
                $newCheckIn,
                $newCheckOut,
                $lockedReservation->id,
                $newGuestCount,
                $earlyCheckInHours,
                $lateCheckOutHours,
                lockForUpdate: true,
                paxTier: $paxTier
            );

            if (! $availability->isAvailable) {
                throw new ReservationConflictException(
                    $availability->reason ?? 'Facility is already booked for the requested period.'
                );
            }

            // Calculate new pricing
            if ($lockedReservation->deposit_required !== null) {
                $payments = $lockedReservation->payments()->lockForUpdate()->get();
                if ($payments->contains(fn ($payment) => in_array($payment->status, [\App\Enums\PaymentStatus::Pending, \App\Enums\PaymentStatus::Verified], true))) {
                    throw new InvalidReservationTransitionException('This booking has a deposit or proof under review. Contact staff to reconcile the existing payment before changing the booking.');
                }
                if ($lockedReservation->deposit_due_at?->isPast()) {
                    throw new InvalidReservationTransitionException('This hold has reached its payment deadline. Contact staff before changing it.');
                }
                if ($lockedNewFacility->facilityType->slug === 'function_hall') {
                    throw new InvalidReservationTransitionException('Request a separate staff quotation for a function hall.');
                }
            }
            $priceBreakdown = $lockedNewFacility->isCottage()
                ? $this->cottageBookingService->price($lockedNewFacility, $sessionId, $newCheckIn, $newCheckOut)
                : $this->pricingService->calculate(
                    $lockedNewFacility,
                    $newCheckIn,
                    $newCheckOut,
                    $earlyCheckInHours,
                    $lateCheckOutHours,
                    $newGuestCount,
                    $paxTier
                );

            // Keep the old values for activity logging properties
            $oldProperties = [
                'facility_id' => $lockedReservation->facility_id,
                'check_in' => $lockedReservation->check_in->toDateTimeString(),
                'check_out' => $lockedReservation->check_out->toDateTimeString(),
                'guest_count' => $lockedReservation->guest_count,
                'total_price' => $lockedReservation->total_price,
            ];

            // A date change never restarts the original unpaid hold.
            if ($lockedReservation->deposit_required !== null) {
                $lockedReservation->deposit_required = ceil($priceBreakdown->totalAmount / 2) / 100;
                $lockedReservation->deposit_due_at = \App\Support\DepositDeadline::forVisit($newCheckIn->copy()->subHours($earlyCheckInHours), $lockedReservation->deposit_due_at);
                if ($lockedReservation->deposit_due_at->lte(now())) throw new InvalidReservationTransitionException('Choose a visit that has not started in Philippine time.');
            }
            // Update reservation
            $lockedReservation->update([
                'facility_id' => $lockedNewFacility->id,
                'swimming_session_id' => $sessionId,
                'check_in' => $newCheckIn,
                'check_out' => $newCheckOut,
                'guest_count' => $newGuestCount,
                'pax_tier' => $paxTier,
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
                ->log('Reservation rescheduled to new dates/facility.');

            return $lockedReservation;
        }, 5);
    }

    /**
     * Change the status of a reservation.
     *
     * Valid transitions:
     * Pending -> Approved, Rejected
     * Approved -> Cancelled, Rescheduled, Completed
     *
     * @param  string|null  $reason  Used for Rejection or Cancellation
     *
     * @throws InvalidReservationTransitionException
     */
    public function changeStatus(Reservation $reservation, ReservationStatus $newStatus, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($reservation, $newStatus, $actor, $reason): void {
            $lockedReservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($lockedReservation->status === $newStatus) {
                return;
            }

            if (config('payments.deposit_workflow') && $newStatus === ReservationStatus::Completed && ! $lockedReservation->checked_out_at) {
                throw new InvalidReservationTransitionException('Use Check out & complete after recording the guest arrival.');
            }
            if ($newStatus === ReservationStatus::Approved && $lockedReservation->deposit_required !== null && $lockedReservation->cancellation_requested_at) {
                throw new InvalidReservationTransitionException('Resolve the guest withdrawal request before confirmation.');
            }
            if ($newStatus === ReservationStatus::Approved && $lockedReservation->deposit_required !== null
                && $lockedReservation->getVerifiedTotalPaid() < $lockedReservation->requiredDepositAmount()) {
                throw new InvalidReservationTransitionException('Verify the required deposit before confirming this reservation.');
            }
            $this->validateTransition($lockedReservation->status, $newStatus);
            $this->updateStatus($lockedReservation, $newStatus, $actor, $reason);
        }, 5);

        $reservation->refresh();
    }

    public function confirmWithdrawal(Reservation $reservation, User $actor): void
    {
        if (! $actor->hasRole(Role::Administrator->value)) {
            throw new InvalidReservationTransitionException('Only administrators can confirm a guest withdrawal.');
        }

        DB::transaction(function () use ($reservation, $actor): void {
            $lockedReservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($lockedReservation->status !== ReservationStatus::Pending || $lockedReservation->cancellation_requested_at === null) {
                throw new InvalidReservationTransitionException('Only a pending reservation with a guest withdrawal request can be withdrawn.');
            }

            $this->updateStatus(
                $lockedReservation,
                ReservationStatus::Cancelled,
                $actor,
                $lockedReservation->cancellation_request_reason ?: 'Guest requested withdrawal.'
            );
        }, 5);

        $reservation->refresh();
    }

    /** Apply a transition already validated while holding the reservation lock. */
    private function updateStatus(Reservation $reservation, ReservationStatus $newStatus, User $actor, ?string $reason = null): void
    {
        if ($reservation->deposit_required !== null && in_array($newStatus, [ReservationStatus::Cancelled, ReservationStatus::Rejected], true)) {
            $reservation->setRelation('payments', $reservation->payments()->lockForUpdate()->get());
            if ($reservation->getPendingVerificationTotal() > 0) {
                throw new InvalidReservationTransitionException('Reconcile pending deposit proofs before releasing this booking.');
            }
            if ($reservation->getVerifiedTotalPaid() > 0 && (! $actor->hasRole(Role::Administrator->value) || blank($reason))) {
                throw new InvalidReservationTransitionException('Staff must record a funds reconciliation reason before cancelling a booking with a verified deposit.');
            }
        }
        $currentStatus = $reservation->status;

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
        DB::transaction(function () use ($reservation, $actor): void {
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($reservation->status !== ReservationStatus::Approved) {
                throw new InvalidReservationTransitionException(
                    "Only approved reservations can be checked in. This one is {$reservation->status->label()}."
                );
            }

            if ($reservation->checked_in_at !== null) {
                throw new InvalidReservationTransitionException(
                    'This reservation was already checked in on '.$reservation->checked_in_at->format('M d, Y g:i A').'.'
                );
            }

            if (config('payments.deposit_workflow') && $reservation->getRemainingBalance() > 0) {
                throw new InvalidReservationTransitionException('Record the remaining reservation balance at arrival before check-in.');
            }
            $reservation->update(['checked_in_at' => now()]);

            activity()
                ->performedOn($reservation)
                ->causedBy($actor)
                ->event('checked_in')
                ->log("Guest checked in for reservation {$reservation->reservation_code}.");
        }, 5);

        return $reservation->refresh();
    }

    /**
     * Record departure and close the stay. Completing the reservation is what
     * releases the space, so check-out and completion happen together.
     *
     * @throws InvalidReservationTransitionException
     */
    public function checkOut(Reservation $reservation, User $actor): Reservation
    {
        DB::transaction(function () use ($reservation, $actor): void {
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($reservation->checked_in_at === null) {
                throw new InvalidReservationTransitionException('This reservation has not been checked in yet.');
            }

            if ($reservation->checked_out_at !== null) {
                throw new InvalidReservationTransitionException(
                    'This reservation was already checked out on '.$reservation->checked_out_at->format('M d, Y g:i A').'.'
                );
            }

            $this->validateTransition($reservation->status, ReservationStatus::Completed);
            $reservation->update(['checked_out_at' => now()]);
            $this->updateStatus($reservation, ReservationStatus::Completed, $actor);

            activity()
                ->performedOn($reservation)
                ->causedBy($actor)
                ->event('checked_out')
                ->log("Guest checked out for reservation {$reservation->reservation_code}.");
        }, 5);

        return $reservation->refresh();
    }

    /**
     * Cancel a reservation.
     *
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
            ReservationStatus::Expired->value => [],
        ];

        $allowedNextStatuses = $validTransitions[$currentStatus->value] ?? [];

        if (! in_array($newStatus, $allowedNextStatuses, true)) {
            throw new InvalidReservationTransitionException(
                "Invalid status transition from {$currentStatus->label()} to {$newStatus->label()}."
            );
        }
    }

    private function inferPaxTier(Facility $facility, int $guestCount): ?int
    {
        if (! $facility->hasPaxTiers()) {
            return null;
        }

        foreach ($facility->paxTiers() as $tier) {
            if ($guestCount <= $tier) {
                return $tier;
            }
        }

        return $facility->effectiveMaxCapacity();
    }

    /**
     * Locks a facility and its related component/parent facilities in a deterministic order to prevent deadlocks.
     *
     * @param  Facility|Facility[]  $facilities
     * @return Collection<int, Facility>
     */
    private function lockFacilityGraph(Facility|array $facilities): Collection
    {
        $facilities = is_array($facilities) ? $facilities : [$facilities];
        $ids = [];

        foreach ($facilities as $facility) {
            $ids[] = $facility->id;

            $facility->loadMissing('facilityType');
            $typeSlug = $facility->facilityType?->slug;

            if ($typeSlug === FacilityType::ResortPackage->value || $typeSlug === FacilityType::ResortPackage->name) {
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
