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
use App\Support\ResortTime;
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
            // 1. Hoarding Protection
            // A pending reservation holds its space until the deposit deadline, so
            // without a ceiling one account could take every cottage and every room
            // for the same dates. Guests only: the desk books groups, and a walk-in
            // is created approved rather than pending, so it is never counted here.
            if ($source === ReservationSource::Online) {
                $this->assertWithinPendingHoldLimit($actor);
            }

            // 2. Concurrency Protection
            // Lock the facility row and all its related package/component rows in deterministic order.
            $lockedFacilities = $this->lockFacilityGraph($facility);
            $lockedFacility = $lockedFacilities->firstWhere('id', $facility->id);

            if ($source === ReservationSource::Online) {
                app(ReservationSchedule::class)->validate($lockedFacility, $checkIn, $checkOut, $earlyCheckInHours, $lateCheckOutHours);
            }

            if ($lockedFacility->isRoom() && ($earlyCheckInHours < 0 || $earlyCheckInHours > ($source === ReservationSource::Online ? (int) config('resort.stay.room_max_early_hours', 2) : (int) config('resort.stay.max_additional_hours', 12)))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['early_check_in_hours' => 'Room early check-in is limited to two hours, subject to availability.']);
            }

            if ($lockedFacility->isRoom() && $checkIn->isSameDay($checkOut)) {
                $earlyCheckInHours = 0;
                $lateCheckOutHours = 0;
            }
            // 3. Check Availability
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

            // 4. Calculate Pricing
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
                    max(0, $guestCount - (($lockedFacility->isRoom() || $lockedFacility->isPackage()) ? (int) ($options['children_count'] ?? 0) : 0)),
                    $paxTier
                );

            $usesDeposit = config('payments.deposit_workflow') && $source === ReservationSource::Online
                && $lockedFacility->facilityType->slug !== 'function_hall';
            $depositDeadline = $usesDeposit ? \App\Support\DepositDeadline::forRoomStay($checkIn->copy()->subHours($earlyCheckInHours), $checkOut, $lockedFacility->isRoom()) : null;
            if ($usesDeposit && $depositDeadline->lte(now())) {
                throw new ReservationConflictException('The visit has already started in Philippine time. Contact the resort to arrange check in.');
            }
            if ($usesDeposit && ! \App\Models\PaymentDestination::where('is_active', true)->lockForUpdate()->exists()) {
                throw new ReservationConflictException('Deposit payment instructions are not available yet. Please contact the resort before reserving.');
            }

            // 5. Determine Initial Status
            $status = match ($source) {
                ReservationSource::WalkIn => ReservationStatus::Approved,
                ReservationSource::Online => ReservationStatus::Pending,
            };

            // 6. Generate unique reservation code
            $reservationCode = $this->generateUniqueReservationCode();

            // 7. Create Reservation (Convert cents to exact decimal representation)
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
                'policy_version' => $options['policy_version'] ?? config('resort.policy.version', '2026-09-v2'),
                'admin_notes' => $options['admin_notes'] ?? null,
                'base_price' => number_format($priceBreakdown->baseAmount / 100, 2, '.', ''),
                'additional_charges' => number_format(($priceBreakdown->totalAmount - $priceBreakdown->baseAmount) / 100, 2, '.', ''),
                'hall_extension_charge' => number_format($priceBreakdown->hallExtensionCharge / 100, 2, '.', ''),
                'total_price' => number_format($priceBreakdown->totalAmount / 100, 2, '.', ''),
                'pricing_breakdown' => $priceBreakdown->toArray(),
                'booking_workflow' => ['infants_count' => (int) ($options['infants_count'] ?? 0), 'children_count' => (int) ($options['children_count'] ?? 0)],
                'status' => $status->value,
                'reservation_source' => $source->value,
                'approved_at' => $status === ReservationStatus::Approved ? now() : null,
            ]);

            if (! empty($options['pets_included'])) {
                app(BookingPracticeService::class)->addAdjustment($reservation, $actor, 50000, 'Pets included; one fee per booking.', 'pet_fee');
                $reservation->refresh();
                if ($reservation->deposit_required !== null) {
                    $reservation->update(['deposit_required' => ceil((int) round($reservation->total_price * 100) / 2) / 100]);
                }
            }

            // 8. Activity Logging
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

            if ($lockedReservation->checked_in_at !== null) {
                throw new InvalidReservationTransitionException('Rescheduling ends when physical check-in begins.');
            }
            if ($lockedReservation->policy_version === config('resort.policy.confirmed_version') && ! ($options['guest_accepted_change'] ?? false)) {
                throw new InvalidReservationTransitionException('Record a revised quote and guest acceptance in Booking discussions before staff confirmation.');
            }
            if (! in_array($lockedReservation->status, [ReservationStatus::Pending, ReservationStatus::Approved], true)) {
                throw new InvalidReservationTransitionException(
                    "Cannot reschedule a reservation that is currently {$lockedReservation->status->label()}."
                );
            }

            $earlyCheckInHours = (int) ($options['early_check_in_hours'] ?? $lockedReservation->early_check_in_hours ?? 0);
            $lateCheckOutHours = (int) ($options['late_check_out_hours'] ?? $lockedReservation->late_check_out_hours ?? 0);
            if (! $actor->hasRole(Role::Administrator->value)) {
                app(ReservationSchedule::class)->validate($lockedNewFacility, $newCheckIn, $newCheckOut, $earlyCheckInHours, $lateCheckOutHours);
            }
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

            if ($lockedNewFacility->isRoom() && ($earlyCheckInHours < 0 || $earlyCheckInHours > ($actor->hasRole(Role::Administrator->value) ? (int) config('resort.stay.max_additional_hours', 12) : (int) config('resort.stay.room_max_early_hours', 2)))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['early_check_in_hours' => 'Room early check-in is limited to two hours, subject to availability.']);
            }

            if ($lockedNewFacility->isRoom() && $newCheckIn->isSameDay($newCheckOut)) {
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
                $isAdmin = $actor->hasRole(Role::Administrator->value);
                if ($payments->contains(fn ($payment) => $payment->status === \App\Enums\PaymentStatus::Pending)) {
                    throw new InvalidReservationTransitionException('This booking has a deposit or proof under review. Contact staff to reconcile the existing payment before changing the booking.');
                }
                if (! $isAdmin && $payments->contains(fn ($payment) => $payment->status === \App\Enums\PaymentStatus::Verified)) {
                    throw new InvalidReservationTransitionException('This booking already has a verified deposit. Contact staff to arrange and reconcile the change.');
                }
                if (! $isAdmin && $lockedReservation->deposit_due_at?->isPast()) {
                    throw new InvalidReservationTransitionException('This hold has reached its payment deadline. Contact staff before changing it.');
                }
                if (! $isAdmin && $lockedNewFacility->facilityType->slug === 'function_hall') {
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
                    max(0, $newGuestCount - (($lockedNewFacility->isRoom() || $lockedNewFacility->isPackage()) ? (int) ($lockedReservation->booking_workflow['children_count'] ?? 0) : 0)),
                    $paxTier
                );

            // Keep the old values for activity logging properties
            $oldProperties = [
                'facility_id' => $lockedReservation->facility_id,
                'check_in' => $lockedReservation->check_in->toDateTimeString(),
                'check_out' => $lockedReservation->check_out->toDateTimeString(),
                'guest_count' => $lockedReservation->guest_count,
                'total_price' => $lockedReservation->total_price,
                'base_price' => $lockedReservation->base_price,
                'pricing_breakdown' => $lockedReservation->pricing_breakdown,
                'policy_version' => $lockedReservation->policy_version,
                'deposit_required' => $lockedReservation->deposit_required,
            ];

            // A date change never restarts the original unpaid hold.
            if ($lockedReservation->deposit_required !== null) {
                $lockedReservation->deposit_required = ceil($priceBreakdown->totalAmount / 2) / 100;
                $lockedReservation->deposit_due_at = ($options['guest_accepted_change'] ?? false)
                    ? \App\Support\DepositDeadline::forVisit($newCheckIn->copy()->subHours($earlyCheckInHours))
                    : \App\Support\DepositDeadline::forVisit($newCheckIn->copy()->subHours($earlyCheckInHours), $lockedReservation->deposit_due_at);
                if ($lockedReservation->deposit_due_at->lte(now())) throw new InvalidReservationTransitionException('Choose a visit that has not started in Philippine time.');
            }
            // Retain separately agreed charges when moving a booking; the original quote remains in the audit log.
            $adjustments = array_sum(array_column($lockedReservation->booking_workflow['adjustments'] ?? [], 'amount_cents'));
            $revisedTotal = $priceBreakdown->totalAmount + $adjustments;
            // Update reservation
            $lockedReservation->update([
                'facility_id' => $lockedNewFacility->id,
                ...(array_key_exists('special_requests', $options) ? ['special_requests' => filled($options['special_requests']) ? trim($options['special_requests']) : null] : []),
                'swimming_session_id' => $sessionId,
                'check_in' => $newCheckIn,
                'check_out' => $newCheckOut,
                'guest_count' => $newGuestCount,
                'pax_tier' => $paxTier,
                'early_check_in_hours' => $earlyCheckInHours,
                'late_check_out_hours' => $lateCheckOutHours,
                'base_price' => number_format($priceBreakdown->baseAmount / 100, 2, '.', ''),
                'additional_charges' => number_format(($revisedTotal - $priceBreakdown->baseAmount) / 100, 2, '.', ''),
                'hall_extension_charge' => number_format($priceBreakdown->hallExtensionCharge / 100, 2, '.', ''),
                'total_price' => number_format($revisedTotal / 100, 2, '.', ''),
                'pricing_breakdown' => $priceBreakdown->toArray(),
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
        abort_unless($actor->hasRole(Role::Administrator->value), 403);
        DB::transaction(function () use ($reservation, $newStatus, $actor, $reason): void {
            if ($newStatus === ReservationStatus::Approved) {
                $this->lockFacilityGraph($reservation->facility);
                if ($reservation->reservation_group_id) \App\Models\ReservationGroup::whereKey($reservation->reservation_group_id)->lockForUpdate()->firstOrFail();
            }
            $lockedReservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($lockedReservation->status === $newStatus) {
                return;
            }

            if ((config('payments.deposit_workflow') || $lockedReservation->policy_version === config('resort.policy.confirmed_version')) && $newStatus === ReservationStatus::Completed && ! $lockedReservation->checked_out_at) {
                throw new InvalidReservationTransitionException('Use Check out & complete after recording the guest check in.');
            }
            if ($newStatus === ReservationStatus::Approved && $lockedReservation->deposit_required !== null && $lockedReservation->cancellation_requested_at) {
                throw new InvalidReservationTransitionException('Resolve the guest withdrawal request before confirmation.');
            }
            if ($newStatus === ReservationStatus::Approved) {
                $discussion = $lockedReservation->reservationGroup?->booking_workflow ?? $lockedReservation->booking_workflow ?? [];
                if (isset($discussion['refund']) || ($discussion['child_charge']['status'] ?? null) === 'disputed') {
                    throw new InvalidReservationTransitionException('Resolve the recorded booking discussion before approval.');
                }
                if ($lockedReservation->policy_version === config('resort.policy.confirmed_version')
                    && $lockedReservation->getVerifiedTotalPaid() < $lockedReservation->requiredDepositAmount()) {
                    throw new InvalidReservationTransitionException('Verify at least the 50% down payment before reserving this booking. Proof under review is not verified payment.');
                }
                $facilities = $this->lockFacilityGraph($lockedReservation->facility);
                $facility = $facilities->firstWhere('id', $lockedReservation->facility_id);
                $available = $this->availabilityService->isAvailable($facility, $lockedReservation->check_in, $lockedReservation->check_out,
                    $lockedReservation->id, $lockedReservation->guest_count, $lockedReservation->early_check_in_hours,
                    $lockedReservation->late_check_out_hours, lockForUpdate: true, paxTier: $lockedReservation->pax_tier);
                if (! $available->isAvailable) {
                    throw new InvalidReservationTransitionException($available->reason);
                }
            }
            // Approving a request does not wait for money. The resort decides
            // whether it wants the booking; the deposit is what the guest does
            // next, inside the window `updateStatus()` opens for them.
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
    private function updateStatus(Reservation $reservation, ReservationStatus $newStatus, ?User $actor, ?string $reason = null): void
    {
        if ($reservation->deposit_required !== null && in_array($newStatus, [ReservationStatus::Cancelled, ReservationStatus::Rejected], true)) {
            $reservation->setRelation('payments', $reservation->payments()->lockForUpdate()->get());
            if ($reservation->getPendingVerificationTotal() > 0) {
                throw new InvalidReservationTransitionException('Reconcile pending deposit proofs before releasing this booking.');
            }
            if ($reservation->getVerifiedTotalPaid() > 0 && (! $actor?->hasRole(Role::Administrator->value) || blank($reason))) {
                throw new InvalidReservationTransitionException('Staff must record a funds reconciliation reason before cancelling a booking with a verified deposit.');
            }
        }
        if ($reservation->policy_version === config('resort.policy.confirmed_version')
            && $newStatus === ReservationStatus::Cancelled) {
            throw new InvalidReservationTransitionException('Use Booking discussions: request cancellation, then owner approval and a separate returned-funds record.');
        }
        $currentStatus = $reservation->status;

        $updates = ['status' => $newStatus->value];

        if ($newStatus === ReservationStatus::Approved) {
            $updates['approved_at'] = now();

            // Approval is what opens the 50% QR step, so it is also where that
            // step's deadline is set: a fresh hold-sized window from now, capped
            // at the start of the visit. A pending request's review window has
            // done its job by this point and must not be inherited.
            if ($reservation->deposit_required !== null) {
                $updates['deposit_due_at'] = \App\Support\DepositDeadline::forReservation($reservation);
            }
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

        $activity = activity()->performedOn($reservation);
        if ($actor !== null) {
            $activity->causedBy($actor);
        }
        $activity->event('status_changed')
            ->log("Reservation status changed from {$currentStatus->label()} to {$newStatus->label()}.");
    }

    /**
     * Record the guest as physically on the property.
     *
     * @throws InvalidReservationTransitionException
     */
    public function checkIn(Reservation $reservation, User $actor): Reservation
    {
        abort_unless($actor->hasRole(Role::Administrator->value), 403);
        DB::transaction(function () use ($reservation, $actor): void {
            $this->lockFacilityGraph($reservation->facility);
            if ($reservation->reservation_group_id) \App\Models\ReservationGroup::whereKey($reservation->reservation_group_id)->lockForUpdate()->firstOrFail();
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

            if ((config('payments.deposit_workflow') || $reservation->policy_version === config('resort.policy.confirmed_version'))
                && ($reservation->reservationGroup ? $reservation->reservationGroup->outstandingBalance() : $reservation->getRemainingBalance()) > 0) {
                throw new InvalidReservationTransitionException('Record the remaining reservation balance at check in before check-in.');
            }
            $discussion = $reservation->reservationGroup?->booking_workflow ?? $reservation->booking_workflow ?? [];
            if (($discussion['child_charge']['status'] ?? null) === 'disputed' || isset($discussion['refund'])) {
                throw new InvalidReservationTransitionException('Hold for staff discussion: the guest disputes the child charge.');
            }
            $expectedHours = $reservation->facility->isRoom() && ! $reservation->check_in->isSameDay($reservation->check_out)
                ? self::earlyArrivalHours(max(0, ResortTime::databaseNow()->diffInMinutes($reservation->check_in, false))) : 0;
            if ($reservation->policy_version === config('resort.policy.confirmed_version') && $expectedHours > $reservation->early_check_in_hours) {
                throw new InvalidReservationTransitionException('Review early check in charges and settle the revised balance before physical check-in.');
            }
            if ((config('payments.deposit_workflow') || $reservation->policy_version === config('resort.policy.confirmed_version'))
                && ($reservation->reservationGroup ? $reservation->reservationGroup->outstandingBalance() : $reservation->getRemainingBalance()) > 0) {
                throw new InvalidReservationTransitionException('Record the revised remaining balance, including early check in, before check-in.');
            }
            if ($reservation->policy_version === config('resort.policy.confirmed_version')) {
                if ($reservation->occupancyEnd()->lte(ResortTime::databaseNow())) {
                    throw new InvalidReservationTransitionException('The selected stay has ended; arrange a new checked occupancy window before physical check in.');
                }
                $result = $this->availabilityService->isAvailable($reservation->facility->fresh(), ResortTime::databaseNow(), $reservation->occupancyEnd(),
                    $reservation->id, $reservation->guest_count, lockForUpdate: true);
                if (! $result->isAvailable) throw new InvalidReservationTransitionException($result->reason);
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
        abort_unless($actor->hasRole(Role::Administrator->value), 403);
        DB::transaction(function () use ($reservation, $actor): void {
            $this->lockFacilityGraph($reservation->facility);
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
            $this->requireCleaning($reservation);
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
     * Close an in-house stay once its scheduled occupancy window has ended.
     *
     * The lock and final time check make this safe to run alongside a staff
     * member using the early check-out action.
     */
    public function checkOutAutomatically(Reservation $reservation): bool
    {
        return DB::transaction(function () use ($reservation): bool {
            $reservation = Reservation::whereKey($reservation->id)->first();
            if (! $reservation) return false;
            $this->lockFacilityGraph($reservation->facility);
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->first();

            if (! $reservation
                || $reservation->status !== ReservationStatus::Approved
                || $reservation->checked_in_at === null
                || $reservation->checked_out_at !== null
                || $reservation->occupancyEnd()->gt(ResortTime::databaseNow())) {
                return false;
            }

            $this->validateTransition($reservation->status, ReservationStatus::Completed);
            $this->requireCleaning($reservation);
            $reservation->update(['checked_out_at' => $reservation->occupancyEnd()]);
            $this->updateStatus($reservation, ReservationStatus::Completed, null);

            activity()
                ->performedOn($reservation)
                ->event('automatically_checked_out')
                ->withProperties(['scheduled_checkout' => $reservation->occupancyEnd()->toIso8601String()])
                ->log("Reservation {$reservation->reservation_code} was checked out automatically at its scheduled end time.");

            return true;
        }, 5);
    }

    /**
     * Cancel a reservation.
     *
     *
     * @throws ReservationCancellationException
     */
    private function requireCleaning(Reservation $reservation): void
    {
        foreach ($this->lockFacilityGraph($reservation->facility) as $facility) {
            if ($facility->isRoom()) {
                $facility->update(['cleaning_required_at' => now(), 'cleaning_completed_at' => null]);
                activity()->performedOn($facility)->event('cleaning_required')
                    ->withProperties(['reservation_id' => $reservation->id])->log('Check out recorded; room awaits staff cleaning release.');
            }
        }
    }

    public function completeCleaning(Facility $facility, User $staff): void
    {
        abort_unless($staff->hasRole(Role::Administrator->value), 403);
        DB::transaction(function () use ($facility, $staff) {
            $locked = Facility::whereKey($facility->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isRoom() || ! $locked->cleaning_required_at) {
                throw new InvalidReservationTransitionException('This room is not awaiting cleaning.');
            }
            $claims = [$locked->id, ...$locked->partOfPackages()->pluck('facilities.id')->all()];
            if (Reservation::whereIn('facility_id', $claims)->whereNotNull('checked_in_at')->whereNull('checked_out_at')->exists()) {
                throw new InvalidReservationTransitionException('An in-house stay must depart before cleaning release.');
            }
            $locked->update(['cleaning_required_at' => null, 'cleaning_completed_at' => now()]);
            activity()->performedOn($locked)->causedBy($staff)->event('cleaning_completed')->log('Staff marked room cleaning complete.');
        }, 5);
    }

    public static function earlyArrivalHours(float $minutes): int
    {
        return $minutes < 20 ? 0 : (int) ceil($minutes / 60);
    }

    public function reviewArrival(Reservation $reservation, User $staff): void
    {
        abort_unless($staff->hasRole(Role::Administrator->value), 403);
        DB::transaction(function () use ($reservation, $staff) {
            $this->lockFacilityGraph($reservation->facility);
            if ($reservation->reservation_group_id) \App\Models\ReservationGroup::whereKey($reservation->reservation_group_id)->lockForUpdate()->firstOrFail();
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== ReservationStatus::Approved || $locked->checked_in_at) {
                throw new InvalidReservationTransitionException('Review check in charges before physical check-in on an approved booking.');
            }
            $flow = $locked->reservationGroup?->booking_workflow ?? $locked->booking_workflow ?? [];
            if (isset($flow['refund'])) throw new InvalidReservationTransitionException('Resolve the cancellation/refund discussion before reviewing check in.');
            $this->applyArrivalHours($locked, $staff);
        }, 5);
        $reservation->refresh();
    }

    private function applyArrivalHours(Reservation $reservation, User $staff): void
    {
        if ($reservation->policy_version !== config('resort.policy.confirmed_version') || ! $reservation->facility->isRoom()
            || $reservation->check_in->isSameDay($reservation->check_out)) {
            return;
        }
        $arrival = ResortTime::databaseNow();
        $minutes = max(0, $arrival->diffInMinutes($reservation->check_in, false));
        $hours = self::earlyArrivalHours($minutes);
        if ($hours > (int) config('resort.stay.max_additional_hours', 12)) {
            throw new InvalidReservationTransitionException('This exceptional check in requires a separate staff arrangement.');
        }
        $facilities = $this->lockFacilityGraph($reservation->facility);
        $available = $this->availabilityService->isAvailable($facilities->firstWhere('id', $reservation->facility_id),
            $arrival, $reservation->occupancyEnd(), $reservation->id, $reservation->guest_count, lockForUpdate: true);
        if (! $available->isAvailable) {
            throw new InvalidReservationTransitionException($available->reason);
        }
        $hours = max($hours, (int) $reservation->early_check_in_hours);
        $alreadyApplied = array_sum(array_column(array_filter($reservation->booking_workflow['adjustments'] ?? [], fn ($entry) => $entry['kind'] === 'early_arrival'), 'amount_cents'));
        $fee = max(0, $hours * 20000 - (int) ($reservation->pricing_breakdown['early_check_in_charge'] ?? 0) - $alreadyApplied);
        if ($fee > 0) {
            app(BookingPracticeService::class)->addAdjustment($reservation, $staff, $fee, 'Actual early check in: '.$minutes.' minutes', 'early_arrival');
        }
        $reservation->update(['early_check_in_hours' => $hours]);
    }

    public function cancel(Reservation $reservation, User $actor, ?string $reason = null): void
    {
        try {
            $this->changeStatus($reservation, ReservationStatus::Cancelled, $actor, $reason);
        } catch (InvalidReservationTransitionException $e) {
            throw new ReservationCancellationException("Reservation cannot be cancelled from its current status: {$reservation->status->label()}");
        }
    }

    /**
     * Refuse a self-service booking once the account is already holding the
     * maximum number of unapproved reservations.
     *
     * Pending is the right thing to count, and the only thing that needs
     * counting: `blockingStatuses()` holds a space off the market for Pending and
     * Approved alike, and a reservation only leaves Pending by being approved,
     * rejected, withdrawn or expired — so this is exactly "spaces this guest is
     * sitting on without having paid for them".
     *
     * The rows are read under lock, inside the caller's transaction, so two
     * submissions racing each other cannot both see the last free slot. The
     * refusal is a `ReservationConflictException` on purpose: that is the class
     * the guest's own booking form already catches and shows as a message.
     *
     * @throws ReservationConflictException
     */
    private function assertWithinPendingHoldLimit(User $actor): void
    {
        $limit = (int) config('resort.policy.max_pending_holds', 5);

        if ($limit <= 0) {
            return;
        }

        $held = Reservation::where('user_id', $actor->id)
            ->where('status', ReservationStatus::Pending->value)
            ->lockForUpdate()
            ->count();

        if ($held >= $limit) {
            throw new ReservationConflictException(sprintf(
                'This account already has %d reservations waiting for approval, which is the most one account can hold at a time. Finish or withdraw one, or call the resort at %s to arrange a group booking.',
                $held,
                config('resort.phone')
            ));
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
    public function lockFacilityGraph(Facility|array $facilities): Collection
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
