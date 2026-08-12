<?php

namespace App\Services\Customer;

use App\DTOs\AvailabilityResult;
use App\DTOs\PriceBreakdown;
use App\Enums\ReservationSource;
use App\Exceptions\Customer\ReservationOwnershipException;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\PricingService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

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
     * Retrieve all reservations belonging to the authenticated customer.
     *
     * @return Collection<int, Reservation>
     */
    public function getMyReservations(User $customer): Collection
    {
        return Reservation::where('user_id', $customer->id)
            ->with(['facility', 'payments'])
            ->latest()
            ->get();
    }

    /**
     * Retrieve a specific reservation belonging to the authenticated customer.
     *
     * @throws ReservationOwnershipException
     */
    public function getMyReservation(User $customer, int $reservationId): Reservation
    {
        $reservation = Reservation::with(['facility', 'payments'])->findOrFail($reservationId);

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
     * Verifies ownership before delegating to ReservationService::cancel().
     *
     * @throws ReservationOwnershipException
     * @throws \App\Exceptions\Reservation\ReservationCancellationException
     */
    public function cancelReservation(User $customer, Reservation $reservation, ?string $reason = null): void
    {
        $this->verifyOwnership($customer, $reservation);

        $this->reservationService->cancel($reservation, $customer, $reason);
    }

    /**
     * Reschedule a reservation owned by the customer.
     *
     * @throws ReservationOwnershipException
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

        // Optional: add business rules here (e.g., cannot reschedule if < 48 hours to check-in)

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
    public function checkAvailability(Facility $facility, Carbon $checkIn, Carbon $checkOut): AvailabilityResult
    {
        return $this->availabilityService->isAvailable($facility, $checkIn, $checkOut);
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
        ?int $guestCount = null
    ): PriceBreakdown {
        return $this->pricingService->calculate(
            $facility,
            $checkIn,
            $checkOut,
            $earlyCheckInHours,
            $lateCheckOutHours,
            $guestCount
        );
    }
}
