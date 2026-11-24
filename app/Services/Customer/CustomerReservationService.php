<?php

namespace App\Services\Customer;

use App\DTOs\AvailabilityResult;
use App\DTOs\PriceBreakdown;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Exceptions\Customer\ReservationOwnershipException;
use App\Exceptions\Customer\ReservationPolicyException;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\Reservation\ReservationWithdrawalRequestedNotification;
use App\Services\AvailabilityService;
use App\Services\PricingService;
use App\Services\ReservationService;
use App\Services\ReservationSchedule;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

class CustomerReservationService
{
    public function __construct(
        private readonly ReservationService $reservationService,
        private readonly AvailabilityService $availabilityService,
        private readonly PricingService $pricingService
    ) {}

    /**
     * Verify that the customer owns the given reservation.
     *
     * @throws ReservationOwnershipException
     */
    private function verifyOwnership(User $customer, Reservation $reservation): void
    {
        if ($reservation->user_id !== $customer->id) {
            throw new ReservationOwnershipException('You do not have permission to access this reservation.');
        }
    }

    /**
     * @return list<array{key: string, label: string, count: int}>
     */
    public function reservationTabs(User $customer): array
    {
        $counts = \Illuminate\Support\Facades\DB::query()
            ->fromSub($this->bookingRecords($customer), 'bookings')
            ->selectRaw('booking_rank, COUNT(*) as total')
            ->groupBy('booking_rank')->pluck('total', 'booking_rank');
        $tabs = [
            ['key' => 'pending', 'label' => config('payments.deposit_workflow') ? 'Review pending' : 'Awaiting approval', 'count' => (int) ($counts[1] ?? 0)],
            ['key' => 'upcoming', 'label' => 'Confirmed', 'count' => (int) ($counts[2] ?? 0)],
            ['key' => 'current', 'label' => 'Current stay', 'count' => (int) ($counts[0] ?? 0)],
            ['key' => 'completed', 'label' => 'Completed', 'count' => (int) ($counts[3] ?? 0)],
            ['key' => 'cancelled', 'label' => 'Closed bookings', 'count' => (int) ($counts[4] ?? 0)],
        ];

        // This last tab names the ways a booking ends without a stay. A guest who
        // has never had one does not need a tab describing the possibility, so it
        // stays out of sight until there is something in it. A guest who does have
        // that history keeps every record of it — the resort can still cancel a
        // booking from the back office, and this is where the guest sees that.
        return array_values(array_filter(
            $tabs,
            fn (array $tab): bool => $tab['key'] !== 'cancelled' || $tab['count'] > 0
        ));
    }

    /** One record per booking, assigned to its most actionable service tab. */
    private function bookingRecords(User $customer): \Illuminate\Database\Eloquent\Builder
    {
        return Reservation::query()->where('user_id', $customer->id)
            ->selectRaw('MAX(reservations.id) as id')
            ->selectRaw('MIN(CASE
                WHEN status = ? AND check_in <= ? AND check_out > ? THEN 0
                WHEN status = ? THEN 1
                WHEN status = ? AND check_in > ? THEN 2
                WHEN status = ? OR (status = ? AND check_out <= ?) THEN 3
                ELSE 4 END) as booking_rank', [
                ReservationStatus::Approved->value, now(), now(),
                ReservationStatus::Pending->value,
                ReservationStatus::Approved->value, now(),
                ReservationStatus::Completed->value, ReservationStatus::Approved->value, now(),
            ])
            ->groupBy('reservation_group_id')
            ->groupByRaw('CASE WHEN reservation_group_id IS NULL THEN reservations.id ELSE 0 END');
    }
    public function resolveReservationTab(string $requested, array $tabs): string
    {
        $keys = array_column($tabs, 'key');

        if (in_array($requested, $keys, true)) {
            return $requested;
        }

        foreach ($tabs as $tab) {
            if ($tab['count'] > 0) {
                return $tab['key'];
            }
        }

        return 'upcoming';
    }

    /**
     * Retrieve reservations belonging to the authenticated customer for one dashboard tab.
     *
     * @return LengthAwarePaginator<int, Reservation>
     */
    public function getMyReservations(User $customer, ?string $tab = 'upcoming'): LengthAwarePaginator
    {
        $tab = in_array($tab, Reservation::customerTabKeys(), true) ? $tab : 'upcoming';

        $rank = ['current' => 0, 'pending' => 1, 'upcoming' => 2, 'completed' => 3, 'cancelled' => 4][$tab];
        $ids = \Illuminate\Support\Facades\DB::query()
            ->fromSub($this->bookingRecords($customer), 'bookings')
            ->where('booking_rank', $rank)->select('id');

        return Reservation::where('user_id', $customer->id)
            ->whereIn('id', $ids)
            ->with(['facility', 'payments', 'swimmingSession', 'reservationGroup.reservations' => fn ($query) => $query->where('user_id', $customer->id)->with(['facility', 'payments'])])
            ->latest()->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();
    }

    /**
     * Retrieve a specific reservation belonging to the authenticated customer.
     *
     * @throws ReservationOwnershipException
     */
    public function getMyReservation(User $customer, int $reservationId): Reservation
    {
        $reservation = Reservation::with(['facility', 'payments', 'swimmingSession'])->findOrFail($reservationId);

        $this->verifyOwnership($customer, $reservation);

        return $reservation;
    }

    /**
     * Create a reservation for the customer.
     *
     * Always forces ReservationSource::Online. Customers cannot create walk-in reservations.
     * All availability, pricing, concurrency, and code generation logic is delegated to ReservationService.
     */
    public function createReservation(
        User $customer,
        Facility $facility,
        Carbon $checkIn,
        Carbon $checkOut,
        int $guestCount,
        array $options = []
    ): Reservation {
        return $this->reservationService->createReservation(
            $facility,
            $customer,
            $checkIn,
            $checkOut,
            ReservationSource::Online,
            $guestCount,
            $options
        );
    }

    /**
     * Ask staff to withdraw a pending reservation. Status stays Pending until staff confirm.
     */
    public function requestCancellation(User $customer, Reservation $reservation, ?string $reason = null): void
    {
        $this->verifyOwnership($customer, $reservation);
        if ($reservation->policy_version === config('resort.policy.confirmed_version')) {
            app(\App\Services\BookingPracticeService::class)->requestCancellation($reservation, $customer, $reason ?: 'Guest requests cancellation.');
            return;
        }

        if (! $this->canRequestCancellation($reservation)) {
            if ($reservation->cancellation_requested_at !== null) {
                throw new ReservationPolicyException(
                    'A withdrawal request is already waiting for the resort to confirm. If you changed your mind, call the resort.'
                );
            }

            throw new ReservationPolicyException(
                'This reservation cannot be withdrawn online. Please contact the resort.'
            );
        }

        $cutoffHours = $this->withdrawalCutoffHours();

        if (! $this->isWithinCutoff($reservation, $cutoffHours)) {
            throw new ReservationPolicyException(
                "Online withdrawal closes {$cutoffHours} hours before check-in. Please contact the resort directly."
            );
        }

        $reservation->update([
            'cancellation_requested_at' => now(),
            'cancellation_request_reason' => $reason,
        ]);

        activity()
            ->performedOn($reservation)
            ->causedBy($customer)
            ->event('withdrawal_requested')
            ->log('Guest requested withdrawal of a pending reservation.');

        try {
            $admins = User::role(Role::Administrator->value)->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new ReservationWithdrawalRequestedNotification($reservation->fresh() ?? $reservation));
            }
        } catch (RoleDoesNotExist) {
        }
    }

    /**
     * Reschedule a reservation owned by the customer.
     *
     * @throws ReservationOwnershipException
     * @throws ReservationPolicyException
     */
    public function rescheduleReservation(
        User $customer,
        Reservation $reservation,
        Facility $newFacility,
        Carbon $newCheckIn,
        Carbon $newCheckOut,
        int $newGuestCount,
        array $options = []
    ): Reservation {
        $this->verifyOwnership($customer, $reservation);

        if ($reservation->policy_version === config('resort.policy.confirmed_version')) {
            app(\App\Services\BookingPracticeService::class)->proposeChange($reservation, $customer, [
                'facility_id' => $newFacility->id, 'check_in' => $newCheckIn->toDateTimeString(), 'check_out' => $newCheckOut->toDateTimeString(),
                'guest_count' => $newGuestCount, ...$options,
            ]);
            return $reservation->refresh();
        }
        $cutoffHours = $this->rescheduleCutoffHours();

        if ($reservation->swimming_session_id !== null || $newFacility->isCottage()) {
            throw new ReservationPolicyException(
                'Cottage reservations use fixed swimming sessions. Please contact the resort if you need to change the date, session, or cottage.'
            );
        }

        if (! $this->isWithinCutoff($reservation, $cutoffHours)) {
            throw new ReservationPolicyException(
                "Online rescheduling closes {$cutoffHours} hours before check-in. Please contact the resort directly to move this reservation."
            );
        }

        return $this->reservationService->reschedule(
            $reservation,
            $newFacility,
            $newCheckIn,
            $newCheckOut,
            $customer,
            $newGuestCount,
            $options
        );
    }

    /**
     * Check facility availability for a requested period.
     *
     * Delegates entirely to AvailabilityService::isAvailable().
     */
    public function checkAvailability(
        Facility $facility,
        Carbon $checkIn,
        Carbon $checkOut,
        ?int $excludeReservationId = null,
        ?int $guestCount = null,
        int $earlyCheckInHours = 0,
        int $lateCheckOutHours = 0,
        ?int $paxTier = null
    ): AvailabilityResult {
        app(ReservationSchedule::class)->validate($facility, $checkIn, $checkOut, $earlyCheckInHours, $lateCheckOutHours);

        $result = $this->availabilityService->isAvailable(
            $facility,
            $checkIn,
            $checkOut,
            $excludeReservationId,
            $guestCount,
            $earlyCheckInHours,
            $lateCheckOutHours,
            paxTier: $paxTier
        );

        if (! $result->isAvailable && $facility->isRoom() && $earlyCheckInHours > 0) {
            return new AvailabilityResult(false, 'Early check-in is unavailable: '.$result->reason.' Try standard 2 PM check-in or another date.', $result->conflictingReservationId, $result->status());
        }

        return $result;
    }

    /**
     * Get an estimated price for a reservation period.
     *
     * Delegates entirely to PricingService::calculate().
     */
    public function getEstimatedPrice(
        Facility $facility,
        Carbon $checkIn,
        Carbon $checkOut,
        int $earlyCheckInHours = 0,
        int $lateCheckOutHours = 0,
        ?int $guestCount = null,
        ?int $paxTier = null
    ): PriceBreakdown {
        app(ReservationSchedule::class)->validate($facility, $checkIn, $checkOut, $earlyCheckInHours, $lateCheckOutHours);

        return $this->pricingService->calculate(
            $facility,
            $checkIn,
            $checkOut,
            $earlyCheckInHours,
            $lateCheckOutHours,
            $guestCount,
            $paxTier
        );
    }

    /**
     * Check if the guest may ask staff to withdraw this reservation.
     *
     * Pending only, and not already asked. Withdrawal never cancels anything,
     * so a reservation the resort has already approved is refused here.
     */
    public function canRequestCancellation(Reservation $reservation): bool
    {
        if ($reservation->policy_version === config('resort.policy.confirmed_version')) {
            $booking = app(\App\Services\BookingPracticeService::class)->target($reservation);
            return ! $reservation->checked_in_at && ! isset($booking->booking_workflow['refund'])
                && (! $reservation->reservation_group_id || ! $reservation->reservationGroup->reservations()->whereNotNull('checked_in_at')->exists());
        }
        return $reservation->status === ReservationStatus::Pending
            && $reservation->cancellation_requested_at === null
            && $this->isWithinCutoff($reservation, $this->withdrawalCutoffHours());
    }

    /**
     * Check if a reservation can be rescheduled.
     */
    public function canReschedule(Reservation $reservation): bool
    {
        if ($reservation->policy_version === config('resort.policy.confirmed_version')) {
            return ! $reservation->checked_in_at && in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Approved], true)
                && (! $reservation->reservation_group_id || ! $reservation->reservationGroup->reservations()->whereNotNull('checked_in_at')->exists());
        }
        return $reservation->swimming_session_id === null
            && (! $reservation->deposit_required || ($reservation->getVerifiedTotalPaid() <= 0 && $reservation->getPendingVerificationTotal() <= 0))
            && in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Approved], true)
            && $this->isWithinCutoff($reservation, $this->rescheduleCutoffHours());
    }

    /**
     * How long before check-in a guest may still ask staff to withdraw.
     */
    public function withdrawalCutoffHours(): int
    {
        return (int) config('resort.policy.withdrawal_cutoff_hours', 48);
    }

    public function rescheduleCutoffHours(): int
    {
        return (int) config('resort.policy.reschedule_cutoff_hours', 48);
    }

    /**
     * The moment after which the guest can no longer self-serve this reservation.
     */
    public function selfServiceDeadline(Reservation $reservation, int $cutoffHours): Carbon
    {
        return $reservation->selfServiceDeadline($cutoffHours);
    }

    private function isWithinCutoff(Reservation $reservation, int $cutoffHours): bool
    {
        return now()->lessThan($this->selfServiceDeadline($reservation, $cutoffHours));
    }
}
