<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\Payment\InvalidPaymentAmountException;
use App\Exceptions\Payment\PaymentOwnershipException;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;

/**
 * Live e-wallet checkout. Deliberately a thin layer over the same Payment
 * records used by manual proof upload, so a reservation's ledger reads the same
 * whichever way the guest paid.
 */
class OnlinePaymentService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly PaymentService $paymentService
    ) {}

    public function isAvailable(): bool
    {
        return $this->gateway->isConfigured();
    }

    public function gatewayLabel(): string
    {
        return $this->gateway->label();
    }

    /**
     * @return string The URL to send the guest to.
     *
     * @throws PaymentOwnershipException
     * @throws InvalidPaymentAmountException
     * @throws \App\Exceptions\Payment\PaymentGatewayException
     */
    public function startCheckout(User $customer, Reservation $reservation, float $amount, PaymentMethod $method): string
    {
        $this->verifyOwnership($customer, $reservation);

        $balance = $reservation->getRemainingBalance();

        if ($amount <= 0) {
            throw new InvalidPaymentAmountException('Payment amount must be greater than zero.');
        }

        if ($amount > $balance) {
            throw new InvalidPaymentAmountException(
                'Payment amount cannot exceed the remaining balance of ₱' . number_format($balance, 2) . '.'
            );
        }

        return DB::transaction(function () use ($reservation, $amount, $method) {
            $payment = Payment::create([
                'reservation_id' => $reservation->id,
                'amount' => number_format($amount, 2, '.', ''),
                'method' => $method->value,
                'payment_date' => now()->toDateString(),
                'status' => PaymentStatus::Pending->value,
            ]);

            $session = $this->gateway->createCheckout(
                $payment->load('reservation.facility'),
                route('customer.payments.return', $payment),
                route('customer.payments.cancel', $payment)
            );

            $payment->update(['reference_number' => $session->id]);

            activity()
                ->performedOn($payment)
                ->event('checkout_started')
                ->log("Online checkout started for reservation {$reservation->reservation_code}.");

            return $session->redirectUrl;
        });
    }

    /**
     * Ask the provider whether the money arrived, and record it if so.
     *
     * @throws PaymentOwnershipException
     */
    public function confirm(User $customer, Payment $payment): bool
    {
        $this->verifyOwnership($customer, $payment->reservation);

        if ($payment->status === PaymentStatus::Verified) {
            return true;
        }

        if ($payment->status !== PaymentStatus::Pending || ! $payment->reference_number) {
            return false;
        }

        if (! $this->gateway->isPaid($payment->reference_number)) {
            return false;
        }

        $this->paymentService->verifyPaymentViaGateway($payment, $payment->reference_number);

        return true;
    }

    /**
     * The guest backed out at the provider. Drop the placeholder so it does not
     * sit in the ledger as an unexplained pending payment.
     *
     * @throws PaymentOwnershipException
     */
    public function abandon(User $customer, Payment $payment): void
    {
        $this->verifyOwnership($customer, $payment->reservation);

        if ($payment->status === PaymentStatus::Pending) {
            $payment->delete();
        }
    }

    /**
     * @throws PaymentOwnershipException
     */
    private function verifyOwnership(User $customer, Reservation $reservation): void
    {
        if ($reservation->user_id !== $customer->id) {
            throw new PaymentOwnershipException('You are not authorized to pay for this reservation.');
        }
    }
}
