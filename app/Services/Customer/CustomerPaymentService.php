<?php

namespace App\Services\Customer;

use App\Enums\PaymentMethod;
use App\Exceptions\Customer\ReservationOwnershipException;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Database\Eloquent\Collection;

class CustomerPaymentService
{
    public function __construct(
        private readonly PaymentService $paymentService
    ) {}

    /**
     * Verify that the customer owns the reservation associated with the payment operation.
     *
     * @throws ReservationOwnershipException
     */
    private function verifyReservationOwnership(User $customer, Reservation $reservation): void
    {
        if ($reservation->user_id !== $customer->id) {
            throw new ReservationOwnershipException('You do not have permission to access this reservation.');
        }
    }

    /**
     * Retrieve all payments for a reservation owned by the customer.
     *
     * @return Collection<int, Payment>
     *
     * @throws ReservationOwnershipException
     */
    public function getPaymentsForReservation(User $customer, Reservation $reservation): Collection
    {
        $this->verifyReservationOwnership($customer, $reservation);

        return $reservation->payments()->get();
    }

    /**
     * Submit a payment for a reservation owned by the customer.
     *
     * Verifies ownership before delegating to PaymentService::createPayment().
     * PaymentService independently re-validates ownership as defense-in-depth.
     *
     * @param string|int|float $amount
     *
     * @throws ReservationOwnershipException
     * @throws \App\Exceptions\Payment\PaymentOwnershipException
     * @throws \App\Exceptions\Payment\InvalidPaymentAmountException
     */
    public function submitPayment(
        User $customer,
        Reservation $reservation,
        $amount,
        PaymentMethod $method,
        ?string $proofReference = null
    ): Payment {
        $this->verifyReservationOwnership($customer, $reservation);

        return $this->paymentService->createPayment(
            $reservation,
            $customer,
            $amount,
            $method,
            $proofReference
        );
    }
}
