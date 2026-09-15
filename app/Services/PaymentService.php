<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Exceptions\Payment\InvalidPaymentAmountException;
use App\Exceptions\Payment\InvalidPaymentTransitionException;
use App\Exceptions\Payment\PaymentNotEligibleException;
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
        ?string $proofReference = null,
        array $details = []
    ): Payment {
        if (! is_numeric($amount) || ! is_finite((float) $amount) || round((float) $amount * 100) < 1) {
            throw new InvalidPaymentAmountException('Payment amount must be a positive value.');
        }

        return DB::transaction(function () use ($reservation, $submitter, $amount, $method, $proofReference, $details) {
            $reservation = $this->lockReservationLedger($reservation->id);
            $isStaff = $submitter->hasRole([Role::Administrator->value, 'staff']);

            if ($submitter->id !== $reservation->user_id && ! $isStaff) {
                throw new PaymentOwnershipException('You are not authorized to submit a payment for this reservation.');
            }

            if (! $isStaff && ! $reservation->canAcceptGuestPayment()) {
                throw new PaymentNotEligibleException(
                    config('payments.deposit_workflow') ? 'Online payment is limited to the required deposit before its deadline. Contact staff for expired holds or pay the remaining balance at arrival.' : 'Payment is available after the resort confirms this reservation, and only while a balance remains.'
                );
            }

            $deposit = config('payments.deposit_workflow') && ! $isStaff;
            $destination = null;
            if ($deposit) {
                $destination = \App\Models\PaymentDestination::whereKey($details['payment_destination_id'] ?? 0)
                    ->where('method', $method->value)->lockForUpdate()->first();
                if (! $destination || (! $destination->is_active && $destination->updated_at->lt($reservation->created_at)) || ! $proofReference) {
                    throw new PaymentNotEligibleException('Choose the resort receiving account used for this booking and upload proof of payment.');
                }
            }
            $balance = $deposit ? $reservation->getDepositAvailableAmount() : $reservation->getAvailablePaymentAmount();

            if ((int) round((float) $amount * 100) > (int) round($balance * 100)) {
                throw new InvalidPaymentAmountException(
                    'Payment amount cannot exceed ₱'.number_format($balance, 2).' after verified payments and pending submissions.'
                );
            }

            // Determine deterministic decimal amount
            $decimalAmount = number_format((float) $amount, 2, '.', '');

            $payment = Payment::create([
                'reservation_id' => $reservation->id,
                'amount' => $decimalAmount,
                'method' => $method->value,
                'reference_number' => isset($details['reference_number']) ? strtoupper(trim($details['reference_number'])) : null,
                'payment_date' => $details['payment_date'] ?? now()->toDateString(),
                'payment_destination_id' => $destination?->id,
                'destination_snapshot' => $destination?->snapshot(),
                'source' => $deposit ? 'customer_deposit' : 'legacy',
                'proof_hash' => $details['proof_hash'] ?? null,
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

            if ($deposit) {
                DB::afterCommit(fn () => $reservation->user?->notify(new \App\Notifications\Reservation\DepositUpdateNotification($reservation->id, 'Deposit proof received. Staff will verify the incoming transfer.')));
            }

            return $payment;
        }, 5);
    }

    public function verifyPayment(Payment $payment, User $verifier, ?string $overpaymentNote = null): void
    {
        if (!$verifier->hasRole([Role::Administrator->value, 'staff'])) {
            throw new UnauthorizedPaymentActionException('Only authorized staff can verify payments.');
        }

        DB::transaction(function () use ($payment, $verifier, $overpaymentNote) {
            $reservation = $this->lockReservationLedger($payment->reservation_id);
            // Lock payment row
            $lockedPayment = Payment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status !== PaymentStatus::Pending) {
                throw new InvalidPaymentTransitionException("Cannot verify a payment that is currently {$lockedPayment->status->value}.");
            }

            $excess = $this->overpaymentCents($reservation, $lockedPayment);
            $duplicate = $lockedPayment->reference_number && Payment::where('method', $lockedPayment->method->value)
                ->where('reference_number', $lockedPayment->reference_number)->where('id', '!=', $lockedPayment->id)
                ->where('status', PaymentStatus::Verified)->exists();
            if ($duplicate && blank($overpaymentNote)) {
                throw new InvalidPaymentAmountException('This reference was already verified. Reconcile the transaction and enter a note before verifying.');
            }
            if ($excess > 0 && blank($overpaymentNote)) {
                throw new InvalidPaymentAmountException('This payment exceeds the remaining balance. Confirm the funds received and enter an overpayment reconciliation note before verifying it.');
            }

            $lockedPayment->update([
                'status' => PaymentStatus::Verified->value,
                'verified_by' => $verifier->id,
                'verified_at' => now(),
                'reconciliation_note' => $overpaymentNote,
            ]);

            $reservation->unsetRelation('payments');
            if ($lockedPayment->source === 'customer_deposit') {
                if ($reservation->status === \App\Enums\ReservationStatus::Pending && ! $reservation->cancellation_requested_at
                    && $reservation->getVerifiedTotalPaid() >= $reservation->requiredDepositAmount()) {
                    app(ReservationService::class)->changeStatus($reservation, \App\Enums\ReservationStatus::Approved, $verifier);
                } elseif ($reservation->status === \App\Enums\ReservationStatus::Pending && $reservation->getDepositAvailableAmount() > 0) {
                    $reservation->update(['deposit_due_at' => \App\Support\DepositDeadline::forVisit($reservation->occupancyStart())]);
                }
                DB::afterCommit(fn () => $reservation->user?->notify(new \App\Notifications\Reservation\DepositUpdateNotification($reservation->id, 'Your deposit payment was verified. View the reservation for its current status and any deposit shortfall.')));
            }

            activity()
                ->performedOn($lockedPayment)
                ->causedBy($verifier)
                ->event('payment_verified')
                ->withProperties([
                    'previous_status' => PaymentStatus::Pending->value,
                    'new_status' => PaymentStatus::Verified->value,
                    'overpayment_centavos' => $excess,
                    'overpayment_note' => $excess > 0 ? trim($overpaymentNote) : null,
                ])
                ->log("Payment verified by {$verifier->name}");
        }, 5);
    }

    /**
     * Verification driven by the payment provider rather than a staff member.
     * There is no human verifier here on purpose: the gateway confirmed the
     * funds, and recording a staff account as the verifier would be a lie in
     * the audit trail.
     *
     * @throws InvalidPaymentTransitionException
     */
    public function verifyPaymentViaGateway(Payment $payment, string $gatewayReference): void
    {
        DB::transaction(function () use ($payment, $gatewayReference) {
            $reservation = $this->lockReservationLedger($payment->reservation_id);
            $lockedPayment = Payment::withTrashed()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->reference_number && $lockedPayment->reference_number !== $gatewayReference) {
                throw new InvalidPaymentTransitionException('The gateway reference does not match this payment.');
            }

            if ($lockedPayment->status === PaymentStatus::Verified) {
                return;
            }

            if ($lockedPayment->status !== PaymentStatus::Pending) {
                throw new InvalidPaymentTransitionException("Cannot verify a payment that is currently {$lockedPayment->status->value}.");
            }

            if ($lockedPayment->trashed()) {
                $lockedPayment->restore();
            }

            $lockedPayment->update([
                'status' => PaymentStatus::Verified->value,
                'reference_number' => $gatewayReference,
                'verified_at' => now(),
            ]);

            activity()
                ->performedOn($lockedPayment)
                ->event('payment_verified')
                ->withProperties([
                    'gateway_reference' => $gatewayReference,
                    'overpayment_centavos' => $this->overpaymentCents($reservation, $lockedPayment),
                ])
                ->log('Payment verified automatically by the payment gateway.');
        }, 5);
    }

    public function rejectPayment(Payment $payment, User $verifier, string $reason): void
    {
        if (!$verifier->hasRole([Role::Administrator->value, 'staff'])) {
            throw new UnauthorizedPaymentActionException('Only authorized staff can reject payments.');
        }

        DB::transaction(function () use ($payment, $verifier, $reason) {
            $reservation = $this->lockReservationLedger($payment->reservation_id);
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

            if ($lockedPayment->source === 'customer_deposit') {
                $reservation->update(['deposit_due_at' => \App\Support\DepositDeadline::forVisit($reservation->occupancyStart())]);
                DB::afterCommit(fn () => $reservation->user?->notify(new \App\Notifications\Reservation\DepositUpdateNotification($reservation->id, 'Deposit proof needs correction: '.$reason)));
            }

            activity()
                ->performedOn($lockedPayment)
                ->causedBy($verifier)
                ->event('payment_rejected')
                ->withProperties(['previous_status' => PaymentStatus::Pending->value, 'new_status' => PaymentStatus::Rejected->value])
                ->log("Payment rejected by {$verifier->name}");
        }, 5);
    }

    private function lockReservationLedger(int $reservationId): Reservation
    {
        $reservation = Reservation::whereKey($reservationId)->lockForUpdate()->firstOrFail();
        $reservation->setRelation('payments', $reservation->payments()->orderBy('id')->lockForUpdate()->get());

        return $reservation;
    }

    public function requestDepositTopUp(Reservation $reservation, User $actor, string $reason): void
    {
        if (! $actor->hasRole('Administrator')) throw new UnauthorizedPaymentActionException('Only administrators can request a top-up.');
        DB::transaction(function () use ($reservation, $actor, $reason) {
            $locked = $this->lockReservationLedger($reservation->id);
            if ($locked->deposit_required === null || $locked->status !== \App\Enums\ReservationStatus::Pending
                || $locked->getPendingVerificationTotal() > 0 || $locked->getDepositAvailableAmount() <= 0 || \App\Support\DepositDeadline::visitInstant($locked->occupancyStart())->isPast()) {
                throw new InvalidPaymentAmountException('Resolve pending proofs first. Top-ups require a future, pending booking with a deposit shortfall.');
            }
            $locked->update(['deposit_due_at' => \App\Support\DepositDeadline::forVisit($locked->occupancyStart())]);
            $message = 'Please submit a deposit top-up of ₱'.number_format($locked->getDepositAvailableAmount(), 2).' by '.$locked->deposit_due_at->copy()->timezone(\App\Support\DepositDeadline::TIMEZONE)->format('M d, Y g:i A').' Philippine time. '.$reason;
            activity()->performedOn($locked)->causedBy($actor)->event('deposit_top_up_requested')->withProperties(['reason' => $reason])->log($message);
            DB::afterCommit(fn () => $locked->user?->notify(new \App\Notifications\Reservation\DepositUpdateNotification($locked->id, $message)));
        }, 5);
    }

    private function overpaymentCents(Reservation $reservation, Payment $payment): int
    {
        return max(0, (int) round((float) $payment->amount * 100)
            - (int) round($reservation->getRemainingBalance() * 100));
    }

    public function recordOnSite(Reservation $reservation, User $actor, array $data): Payment
    {
        if (! $actor->hasRole('Administrator')) throw new UnauthorizedPaymentActionException('Only administrators can record collected payments.');
        return DB::transaction(function () use ($reservation, $actor, $data) {
            $locked = $this->lockReservationLedger($reservation->id);
            $amount = (float) $data['amount'];
            if (! is_finite($amount) || round($amount * 100) < 1) throw new InvalidPaymentAmountException('Enter a positive amount.');
            if ($locked->getPendingVerificationTotal() > 0) throw new InvalidPaymentAmountException('Resolve pending proofs before recording another payment.');
            if (($amount > $locked->getRemainingBalance() || $locked->status !== \App\Enums\ReservationStatus::Approved) && blank($data['reconciliation_note'] ?? null)) {
                throw new InvalidPaymentAmountException('Enter a reconciliation note for excess funds or a payment on an unconfirmed reservation.');
            }
            $payment = $locked->payments()->create([
                'amount' => number_format($amount, 2, '.', ''), 'method' => $data['method'],
                'reference_number' => isset($data['reference_number']) ? strtoupper(trim($data['reference_number'])) : null,
                'payment_date' => \Carbon\Carbon::parse($data['collected_at'])->timezone(\App\Support\DepositDeadline::TIMEZONE)->toDateString(),
                'collected_at' => $data['collected_at'], 'source' => 'onsite', 'status' => PaymentStatus::Verified,
                'verified_by' => $actor->id, 'verified_at' => now(), 'reconciliation_note' => $data['reconciliation_note'] ?? null,
            ]);
            activity()->performedOn($payment)->causedBy($actor)->event('onsite_payment_recorded')->log('Payment received at the resort.');
            $locked->unsetRelation('payments');
            if ($locked->status === \App\Enums\ReservationStatus::Pending && $locked->deposit_required !== null
                && ! $locked->cancellation_requested_at && $locked->getVerifiedTotalPaid() >= $locked->requiredDepositAmount()) {
                app(ReservationService::class)->changeStatus($locked, \App\Enums\ReservationStatus::Approved, $actor);
            }
            DB::afterCommit(fn () => $locked->user?->notify(new \App\Notifications\Reservation\DepositUpdateNotification($locked->id, 'Staff recorded an on-site payment. Your payment history has been updated.')));
            return $payment;
        }, 5);
    }
}
