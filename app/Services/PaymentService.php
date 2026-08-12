<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Exceptions\Payment\InvalidPaymentAmountException;
use App\Exceptions\Payment\InvalidPaymentTransitionException;
use App\Exceptions\Payment\PaymentOwnershipException;
use App\Exceptions\Payment\UnauthorizedPaymentActionException;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * @param string|int|float $amount
     */
    public function createPayment(
        Reservation $reservation,
        User $submitter,
        $amount,
        PaymentMethod $method,
        ?string $proofReference = null
    ): Payment {
        // Validate amount
        if (!is_numeric($amount) || $amount <= 0) {
            throw new InvalidPaymentAmountException('Payment amount must be a positive value.');
        }

        // Verify ownership/authorization
        // Submitter must own the reservation OR be an authorized staff/admin
        if ($submitter->id !== $reservation->user_id && !$submitter->hasRole([Role::Administrator->value, 'staff'])) {
            throw new PaymentOwnershipException('You are not authorized to submit a payment for this reservation.');
        }

        return DB::transaction(function () use (
            $reservation,
            $submitter,
            $amount,
            $method,
            $proofReference
        ) {
            // Determine deterministic decimal amount
            $decimalAmount = number_format((float) $amount, 2, '.', '');

            $payment = Payment::create([
                'reservation_id' => $reservation->id,
                'amount' => $decimalAmount,
                'method' => $method->value,
                'reference_number' => null, // Can be extended if needed
                'payment_date' => now()->toDateString(),
                'proof_path' => $proofReference,
                'status' => PaymentStatus::Pending->value,
                'verified_by' => null,
                'verified_at' => null,
                'rejection_reason' => null,
            ]);

            activity()
                ->performedOn($payment)
                ->causedBy($submitter)
                ->event('payment_submitted')
                ->log("Payment submitted for reservation {$reservation->reservation_code}");

            return $payment;
        });
    }

    public function verifyPayment(Payment $payment, User $verifier): void
    {
        if (!$verifier->hasRole([Role::Administrator->value, 'staff'])) {
            throw new UnauthorizedPaymentActionException('Only authorized staff can verify payments.');
        }

        DB::transaction(function () use ($payment, $verifier) {
            // Lock payment row
            $lockedPayment = Payment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status !== PaymentStatus::Pending) {
                throw new InvalidPaymentTransitionException("Cannot verify a payment that is currently {$lockedPayment->status->value}.");
            }

            $lockedPayment->update([
                'status' => PaymentStatus::Verified->value,
                'verified_by' => $verifier->id,
                'verified_at' => now(),
            ]);

            activity()
                ->performedOn($lockedPayment)
                ->causedBy($verifier)
                ->event('payment_verified')
                ->withProperties(['previous_status' => PaymentStatus::Pending->value, 'new_status' => PaymentStatus::Verified->value])
                ->log("Payment verified by {$verifier->name}");
        });
    }

    public function rejectPayment(Payment $payment, User $verifier, string $reason): void
    {
        if (!$verifier->hasRole([Role::Administrator->value, 'staff'])) {
            throw new UnauthorizedPaymentActionException('Only authorized staff can reject payments.');
        }

        DB::transaction(function () use ($payment, $verifier, $reason) {
            // Lock payment row
            $lockedPayment = Payment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status !== PaymentStatus::Pending) {
                throw new InvalidPaymentTransitionException("Cannot reject a payment that is currently {$lockedPayment->status->value}.");
            }

            $lockedPayment->update([
                'status' => PaymentStatus::Rejected->value,
                'rejection_reason' => $reason,
                // Do we set verified_by for rejections? The requirements say:
                // "verified_by / verifier field should be handled according to existing schema semantics."
                // I will set verified_by for rejection too, since it tracks who acted on it.
                'verified_by' => $verifier->id,
                'verified_at' => now(),
            ]);

            activity()
                ->performedOn($lockedPayment)
                ->causedBy($verifier)
                ->event('payment_rejected')
                ->withProperties(['previous_status' => PaymentStatus::Pending->value, 'new_status' => PaymentStatus::Rejected->value])
                ->log("Payment rejected by {$verifier->name}");
        });
    }
}
