<?php

namespace App\Services;

use App\Enums\GuestGender;
use App\Enums\GuestIdType;
use App\Enums\ReservationGroupStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Exceptions\Reservation\ReservationConflictException;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Models\SwimmingSession;
use App\Models\User;
use App\Support\DepositDeadline;
use App\Support\GuestInformation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GroupedBookingService
{
    public function __construct(
        private readonly ReservationService $reservationService,
        private readonly GroupedAvailabilityService $availabilityService,
        private readonly CottageBookingService $cottageBookingService,
        private readonly AvailabilityService $singleAvailability,
        private readonly ReservationSchedule $schedule,
        private readonly PricingService $pricing,
    ) {}

    /**
     * Create an atomic grouped reservation containing multiple service lines.
     *
     * @param User $actor
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, mixed> $guestData
     * @param ReservationSource $source
     * @param array<string, mixed> $options
     * @return ReservationGroup
     *
     * @throws ValidationException
     * @throws ReservationConflictException
     */
    public function createGroup(
        User $actor,
        array $lines,
        array $guestData,
        ReservationSource $source,
        array $options = []
    ): ReservationGroup {
        if (empty($lines)) {
            throw ValidationException::withMessages([
                'lines' => ['At least one service must be selected to create a booking request.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $lines, $guestData, $source, $options) {
            // 1. Pre-validation and Recheck of every line inside the transaction
            $evaluated = $this->availabilityService->evaluateGroup($lines, customer: $source === ReservationSource::Online);

            if (isset($options['version_token']) && ! $this->availabilityService->matchesCurrentQuote($options['version_token'], $evaluated)) {
                throw ValidationException::withMessages(['lines' => ['The schedule or price changed. Review the current availability before submitting.']]);
            }

            if (! $evaluated['all_available']) {
                $failedLine = collect($evaluated['lines'])->firstWhere('is_available', false);
                $facilityName = $failedLine['facility_name'] ?? 'One of your selected services';
                $reason = $failedLine['reason'] ?? 'is no longer available.';

                throw new ReservationConflictException("{$facilityName} cannot be booked: {$reason}");
            }

            // 2. Instantiate Group Record
            $group = new ReservationGroup();
            $group->group_code = $group->generateGroupCode();
            $group->user_id = $source === ReservationSource::Online ? $actor->id : ($guestData['user_id'] ?? null);
            $group->source = $source;

            $action = $options['creation_action'] ?? 'approve';
            $isApprovedWalkIn = ($source === ReservationSource::WalkIn && $action === 'approve');
            $group->status = $isApprovedWalkIn ? ReservationGroupStatus::Approved : ReservationGroupStatus::Pending;

            $group->guest_name = $guestData['guest_name'] ?? $actor->name;
            $group->guest_email = $guestData['guest_email'] ?? $actor->email;
            $group->guest_phone = $guestData['guest_phone'] ?? $actor->phone ?? '';
            $group->guest_place_of_origin = $guestData['guest_place_of_origin'] ?? $actor->address ?? null;
            $group->guest_nationality = $guestData['guest_nationality'] ?? $actor->nationality ?? null;
            $group->guest_company = $guestData['guest_company'] ?? $actor->company ?? null;
            $group->guest_valid_id = $guestData['guest_valid_id'] ?? $actor->valid_id_type?->value ?? null;
            $group->guest_gender = $guestData['guest_gender'] ?? $actor->gender?->value ?? null;

            $group->special_requests = $options['special_requests'] ?? null;
            $group->admin_notes = $options['admin_notes'] ?? null;
            $group->policies_accepted_at = ! empty($options['policies_accepted']) ? now() : null;
            $group->policy_version = $options['policy_version'] ?? config('resort.policy.version', '2026-09-v2');

            $requiresQuote = ! empty($options['requires_quote']);
            $group->requires_quote = $requiresQuote;
            if ($requiresQuote) {
                $group->quote_requested_at = now();
            }

            if ($isApprovedWalkIn) {
                $group->approved_at = now();
                $group->approved_by = $actor->id;
            }

            $group->save();

            if (! empty($options['override_reason'])) {
                activity()
                    ->performedOn($group)
                    ->causedBy($actor)
                    ->event('administrative_override')
                    ->withProperties(['reason' => $options['override_reason']])
                    ->log("Administrative override applied on walk-in booking: {$options['override_reason']}");
            }

            $totalGroupAmount = 0.0;
            $this->reservationService->lockFacilityGraph(Facility::whereIn('id', collect($lines)->pluck('facility_id'))->get()->all());

            // 3. Create each individual Reservation row linked to the group
            foreach ($lines as $line) {
                $facility = Facility::findOrFail($line['facility_id']);
                $serviceRequest = $line['special_requests'] ?? ($source === ReservationSource::WalkIn ? $group->special_requests : null);

                if ($facility->isCottage()) {
                    $session = null;
                    if (! empty($line['swimming_session_id'])) {
                        $session = SwimmingSession::find($line['swimming_session_id']);
                    } elseif (! empty($line['session'])) {
                        $session = SwimmingSession::where('slug', $line['session'])->first();
                    }
                    if (! $session) {
                        $session = SwimmingSession::where('is_active', true)->orderBy('sort_order')->firstOrFail();
                    }

                    $visitDate = $line['date'] ?? $line['check_in'] ?? null;
                    $visitDate = $visitDate ? Carbon::parse($visitDate)->toDateString() : now()->addDay()->toDateString();
                    $checkIn = Carbon::parse($visitDate . ' ' . $session->starts_at);
                    $checkOut = Carbon::parse($visitDate . ' ' . $session->ends_at);
                    if ($checkOut->lte($checkIn)) {
                        $checkOut->addDay();
                    }
                    $guestCount = (int) ($line['guest_count'] ?? $line['guestCount'] ?? 1);

                    $reservation = $this->reservationService->createReservation(
                        $facility,
                        $actor,
                        $checkIn,
                        $checkOut,
                        $source,
                        $guestCount,
                        [
                            'swimming_session_id' => $session->id,
                            'infants_count' => (int) ($line['infants_count'] ?? 0),
                            'children_count' => (int) ($line['children_count'] ?? 0),
                            'special_requests' => $serviceRequest,
                            'admin_notes' => $group->admin_notes,
                            'policies_accepted_at' => $group->policies_accepted_at,
                            'policy_version' => $group->policy_version,
                            ...GuestInformation::fromValidated([
                                'guest_name' => $group->guest_name,
                                'guest_email' => $group->guest_email,
                                'guest_phone' => $group->guest_phone,
                                'guest_place_of_origin' => $group->guest_place_of_origin,
                                'guest_nationality' => $group->guest_nationality,
                                'guest_company' => $group->guest_company,
                                'guest_valid_id' => $group->guest_valid_id instanceof GuestIdType ? $group->guest_valid_id->value : $group->guest_valid_id,
                                'guest_gender' => $group->guest_gender instanceof GuestGender ? $group->guest_gender->value : $group->guest_gender,
                            ]),
                        ]
                    );

                    $reservation->reservation_group_id = $group->id;
                    $reservation->save();

                    $totalGroupAmount += (float) $reservation->total_price;
                } else {
                    $checkIn = Carbon::parse($line['check_in']);
                    $checkOut = Carbon::parse($line['check_out']);
                    $guestCount = (int) ($line['guest_count'] ?? $line['guestCount'] ?? 1);
                    $earlyHours = (int) ($line['early_check_in_hours'] ?? 0);
                    $lateHours = (int) ($line['late_check_out_hours'] ?? 0);
                    $paxTier = ! empty($line['pax_tier']) ? (int) $line['pax_tier'] : null;

                    $reservation = $this->reservationService->createReservation(
                        $facility,
                        $actor,
                        $checkIn,
                        $checkOut,
                        $source,
                        $guestCount,
                        [
                            'early_check_in_hours' => $earlyHours,
                            'late_check_out_hours' => $lateHours,
                            'pax_tier' => $paxTier,
                            'infants_count' => (int) ($line['infants_count'] ?? 0),
                            'children_count' => (int) ($line['children_count'] ?? 0),
                            'special_requests' => $serviceRequest,
                            'admin_notes' => $group->admin_notes,
                            'policies_accepted_at' => $group->policies_accepted_at,
                            'policy_version' => $group->policy_version,
                            ...GuestInformation::fromValidated([
                                'guest_name' => $group->guest_name,
                                'guest_email' => $group->guest_email,
                                'guest_phone' => $group->guest_phone,
                                'guest_place_of_origin' => $group->guest_place_of_origin,
                                'guest_nationality' => $group->guest_nationality,
                                'guest_company' => $group->guest_company,
                                'guest_valid_id' => $group->guest_valid_id instanceof GuestIdType ? $group->guest_valid_id->value : $group->guest_valid_id,
                                'guest_gender' => $group->guest_gender instanceof GuestGender ? $group->guest_gender->value : $group->guest_gender,
                            ]),
                        ]
                    );

                    $reservation->reservation_group_id = $group->id;
                    $reservation->save();

                    $totalGroupAmount += (float) $reservation->total_price;
                }
            }

            // 4. Update Group totals
            $group->total_amount = $totalGroupAmount;
            $group->deposit_required = ceil((int) round($totalGroupAmount * 100) / 2) / 100;
            if ($group->status === ReservationGroupStatus::Approved) {
                // Earliest check in across all lines
                $earliestCheckIn = $group->reservations()->min('check_in');
                if ($earliestCheckIn) {
                    $group->deposit_due_at = $group->reservations->map(fn ($line) => DepositDeadline::forReservation($line))->sort()->first();
                }
            }
            $group->booking_workflow = [
                'infants_count' => (int) max($options['infants_count'] ?? 0, collect($lines)->max('infants_count') ?? 0),
                'children_count' => (int) max($options['children_count'] ?? 0, collect($lines)->max('children_count') ?? 0),
                'pets_included' => (bool) ($options['pets_included'] ?? false),
            ];
            $group->save();
            if ($group->booking_workflow['pets_included']) {
                app(BookingPracticeService::class)->addAdjustment($group, $actor, 50000, 'Pets included; one fee for all selected services.', 'pet_fee');
                $group->refresh();
                $group->update(['deposit_required' => ceil((int) round($group->total_amount * 100) / 2) / 100]);
            }

            // 5. Attach group-level payment proof if provided
            if (! empty($options['payment'])) {
                $paymentData = $options['payment'];
                $payment = new \App\Models\Payment();
                $payment->reservation_group_id = $group->id;
                $percent = (int) ($options['payment_percent'] ?? 50);
                if (! in_array($percent, [50, 100], true)) {
                    throw ValidationException::withMessages(['payment_percent' => 'Choose 50% down payment or 100% online payment.']);
                }
                $payment->amount = $percent === 100 ? $group->total_amount : $group->deposit_required;
                $payment->method = $paymentData['method'] ?? \App\Enums\PaymentMethod::BankTransfer;
                $payment->reference_number = ! empty($paymentData['reference_number']) ? strtoupper(trim($paymentData['reference_number'])) : null;
                $payment->payment_date = $paymentData['payment_date'] ?? now()->toDateString();
                $payment->status = \App\Enums\PaymentStatus::Pending;
                $payment->source = 'customer_deposit';
                $payment->payment_destination_id = $paymentData['payment_destination_id'] ?? null;
                $payment->destination_snapshot = $paymentData['destination_snapshot'] ?? null;
                $payment->proof_path = $paymentData['proof_path'] ?? null;
                $payment->proof_hash = $paymentData['proof_hash'] ?? null;
                $payment->save();

                activity()
                    ->performedOn($payment)
                    ->causedBy($actor)
                    ->event('payment_submitted')
                    ->log("Group deposit payment submitted for group {$group->group_code}");
            }

            return $group->load(['reservations.facility.facilityType', 'reservations.swimmingSession']);
        });
    }

    /**
     * Staff approval of an entire grouped booking request.
     */
    public function approveGroup(ReservationGroup $group, User $staff): void
    {
        abort_unless($staff->hasRole(\App\Enums\Role::Administrator->value), 403);
        DB::transaction(function () use ($group, $staff) {
            $this->reservationService->lockFacilityGraph($group->reservations()->with('facility')->get()->map->facility->all());
            $group = ReservationGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();
            if (! $group->isPending()) {
                throw ValidationException::withMessages(['group' => ['Only a pending booking request can be approved.']]);
            }
            if ($group->source === ReservationSource::Online && ($group->requires_quote || $group->verifiedPaymentsTotal() < (float) $group->deposit_required)) {
                throw ValidationException::withMessages(['group' => ['Provide any required quote and verify the full 50% deposit before approving this online request.']]);
            }
            $group->load('reservations.facility.facilityType', 'reservations.swimmingSession');
            $this->reservationService->lockFacilityGraph($group->reservations->map->facility->all());
            if (($group->booking_workflow['child_charge']['status'] ?? null) === 'disputed' || isset($group->booking_workflow['refund'])) {
                throw ValidationException::withMessages(['group' => 'Resolve the recorded guest discussion before approving.']);
            }
            foreach ($group->reservations as $reservation) {
                $facility = $reservation->facility;
                $this->schedule->validate($facility, Carbon::parse($reservation->check_in), Carbon::parse($reservation->check_out), (int) $reservation->early_check_in_hours, (int) $reservation->late_check_out_hours, customer: $group->source === ReservationSource::Online);
                $result = $this->singleAvailability->isAvailable($facility, Carbon::parse($reservation->check_in), Carbon::parse($reservation->check_out), $reservation->id, (int) $reservation->guest_count, (int) $reservation->early_check_in_hours, (int) $reservation->late_check_out_hours, lockForUpdate: true, paxTier: $reservation->pax_tier);
                if (! $result->isAvailable) {
                    throw ValidationException::withMessages(['group' => [$facility->name.': '.($result->reason ?? 'This service is no longer available.')]]);
                }
                $currentCents = $facility->isCottage()
                    ? ($reservation->swimmingSession ? $this->cottageBookingService->price($facility, $reservation->swimmingSession->id, Carbon::parse($reservation->check_in), Carbon::parse($reservation->check_out))->totalAmount : null)
                    : $this->pricing->calculate($facility, Carbon::parse($reservation->check_in), Carbon::parse($reservation->check_out), (int) $reservation->early_check_in_hours, (int) $reservation->late_check_out_hours, max(0, (int) $reservation->guest_count - (int) ($reservation->booking_workflow['children_count'] ?? 0)), $reservation->pax_tier)->totalAmount;
                $savedAdjustments = array_sum(array_column($reservation->booking_workflow['adjustments'] ?? [], 'amount_cents'));
                if ($currentCents !== null) $currentCents += $savedAdjustments;
                if (! ($group->quoted_at && $facility->isFunctionHall())
                    && ($currentCents === null || $currentCents !== (int) round((float) $reservation->total_price * 100))) {
                    throw ValidationException::withMessages(['group' => [$facility->name.' has a changed rate. Review the submitted and current prices before making a decision.']]);
                }
            }
            $group->status = ReservationGroupStatus::Approved;
            $group->approved_at = now();
            $group->approved_by = $staff->id;
            
            $earliestCheckIn = $group->reservations()->min('check_in');
            if ($earliestCheckIn) {
                $group->deposit_due_at = $group->reservations->map(fn ($line) => DepositDeadline::forReservation($line))->sort()->first();
            }
            $group->save();

            foreach ($group->reservations as $reservation) {
                if ($reservation->status === ReservationStatus::Pending) {
                    $this->reservationService->changeStatus($reservation, ReservationStatus::Approved, $staff);
                }
            }
        });
    }

    /**
     * Provide staff quote for a function hall or custom request.
     */
    public function provideQuote(ReservationGroup $group, User $staff, float $quotedTotal, ?string $notes = null): void
    {
        abort_unless($staff->hasRole(\App\Enums\Role::Administrator->value), 403);
        DB::transaction(function () use ($group, $staff, $quotedTotal, $notes) {
            $group = ReservationGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();
            if (! $group->isPending() || ! $group->requires_quote || $group->quoted_at) {
                throw ValidationException::withMessages(['group' => ['Only a pending quotation request can be quoted.']]);
            }
            $group->load('reservations.facility.facilityType');
            $halls = $group->reservations->filter(fn (Reservation $reservation) => $reservation->facility->isFunctionHall())->values();
            $nonHallCents = (int) round($group->reservations->reject(fn (Reservation $reservation) => $reservation->facility->isFunctionHall())->sum('total_price') * 100);
            $hallCents = (int) round($quotedTotal * 100) - $nonHallCents;
            if ($halls->isEmpty() || $hallCents <= 0) {
                throw ValidationException::withMessages(['quoted_total' => ['The quote must cover all other services and a positive hall price.']]);
            }
            $previousHallCents = max(1, (int) round($halls->sum('total_price') * 100));
            $allocated = 0;
            foreach ($halls as $index => $hall) {
                $share = $index === $halls->count() - 1 ? $hallCents - $allocated
                    : (int) floor($hallCents * ((int) round((float) $hall->total_price * 100)) / $previousHallCents);
                if ($share <= 0) {
                    throw ValidationException::withMessages(['quoted_total' => ['Each function hall needs a positive quoted price.']]);
                }
                $hall->update(['total_price' => $share / 100]);
                $allocated += $share;
            }
            $group->total_amount = $quotedTotal;
            $group->deposit_required = ceil((int) round($quotedTotal * 100) / 2) / 100;
            $group->quoted_at = now();
            $group->quoted_by = $staff->id;
            $group->requires_quote = false;
            if ($notes) {
                $group->admin_notes = trim(($group->admin_notes ? $group->admin_notes . "\n" : '') . "Quotation Notes: " . $notes);
            }
            $group->save();

            activity()
                ->performedOn($group)
                ->causedBy($staff)
                ->event('quote_provided')
                ->withProperties(['quoted_total' => $quotedTotal, 'notes' => $notes])
                ->log("Quote of ₱" . number_format($quotedTotal, 2) . " provided by {$staff->name}");

            // Notify customer via email
            $recipient = $group->user;
            if ($recipient) {
                DB::afterCommit(fn () => $recipient->notify(new \App\Notifications\Reservation\GroupQuotationProvidedMail($group, $notes)));
            }
        });
    }

    /**
     * Staff rejection of an entire grouped booking request with full refund if deposit was paid.
     */
    public function rejectGroup(
        ReservationGroup $group,
        User $staff,
        string $reason,
        ?string $refundStatus = null,
        ?float $refundAmount = null,
        ?string $refundNotes = null
    ): void {
        abort_unless($staff->hasRole(\App\Enums\Role::Administrator->value), 403);
        DB::transaction(function () use ($group, $staff, $reason, $refundStatus, $refundAmount, $refundNotes) {
            $group = ReservationGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();
            if (! $group->isPending()) {
                throw ValidationException::withMessages(['group' => ['Only a pending booking request can be declined here.']]);
            }
            $group->status = ReservationGroupStatus::Rejected;
            $group->rejected_at = now();
            $group->rejection_reason = $reason;

            $verifiedTotal = $group->verifiedPaymentsTotal();
            $pendingTotal = $group->pendingPaymentsTotal();
            if ($verifiedTotal > 0 || $pendingTotal > 0) {
                if ($refundStatus === 'processed') {
                    throw ValidationException::withMessages(['refund_status' => 'Record a refund request, then owner approval, then returned funds separately.']);
                }
                $group->refund_status = 'requested';
                $flow = $group->booking_workflow ?? [];
                $flow['refund'] = ['status' => 'requested', 'reason' => $reason, 'requested_by' => $staff->id, 'requested_at' => now()->toIso8601String()];
                $group->booking_workflow = $flow;
                $group->refund_amount = $verifiedTotal;
                $group->refund_notes = $refundStatus === 'processed' ? $refundNotes : 'Review submitted payment proofs and refund any received funds in full.';
                $group->refunded_at = $refundStatus === 'processed' ? now() : null;
                $group->refunded_by = $refundStatus === 'processed' ? $staff->id : null;
            }

            $group->save();

            foreach ($group->reservations as $reservation) {
                if ($reservation->status === ReservationStatus::Pending) {
                    $this->reservationService->changeStatus($reservation, ReservationStatus::Rejected, $staff, $reason);
                }
            }

            activity()
                ->performedOn($group)
                ->causedBy($staff)
                ->event('group_rejected')
                ->withProperties([
                    'reason' => $reason,
                    'refund_status' => $group->refund_status,
                    'refund_amount' => $group->refund_amount,
                ])
                ->log("Group reservation {$group->group_code} rejected by {$staff->name}");
            if (isset($group->booking_workflow['refund'])) {
                DB::afterCommit(function () use ($group) {
                    $line = $group->reservations()->firstOrFail();
                    foreach (User::role([\App\Enums\Role::Administrator->value, \App\Enums\Role::Owner->value])->get() as $recipient) {
                        $recipient->notify(new \App\Notifications\Reservation\BookingDiscussionUpdate($line->id, 'refund_requested'));
                    }
                });
            }
        });
    }

    public function recordFullRefund(ReservationGroup $group, User $staff, float $amount, string $reference): void
    {
        abort_unless($staff->hasRole(\App\Enums\Role::Administrator->value), 403);
        $line = $group->reservations()->firstOrFail();
        app(BookingPracticeService::class)->recordReturnedFunds($line, $staff, (int) round($amount * 100), $reference);

    }
}
