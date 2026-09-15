<?php

namespace App\Services\Customer;

use App\DTOs\AvailabilityResult;
use App\DTOs\PriceBreakdown;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Exceptions\Customer\ReservationOwnershipException;
use App\Exceptions\Customer\ReservationPolicyException;
use App\Exceptions\Reservation\ReservationCancellationException;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\Reservation\ReservationWithdrawalRequestedNotification;
use App\Services\AvailabilityService;
use App\Services\PricingService;
use App\Services\ReservationService;
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
        $now = now();
        $counts = Reservation::query()
            ->where('user_id', $customer->id)
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_count', [ReservationStatus::Pending->value])
            ->selectRaw('SUM(CASE WHEN status = ? AND check_in > ? THEN 1 ELSE 0 END) as upcoming_count', [ReservationStatus::Approved->value, $now])
            ->selectRaw('SUM(CASE WHEN status = ? AND check_in <= ? AND check_out > ? THEN 1 ELSE 0 END) as current_count', [ReservationStatus::Approved->value, $now, $now])
            ->selectRaw('SUM(CASE WHEN status = ? OR (status = ? AND check_out <= ?) THEN 1 ELSE 0 END) as completed_count', [ReservationStatus::Completed->value, ReservationStatus::Approved->value, $now])
            ->selectRaw('SUM(CASE WHEN status IN (?, ?, ?, ?) THEN 1 ELSE 0 END) as cancelled_count', [
                ReservationStatus::Expired->value,
                ReservationStatus::Cancelled->value,
                ReservationStatus::Rejected->value,
                ReservationStatus::Rescheduled->value,
            ])
            ->first();

        return [
            ['key' => 'upcoming', 'label' => 'Upcoming', 'count' => (int) ($counts?->upcoming_count ?? 0)],
            ['key' => 'pending', 'label' => config('payments.deposit_workflow') ? 'Action / review pending' : 'Awaiting approval', 'count' => (int) ($counts?->pending_count ?? 0)],
            ['key' => 'current', 'label' => 'Current stay', 'count' => (int) ($counts?->current_count ?? 0)],
            ['key' => 'completed', 'label' => 'Completed', 'count' => (int) ($counts?->completed_count ?? 0)],
            ['key' => 'cancelled', 'label' => 'Cancelled / declined / expired', 'count' => (int) ($counts?->cancelled_count ?? 0)],
        ];
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

        return Reservation::where('user_id', $customer->id)
            ->forCustomerTab($tab)
            ->with(['facility', 'payments', 'swimmingSession'])
            ->latest()
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
     * Cancel a reservation owned by the customer.
     *
     * Verifies ownership and the published free-cancellation window before
     * delegating to ReservationService::cancel().
     *
     * @throws ReservationOwnershipException
     * @throws ReservationPolicyException
     * @throws ReservationCancellationException
     */
    public function cancelReservation(User $customer, Reservation $reservation, ?string $reason = null): void
    {
        $this->verifyOwnership($customer, $reservation);

        $cutoffHours = $this->cancellationCutoffHours();

        if (! $this->isWithinCutoff($reservation, $cutoffHours)) {
            throw new ReservationPolicyException(
                "Online cancellation closes {$cutoffHours} hours before check-in. Please contact the resort directly to cancel this reservation."
            );
        }

        if ($reservation->status !== ReservationStatus::Approved) {
            throw new ReservationCancellationException(
                'This reservation cannot be cancelled online from its current status.'
            );
        }

        $this->reservationService->cancel($reservation, $customer, $reason);
    }

    /**
     * Ask staff to withdraw a pending reservation. Status stays Pending until staff confirm.
     */
    public function requestCancellation(User $customer, Reservation $reservation, ?string $reason = null): void
    {
        $this->verifyOwnership($customer, $reservation);

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

        $cutoffHours = $this->cancellationCutoffHours();

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
        return $this->availabilityService->isAvailable(
            $facility,
            $checkIn,
            $checkOut,
            $excludeReservationId,
            $guestCount,
            $earlyCheckInHours,
            $lateCheckOutHours,
            paxTier: $paxTier
        );
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
     * Check if a reservation can be cancelled.
     */
    public function canCancel(Reservation $reservation): bool
    {
        return $reservation->status === ReservationStatus::Approved
            && (! $reservation->deposit_required || ($reservation->getVerifiedTotalPaid() <= 0 && $reservation->getPendingVerificationTotal() <= 0))
            && $this->isWithinCutoff($reservation, $this->cancellationCutoffHours());
    }

    public function canRequestCancellation(Reservation $reservation): bool
    {
        return $reservation->status === ReservationStatus::Pending
            && $reservation->cancellation_requested_at === null
            && $this->isWithinCutoff($reservation, $this->cancellationCutoffHours());
    }

    /**
     * Check if a reservation can be rescheduled.
     */
    public function canReschedule(Reservation $reservation): bool
    {
        return $reservation->swimming_session_id === null
            && (! $reservation->deposit_required || ($reservation->getVerifiedTotalPaid() <= 0 && $reservation->getPendingVerificationTotal() <= 0))
            && in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Approved], true)
            && $this->isWithinCutoff($reservation, $this->rescheduleCutoffHours());
    }

    public function cancellationCutoffHours(): int
    {
        return (int) config('resort.policy.cancellation_cutoff_hours', 48);
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
