<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Models\User;
use App\Notifications\Reservation\LateCheckoutUpdate;
use App\Support\ResortTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LateCheckoutService
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly AvailabilityService $availability,
    ) {}

    public function quote(Reservation $reservation, int $additionalHours): array
    {
        $this->assertEligible($reservation, $additionalHours);
        $rates = $reservation->facility->rates()->where('is_active', true)
            ->where('type', \App\Enums\RateType::AdditionalCharge->value)
            ->where('name', PricingService::LATE_CHECK_OUT_RATE_NAME)->get();
        if ($rates->count() !== 1 || (int) round((float) $rates->first()->amount * 100) !== 30000 || $rates->first()->unit !== 'per hour') {
            throw ValidationException::withMessages(['hours' => 'Staff must reconcile the room late check-out rate to ₱300 per room hour before requests can be priced.']);
        }

        $totalHours = (int) $reservation->late_check_out_hours + $additionalHours;
        $result = $this->availability->isAvailable(
            $reservation->facility, $reservation->check_in, $reservation->check_out,
            $reservation->id, (int) $reservation->guest_count,
            (int) $reservation->early_check_in_hours, $totalHours,
        );

        return [
            'hours' => $additionalHours,
            'total_late_hours' => $totalHours,
            'fee_cents' => $additionalHours * 30000,
            'departure' => $reservation->check_out->copy()->addHours($totalHours)->toDateTimeString(),
            'availability' => $result->toArray(),
        ];
    }

    public function request(Reservation $reservation, User $guest, int $hours, int $acceptedFeeCents): void
    {
        abort_unless($reservation->user_id === $guest->id && ! $guest->hasRole(Role::Administrator->value), 403);
        DB::transaction(function () use ($reservation, $guest, $hours, $acceptedFeeCents) {
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->user_id === $guest->id, 403);
            if (($locked->late_checkout_request['status'] ?? null) === 'pending') {
                throw ValidationException::withMessages(['hours' => 'A late check-out request is already under review.']);
            }
            $quote = $this->quote($locked, $hours);
            if ($acceptedFeeCents !== $quote['fee_cents']) {
                throw ValidationException::withMessages(['hours' => 'Review the exact late check-out fee before submitting.']);
            }
            $locked->update(['late_checkout_request' => [
                'status' => 'pending',
                'hours' => $hours,
                'fee_cents' => $quote['fee_cents'],
                'departure' => $quote['departure'],
                'facility_id' => $locked->facility_id,
                'check_in' => $locked->check_in->toDateTimeString(),
                'check_out' => $locked->check_out->toDateTimeString(),
                'previous_late_hours' => (int) $locked->late_check_out_hours,
                'applied_fee_cents' => (int) ($locked->late_checkout_request['applied_fee_cents'] ?? 0),
                'requested_at' => now()->toIso8601String(),
            ]]);
            activity()->performedOn($locked)->causedBy($guest)->event('late_checkout_requested')
                ->withProperties($locked->late_checkout_request)->log('Room late check-out requested; no charge applied.');
            DB::afterCommit(function () use ($locked) {
                foreach (User::role(Role::Administrator->value)->get() as $staff) {
                    $staff->notify(new LateCheckoutUpdate($locked->id, $locked->late_checkout_request));
                }
            });
        }, 5);
        $reservation->refresh();
    }

    public function decide(Reservation $reservation, User $staff, bool $approve, int $reviewedFeeCents, ?string $reason = null): void
    {
        abort_unless($staff->hasRole(Role::Administrator->value), 403);
        DB::transaction(function () use ($reservation, $staff, $approve, $reviewedFeeCents, $reason) {
            // Match booking creation's deterministic resource locks and current overlap read.
            $facilities = $this->reservations->lockFacilityGraph($reservation->facility);
            $group = $reservation->reservation_group_id
                ? ReservationGroup::whereKey($reservation->reservation_group_id)->lockForUpdate()->firstOrFail() : null;
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $request = $locked->late_checkout_request;
            if (($request['status'] ?? null) !== 'pending') {
                throw ValidationException::withMessages(['late_checkout' => 'This request has already been decided or no longer exists.']);
            }
            $oldTotal = (int) round((float) $locked->total_price * 100);
            if ($approve) {
                if ($locked->facility_id !== $reservation->facility_id || $locked->facility_id !== $request['facility_id']
                    || $locked->check_in->toDateTimeString() !== $request['check_in']
                    || $locked->check_out->toDateTimeString() !== $request['check_out']
                    || (int) $locked->late_check_out_hours !== $request['previous_late_hours']) {
                    throw ValidationException::withMessages(['late_checkout' => 'The booking changed after this request. Decline it and ask the guest for a new request.']);
                }
                $locked->setRelation('facility', $facilities->firstWhere('id', $locked->facility_id));
                $quote = $this->quote($locked, $request['hours']);
                if ($reviewedFeeCents !== $request['fee_cents'] || $quote['fee_cents'] !== $request['fee_cents']) {
                    throw ValidationException::withMessages(['late_checkout' => 'Review the exact request fee before approval.']);
                }
                $result = $this->availability->isAvailable(
                    $locked->facility, $locked->check_in, $locked->check_out, $locked->id,
                    (int) $locked->guest_count, (int) $locked->early_check_in_hours,
                    $quote['total_late_hours'], lockForUpdate: true,
                );
                if (! $result->isAvailable) {
                    throw ValidationException::withMessages(['late_checkout' => 'Late check-out cannot be approved: '.$result->status()->label().'. '.$result->reason]);
                }
                $fee = $request['fee_cents'];
                $request['applied_fee_cents'] = ($request['applied_fee_cents'] ?? 0) + $fee;
                $breakdown = $locked->pricing_breakdown;
                if ($breakdown !== null) {
                    $breakdown['late_check_out_charge'] = ($breakdown['late_check_out_charge'] ?? 0) + $fee;
                    $breakdown['additional_charges'] = (int) round((float) $locked->additional_charges * 100) + $fee;
                    $breakdown['total_amount'] = $oldTotal + $fee;
                }
                $locked->fill([
                    'late_check_out_hours' => $quote['total_late_hours'],
                    'additional_charges' => number_format(((int) round((float) $locked->additional_charges * 100) + $fee) / 100, 2, '.', ''),
                    'total_price' => number_format(($oldTotal + $fee) / 100, 2, '.', ''),
                    'pricing_breakdown' => $breakdown,
                ]);
                // Additional departure fees are a balance charge, not a new deposit policy.
                if ($group) {
                    $group->update(['total_amount' => number_format(((int) round((float) $group->total_amount * 100) + $fee) / 100, 2, '.', '')]);
                }
            } elseif (blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'Explain why the request was declined.']);
            }
            $request['status'] = $approve ? 'approved' : 'declined';
            $request['decided_by'] = $staff->id;
            $request['decided_at'] = now()->toIso8601String();
            $request['reason'] = $approve ? null : trim($reason);
            $locked->late_checkout_request = $request;
            $locked->save();
            activity()->performedOn($locked)->causedBy($staff)->event('late_checkout_'.$request['status'])
                ->withProperties([...$request, 'old_total_cents' => $oldTotal, 'new_total_cents' => (int) round((float) $locked->total_price * 100)])
                ->log($approve ? 'Room late check-out approved and fee added to the balance.' : 'Room late check-out declined; totals unchanged.');
            DB::afterCommit(fn () => $locked->user?->notify(new LateCheckoutUpdate($locked->id, $request)));
        }, 5);
        $reservation->refresh();
    }

    private function assertEligible(Reservation $reservation, int $hours): void
    {
        $workflow = $reservation->reservationGroup?->booking_workflow ?? $reservation->booking_workflow ?? [];
        if (isset($workflow['refund']) || ($workflow['child_charge']['status'] ?? null) === 'disputed') {
            throw ValidationException::withMessages(['hours' => 'Resolve the recorded booking discussion before requesting or approving late checkout.']);
        }
        if (! $reservation->facility?->isRoom() || $reservation->status !== ReservationStatus::Approved
            || $reservation->checked_out_at || $reservation->cancellation_requested_at
            || $reservation->occupancyEnd()->lte(ResortTime::databaseNow())
            || $hours < 1 || $hours + (int) $reservation->late_check_out_hours > (int) config('resort.stay.max_additional_hours', 12)) {
            throw ValidationException::withMessages(['hours' => 'Late check-out requires an active approved room booking and whole hours within the existing additional-hours limit.']);
        }
    }
}
