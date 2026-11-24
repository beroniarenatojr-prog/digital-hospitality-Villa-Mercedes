<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Models\User;
use App\Notifications\Reservation\BookingDiscussionUpdate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingPracticeService
{
    public function target(Reservation $line): Reservation|ReservationGroup
    {
        return $line->reservationGroup ?? $line;
    }

    private function assertConfirmedPolicy(Reservation|ReservationGroup $booking): void
    {
        if ($booking->policy_version !== config('resort.policy.confirmed_version')) {
            throw ValidationException::withMessages(['booking' => 'This historical booking retains its accepted terms. Staff must review its saved policy.']);
        }
    }

    private function assertNotArrived(Reservation|ReservationGroup $booking): void
    {
        $arrived = $booking instanceof ReservationGroup
            ? $booking->reservations()->whereNotNull('checked_in_at')->exists()
            : $booking->checked_in_at !== null;
        if ($arrived) {
            throw ValidationException::withMessages(['booking' => 'Physical check-in has begun; cancellation refunds and rescheduling are no longer eligible.']);
        }
    }

    public function requestCancellation(Reservation $line, User $actor, string $reason): void
    {
        $this->mutate($line, $actor, function ($booking) use ($actor, $reason) {
            abort_unless($booking->user_id === $actor->id || $actor->hasRole(Role::Administrator->value), 403);
            $this->assertNotArrived($booking);
            $flow = $booking->booking_workflow ?? [];
            if ($booking->policy_version !== config('resort.policy.confirmed_version')) {
                throw ValidationException::withMessages(['booking' => 'This historical booking retains its accepted terms. Staff must review its saved policy.']);
            }
            if ($booking instanceof Reservation && ! in_array($booking->status, [ReservationStatus::Pending, ReservationStatus::Approved, ReservationStatus::Completed, ReservationStatus::Expired, ReservationStatus::Rejected], true)) {
                throw ValidationException::withMessages(['booking' => 'Contact staff about this closed booking and its recorded refund decision.']);
            }
            if ($booking instanceof ReservationGroup && ! $booking->isPending() && ! $booking->isApproved() && ! $booking->isCompleted() && ! $booking->isRejected()) {
                throw ValidationException::withMessages(['booking' => 'Contact staff about this closed booking and its recorded refund decision.']);
            }
            if (isset($flow['refund'])) {
                throw ValidationException::withMessages(['booking' => 'A cancellation/refund discussion already exists.']);
            }
            $flow['refund'] = ['status' => 'requested', 'reason' => $reason, 'requested_by' => $actor->id, 'requested_at' => now()->toIso8601String()];
            $booking->update(['booking_workflow' => $flow]);
            if ($booking instanceof Reservation) {
                $booking->update(['cancellation_requested_at' => now(), 'cancellation_request_reason' => $reason]);
            }
            $this->audit($booking, $actor, 'refund_requested', $flow['refund']);
        });
    }

    public function approveRefund(Reservation $line, User $owner, string $approvalNote = ''): void
    {
        $this->assertAdministrator($owner);
        if (blank($approvalNote)) {
            throw ValidationException::withMessages(['reason' => 'Record the owner approval or instruction before approving the refund.']);
        }
        $this->mutate($line, $owner, function ($booking) use ($owner, $approvalNote) {
            $this->assertNotArrived($booking);
            $flow = $booking->booking_workflow ?? [];
            if (($flow['refund']['status'] ?? null) !== 'requested') {
                throw ValidationException::withMessages(['booking' => 'A refund request must be recorded before owner approval.']);
            }
            if ($this->pending($booking) > 0) {
                throw ValidationException::withMessages(['booking' => 'Verify or reject every pending proof before owner approval.']);
            }
            $flow['refund'] = [...$flow['refund'], 'status' => 'approved', 'amount_cents' => $this->verifiedCents($booking),
                'approved_by' => $owner->id, 'owner_approval_note' => trim($approvalNote), 'approved_at' => now()->toIso8601String()];
            $booking->update(['booking_workflow' => $flow, 'status' => 'cancelled', 'cancelled_at' => now(),
                'cancellation_reason' => $flow['refund']['reason']]);
            if ($booking instanceof ReservationGroup) {
                $booking->reservations()->whereNull('checked_in_at')->update(['status' => ReservationStatus::Cancelled->value, 'cancelled_at' => now()]);
                $booking->update(['refund_status' => 'approved', 'refund_amount' => $flow['refund']['amount_cents'] / 100]);
            }
            $this->audit($booking, $owner, 'refund_approved', $flow['refund']);
        });
    }

    public function recordReturnedFunds(Reservation $line, User $owner, int $cents, string $reference): void
    {
        $this->assertAdministrator($owner);
        $this->mutate($line, $owner, function ($booking) use ($owner, $cents, $reference) {
            $this->assertNotArrived($booking);
            $flow = $booking->booking_workflow ?? [];
            if (($flow['refund']['status'] ?? null) !== 'approved' || $this->pending($booking) > 0
                || $cents !== $this->verifiedCents($booking) || $cents !== $flow['refund']['amount_cents'] || blank($reference)) {
                throw ValidationException::withMessages(['booking' => 'Record the full owner-approved verified amount and return reference; reconcile changed payments first.']);
            }
            $flow['refund'] = [...$flow['refund'], 'status' => 'returned', 'returned_at' => now()->toIso8601String(),
                'returned_by' => $owner->id, 'reference' => trim($reference)];
            $booking->update(['booking_workflow' => $flow]);
            if ($booking instanceof ReservationGroup) {
                $booking->update(['refund_status' => 'processed', 'refund_amount' => $cents / 100, 'refund_notes' => trim($reference), 'refunded_at' => now(), 'refunded_by' => $owner->id]);
            }
            $this->audit($booking, $owner, 'funds_returned', $flow['refund']);
        });
    }

    public function proposeChange(Reservation $line, User $actor, array $selection): void
    {
        abort_unless($line->user_id === $actor->id || $actor->hasRole(Role::Administrator->value), 403);
        DB::transaction(function () use ($line, $actor, $selection) {
            if ($line->reservation_group_id) {
                ReservationGroup::whereKey($line->reservation_group_id)->lockForUpdate()->firstOrFail();
            }
            $locked = Reservation::whereKey($line->id)->lockForUpdate()->firstOrFail();
            $this->assertConfirmedPolicy($this->target($locked));
            $this->assertNotArrived($this->target($locked));
            if (isset($this->target($locked)->booking_workflow['refund'])) {
                throw ValidationException::withMessages(['booking' => 'Resolve the cancellation/refund discussion before proposing a change.']);
            }
            if (! in_array($locked->status, [ReservationStatus::Pending, ReservationStatus::Approved], true)) {
                throw ValidationException::withMessages(['booking' => 'Only an active booking can be changed.']);
            }
            $facility = Facility::findOrFail($selection['facility_id']);
            $start = Carbon::parse($selection['check_in']);
            $end = Carbon::parse($selection['check_out']);
            $early = (int) ($selection['early_check_in_hours'] ?? 0);
            app(ReservationSchedule::class)->validate($facility, $start, $end, $early);
            $result = app(AvailabilityService::class)->isAvailable($facility, $start, $end, $locked->id, (int) $selection['guest_count'], $early);
            if (! $result->isAvailable) {
                throw ValidationException::withMessages(['booking' => $result->reason.' Your current stay is still booked.']);
            }
            $children = (int) ($locked->booking_workflow['children_count'] ?? 0);
            if ((int) $selection['guest_count'] < $children) {
                throw ValidationException::withMessages(['booking' => 'Guest count must include the declared older children.']);
            }
            $quote = $facility->isCottage()
                ? app(CottageBookingService::class)->price($facility, $selection['swimming_session_id'] ?? null, $start, $end)
                : app(PricingService::class)->calculate($facility, $start, $end, $early, 0,
                    max(0, (int) $selection['guest_count'] - (($facility->isRoom() || $facility->isPackage()) ? $children : 0)), $selection['pax_tier'] ?? null);
            $flow = $locked->booking_workflow ?? [];
            $flow['change'] = ['status' => 'proposed',
                'selection' => $selection, 'quote_cents' => $quote->totalAmount + array_sum(array_column($locked->booking_workflow['adjustments'] ?? [], 'amount_cents')), 'old_total_cents' => (int) round($locked->total_price * 100),
                'proposed_by' => $actor->id, 'proposed_at' => now()->toIso8601String(),
                'accepted_by' => null];
            $locked->update(['booking_workflow' => $flow]);
            $this->audit($locked, $actor, 'change_proposed', $flow['change']);
        }, 5);
    }

    public function acceptChange(Reservation $line, User $guest, int $quoteCents): void
    {
        DB::transaction(function () use ($line, $guest, $quoteCents) {
            if ($line->reservation_group_id) {
                ReservationGroup::whereKey($line->reservation_group_id)->lockForUpdate()->firstOrFail();
            }
            $locked = Reservation::whereKey($line->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->user_id === $guest->id, 403);
            $this->assertConfirmedPolicy($this->target($locked));
            $this->assertNotArrived($this->target($locked));
            if (isset($this->target($locked)->booking_workflow['refund'])) {
                throw ValidationException::withMessages(['booking' => 'Cancellation/refund discussion blocks accepting a change.']);
            }
            $flow = $locked->booking_workflow ?? [];
            if (($flow['change']['status'] ?? null) !== 'proposed' || $quoteCents !== $flow['change']['quote_cents']) {
                throw ValidationException::withMessages(['booking' => 'Review and accept the current revised quote.']);
            }
            $flow['change']['status'] = 'accepted';
            $flow['change']['accepted_by'] = $guest->id;
            $flow['change']['accepted_at'] = now()->toIso8601String();
            $locked->update(['booking_workflow' => $flow]);
            $this->audit($locked, $guest, 'change_accepted', $flow['change']);
        }, 5);
    }

    public function confirmChange(Reservation $line, User $staff): void
    {
        abort_unless($staff->hasRole(Role::Administrator->value), 403);
        DB::transaction(function () use ($line, $staff) {
            $proposal = $line->fresh()->booking_workflow['change'] ?? [];
            $facility = Facility::findOrFail($proposal['selection']['facility_id'] ?? 0);
            app(ReservationService::class)->lockFacilityGraph($facility);
            $group = $line->reservation_group_id ? ReservationGroup::whereKey($line->reservation_group_id)->lockForUpdate()->firstOrFail() : null;
            $locked = Reservation::whereKey($line->id)->lockForUpdate()->firstOrFail();
            $flow = $locked->booking_workflow ?? [];
            $change = $flow['change'] ?? [];
            if ($change !== $proposal || ($change['status'] ?? null) !== 'accepted') {
                throw ValidationException::withMessages(['booking' => 'Guest acceptance of the revised quote is required.']);
            }
            $this->assertConfirmedPolicy($group ?? $locked);
            $this->assertNotArrived($group ?? $locked);
            if (isset(($group?->booking_workflow ?? $locked->booking_workflow ?? [])['refund'])) {
                throw ValidationException::withMessages(['booking' => 'Cancellation/refund discussion blocks confirming a change.']);
            }
            $s = $change['selection'];
            $before = (int) round($locked->total_price * 100);
            $updated = app(ReservationService::class)->reschedule($locked, $facility, Carbon::parse($s['check_in']), Carbon::parse($s['check_out']),
                $staff, (int) $s['guest_count'], [...$s, 'guest_accepted_change' => true]);
            // Pricing is refreshed by reschedule under resource locks. Never apply a changed quote without renewed acceptance.
            if ((int) round($updated->total_price * 100) !== $change['quote_cents']) {
                throw ValidationException::withMessages(['booking' => 'The quote changed. Propose a new quote for guest acceptance.']);
            }
            $flow['change']['status'] = 'confirmed';
            $flow['change']['confirmed_by'] = $staff->id;
            $flow['change']['confirmed_at'] = now()->toIso8601String();
            $updated->update(['booking_workflow' => $flow]);
            if ($group) {
                $group->update(['total_amount' => ((int) round($group->total_amount * 100) + $change['quote_cents'] - $before) / 100]);
            }
            $this->audit($updated, $staff, 'change_confirmed', $flow['change']);
        }, 5);
    }

    public function childCharge(Reservation $line, User $owner, int $cents, string $reason): void
    {
        $this->assertAdministrator($owner);
        $this->mutate($line, $owner, function ($booking) use ($owner, $cents, $reason) {
            $flow = $booking->booking_workflow ?? [];
            if (($flow['children_count'] ?? 0) < 1 || isset($flow['child_charge']) || $cents < 0 || blank($reason)) {
                throw ValidationException::withMessages(['booking' => 'Record one explained owner decision for the declared older children.']);
            }
            $this->assertNotArrived($booking);
            if (isset($flow['refund'])) {
                throw ValidationException::withMessages(['booking' => 'Do not add child charges while cancellation/refund is under discussion or closed.']);
            }
            $this->addAdjustment($booking, $owner, $cents, $reason, 'child_charge');
            $flow = $booking->fresh()->booking_workflow ?? [];
            $flow['child_charge'] = ['status' => 'owner_approved', 'amount_cents' => $cents, 'reason' => $reason, 'approved_by' => $owner->id];
            $booking->update(['booking_workflow' => $flow]);
            $this->audit($booking, $owner, 'child_charge_approved', $flow['child_charge']);
        });
    }

    public function discussChildCharge(Reservation $line, User $actor, string $decision, string $reason): void
    {
        $this->mutate($line, $actor, function ($booking) use ($actor, $decision, $reason) {
            $flow = $booking->booking_workflow ?? [];
            if ($decision === 'dispute') {
                abort_unless($booking->user_id === $actor->id, 403);
                if (($flow['child_charge']['status'] ?? null) !== 'owner_approved') {
                    throw ValidationException::withMessages(['booking' => 'There is no new child charge to dispute.']);
                }
                $flow['child_charge']['status'] = 'disputed';
            } else {
                abort_unless($actor->hasRole(Role::Administrator->value), 403);
                if (($flow['child_charge']['status'] ?? null) !== 'disputed' || blank($reason)) {
                    throw ValidationException::withMessages(['booking' => 'Record the guest-agreed outcome of the child-fee discussion.']);
                }
                $flow['child_charge']['status'] = 'discussion_resolved';
            }
            $flow['child_charge']['discussion'] = $reason;
            $booking->update(['booking_workflow' => $flow]);
            $this->audit($booking, $actor, 'child_charge_'.$decision, $flow['child_charge']);
        });
    }

    public function arrivalDiscount(Reservation $line, User $staff, int $cents, string $reason): void
    {
        abort_unless($staff->hasRole(Role::Administrator->value), 403);
        $this->mutate($line, $staff, function ($booking) use ($staff, $cents, $reason) {
            if ($cents <= 0 || blank($reason)) {
                throw ValidationException::withMessages(['booking' => 'Enter a positive check in discount and reason.']);
            }
            if (isset($booking->booking_workflow['refund'])) {
                throw ValidationException::withMessages(['booking' => 'Do not discount a booking under cancellation/refund discussion.']);
            }
            $this->addAdjustment($booking, $staff, -$cents, $reason, 'arrival_discount');
        });
    }

    public function addAdjustment(Reservation|ReservationGroup $booking, User $actor, int $cents, string $reason, string $kind): void
    {
        if ($booking instanceof ReservationGroup && $cents < 0) {
            $lines = $booking->reservations()->orderBy('id')->lockForUpdate()->get();
            $discount = -$cents;
            if ($discount > (int) round($booking->total_amount * 100)
                || $discount > $lines->sum(fn ($line) => max(0, (int) round($line->total_price * 100)))) {
                throw ValidationException::withMessages(['booking' => 'Discount cannot exceed the booking total.']);
            }
            $entry = ['kind' => $kind, 'amount_cents' => $cents, 'reason' => $reason, 'actor_id' => $actor->id, 'at' => now()->toIso8601String()];
            $remaining = $discount;
            foreach ($lines as $line) {
                $portion = min($remaining, max(0, (int) round($line->total_price * 100)));
                if ($portion === 0) {
                    continue;
                }
                $lineFlow = $line->booking_workflow ?? [];
                $lineFlow['adjustments'][] = [...$entry, 'amount_cents' => -$portion];
                $line->update([
                    'total_price' => ((int) round($line->total_price * 100) - $portion) / 100,
                    'additional_charges' => ((int) round($line->additional_charges * 100) - $portion) / 100,
                    'booking_workflow' => $lineFlow,
                ]);
                $remaining -= $portion;
                if ($remaining === 0) {
                    break;
                }
            }
            $flow = $booking->booking_workflow ?? [];
            $flow['adjustments'][] = $entry;
            $booking->update(['total_amount' => ((int) round($booking->total_amount * 100) + $cents) / 100, 'booking_workflow' => $flow]);
            $this->audit($booking, $actor, $kind.'_recorded', $entry);

            return;
        }
        // Caller owns the ledger lock. Booking-level fees are assigned once, to one line, so every balance reconciles.
        $line = $booking instanceof ReservationGroup ? $booking->reservations()->orderBy('id')->lockForUpdate()->firstOrFail() : $booking;
        $total = (int) round($line->total_price * 100) + $cents;
        if ($total < 0) {
            throw ValidationException::withMessages(['booking' => 'Discount cannot exceed the line total.']);
        }
        $flow = $booking->booking_workflow ?? [];
        $entry = ['kind' => $kind, 'amount_cents' => $cents, 'reason' => $reason, 'actor_id' => $actor->id, 'at' => now()->toIso8601String()];
        $flow['adjustments'][] = $entry;
        $lineFlow = $line->booking_workflow ?? [];
        $lineFlow['adjustments'][] = $entry;
        $line->update(['total_price' => $total / 100, 'additional_charges' => ((int) round($line->additional_charges * 100) + $cents) / 100,
            'booking_workflow' => $booking instanceof ReservationGroup ? $lineFlow : $flow]);
        if ($booking instanceof ReservationGroup) {
            $booking->update(['total_amount' => ((int) round($booking->total_amount * 100) + $cents) / 100, 'booking_workflow' => $flow]);
        }
        if ($booking instanceof Reservation && $booking->reservation_group_id) {
            $group = ReservationGroup::whereKey($booking->reservation_group_id)->lockForUpdate()->firstOrFail();
            $group->update(['total_amount' => ((int) round($group->total_amount * 100) + $cents) / 100]);
        }
        $this->audit($booking, $actor, $kind.'_recorded', $entry);
    }

    private function mutate(Reservation $line, User $actor, callable $callback): void
    {
        DB::transaction(function () use ($line, $callback) {
            $target = $this->target($line);
            $locked = $target->newQuery()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ($locked instanceof ReservationGroup) {
                $locked->setRelation('reservations', $locked->reservations()->lockForUpdate()->get());
                Payment::where(function ($query) use ($locked) {
                    $query->where('reservation_group_id', $locked->id)->orWhereIn('reservation_id', $locked->reservations->pluck('id'));
                })->orderBy('id')->lockForUpdate()->get();
            } else {
                $locked->payments()->lockForUpdate()->get();
            }
            $this->assertConfirmedPolicy($locked);
            $callback($locked);
        }, 5);
    }

    private function assertAdministrator(User $user): void
    {
        abort_unless($user->hasRole(Role::Administrator->value), 403, 'Only administrators may record owner decisions and returned funds.');
    }

    private function verifiedCents(Reservation|ReservationGroup $booking): int
    {
        return (int) round(($booking instanceof ReservationGroup ? $booking->verifiedPaymentsTotal() : $booking->getVerifiedTotalPaid()) * 100);
    }

    private function pending(Reservation|ReservationGroup $booking): float
    {
        return $booking instanceof ReservationGroup ? $booking->pendingPaymentsTotal() : $booking->getPendingVerificationTotal();
    }

    private function audit(Reservation|ReservationGroup $booking, User $actor, string $event, array $details): void
    {
        activity()->performedOn($booking)->causedBy($actor)->event($event)->withProperties($details)->log(str_replace('_', ' ', $event));
        DB::afterCommit(function () use ($booking, $event) {
            $recipients = User::role([Role::Administrator->value, Role::Owner->value])->get();
            if ($booking->user) {
                $recipients->push($booking->user);
            }
            foreach ($recipients->unique('id') as $recipient) {
                $recipient->notify(new BookingDiscussionUpdate($booking instanceof ReservationGroup ? $booking->reservations()->firstOrFail()->id : $booking->id, $event));
            }
        });
    }
}
