<?php

namespace App\Services\Admin;

use App\DTOs\DecisionImpact;
use App\DTOs\OccupancyClaim;
use App\Enums\ReservationStatus;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\TrainingBatch;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns the reservation and training data into decisions an owner can act on.
 *
 * Everything here is rule-based and traceable: each peso figure comes from a
 * configured rate and each statement names the claim it came from. That is
 * deliberate. An owner who cannot see why a number appeared will not trust it.
 */
class DecisionSupportService
{
    public function __construct(
        private readonly PricingService $pricingService
    ) {}

    /**
     * One calendar for the whole property: every claim on every bookable
     * resource in the window, positioned for display.
     *
     * @return list<array{facility: Facility, claims: list<array{claim: OccupancyClaim, offset: float, width: float}>}>
     */
    public function timeline(Carbon $from, Carbon $to, ?string $facilityTypeSlug = null): array
    {
        $facilities = Facility::with('facilityType')
            ->where('is_active', true)
            ->when($facilityTypeSlug, fn ($q) => $q->whereHas('facilityType', fn ($t) => $t->where('slug', $facilityTypeSlug)))
            ->orderBy('facility_type_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $claims = $this->claimsInWindow($from, $to, $facilities->pluck('id')->all());
        $totalMinutes = max(1, $from->diffInMinutes($to));

        $rows = [];

        foreach ($facilities as $facility) {
            $facilityClaims = $claims
                ->where('facilityId', $facility->id)
                ->sortBy(fn (OccupancyClaim $claim) => $claim->from->timestamp)
                ->map(function (OccupancyClaim $claim) use ($from, $to, $totalMinutes) {
                    $start = $claim->from->greaterThan($from) ? $claim->from : $from;
                    $end = $claim->to->lessThan($to) ? $claim->to : $to;

                    return [
                        'claim' => $claim,
                        'offset' => round(($from->diffInMinutes($start) / $totalMinutes) * 100, 3),
                        'width' => max(0.6, round(($start->diffInMinutes($end) / $totalMinutes) * 100, 3)),
                    ];
                })
                ->values()
                ->all();

            $rows[] = [
                'facility' => $facility,
                'claims' => $facilityClaims,
            ];
        }

        return $rows;
    }

    /**
     * Every claim overlapping the window, from all claim types.
     *
     * @param list<int>|null $facilityIds
     * @return Collection<int, OccupancyClaim>
     */
    public function claimsInWindow(Carbon $from, Carbon $to, ?array $facilityIds = null): Collection
    {
        return $this->reservationClaims($from, $to, $facilityIds)
            ->merge($this->trainingClaims($from, $to, $facilityIds))
            ->values();
    }

    /**
     * Pending requests with their impact, newest first, for the approval queue.
     *
     * @return list<array{reservation: Reservation, impact: DecisionImpact}>
     */
    public function pendingQueue(int $limit = 10): array
    {
        return Reservation::with(['user', 'facility.facilityType', 'payments'])
            ->where('status', ReservationStatus::Pending->value)
            ->orderBy('check_in')
            ->limit($limit)
            ->get()
            ->map(fn (Reservation $reservation) => [
                'reservation' => $reservation,
                'impact' => $this->analyze($reservation),
            ])
            ->all();
    }

    /**
     * What accepting (or continuing to hold) this reservation does to the property.
     */
    public function analyze(Reservation $reservation): DecisionImpact
    {
        $reservation->loadMissing('facility.facilityType');
        $facility = $reservation->facility;

        $windowStart = $reservation->occupancyStart();
        $windowEnd = $reservation->occupancyEnd();

        $explanations = [];
        $frozenResources = [];
        $opportunityCost = 0.0;

        $explanations[] = sprintf(
            'Holds %s from %s to %s (%s).',
            $facility->name,
            $windowStart->format('M d, g:i A'),
            $windowEnd->format('M d, g:i A'),
            $this->humanDuration($windowStart, $windowEnd)
        );

        if ($reservation->early_check_in_hours || $reservation->late_check_out_hours) {
            $explanations[] = sprintf(
                'Occupancy runs %s beyond the booked period (%dh early check-in, %dh late check-out), so the space cannot be turned over inside that time.',
                $this->humanDuration(
                    $reservation->check_out,
                    $reservation->check_out->copy()->addHours((int) $reservation->early_check_in_hours + (int) $reservation->late_check_out_hours)
                ),
                (int) $reservation->early_check_in_hours,
                (int) $reservation->late_check_out_hours
            );
        }

        foreach ($facility->contendingFacilities() as $contender) {
            $listValue = $this->listValue($contender, $windowStart, $windowEnd);
            $opportunityCost += $listValue;

            $reason = $facility->isPackage()
                ? sprintf('Consumed as a component of %s', $facility->name)
                : sprintf('Cannot be sold because %s is part of it', $facility->name);

            $frozenResources[] = [
                'name' => $contender->name,
                'reason' => $reason,
                'list_value' => $listValue,
            ];
        }

        if ($frozenResources === []) {
            $explanations[] = 'No other bookable resource is linked to this facility, so nothing else is taken off the market.';
        } else {
            $names = implode(', ', array_column($frozenResources, 'name'));
            $explanations[] = sprintf(
                'Also freezes %d linked resource(s): %s — ₱%s of list value for the same window.',
                count($frozenResources),
                $names,
                number_format($opportunityCost, 2)
            );
        }

        $conflicts = $this->conflictingClaims($reservation, $windowStart, $windowEnd);

        foreach ($conflicts as $conflict) {
            $explanations[] = sprintf(
                'Conflict: %s already claims %s for %s. Approving this would double-book the space.',
                $conflict->label,
                $conflict->facilityName,
                $conflict->durationLabel()
            );
        }

        $reservationValue = (float) $reservation->total_price;
        $unpaid = $reservation->getRemainingBalance();

        if ($reservation->status === ReservationStatus::Pending) {
            $explanations[] = sprintf(
                'While this request waits, the space is already blocked. It has been pending for %s.',
                $this->humanDuration($reservation->created_at, now())
            );
        }

        if ($unpaid > 0) {
            $explanations[] = sprintf(
                '₱%s of ₱%s is still unpaid, so the hold is not yet secured by a verified payment.',
                number_format($unpaid, 2),
                number_format($reservationValue, 2)
            );
        }

        $net = $reservationValue - $opportunityCost;
        $explanations[] = $net >= 0
            ? sprintf(
                'Net effect if approved: +₱%s (₱%s booking value against ₱%s of space taken off the market).',
                number_format($net, 2),
                number_format($reservationValue, 2),
                number_format($opportunityCost, 2)
            )
            : sprintf(
                'Net effect if approved: -₱%s. This booking earns ₱%s but blocks ₱%s of space that could be sold separately.',
                number_format(abs($net), 2),
                number_format($reservationValue, 2),
                number_format($opportunityCost, 2)
            );

        return new DecisionImpact(
            reservationValue: $reservationValue,
            opportunityCost: $opportunityCost,
            explanations: $explanations,
            frozenResources: $frozenResources,
            conflicts: $conflicts->values()->all(),
            unpaidBalance: $unpaid,
        );
    }

    /**
     * Cost and collision consequences of each extra-hour option, so staff can
     * answer "can they stay two more hours?" without opening the calendar.
     *
     * @return list<array{hours: int, charge: float, collides: bool, collides_with: string|null, blocked_release: string|null}>
     */
    public function extraHourOptions(Reservation $reservation, int $maxHours = null): array
    {
        $maxHours ??= (int) config('resort.stay.max_additional_hours', 12);
        $reservation->loadMissing('facility.facilityType');

        $hourlyRate = $this->pricingService->hourlyChargeAmount(
            $reservation->facility,
            PricingService::LATE_CHECK_OUT_RATE_NAME
        );

        $searchStart = $reservation->occupancyEnd();
        $searchEnd = $searchStart->copy()->addHours($maxHours);

        $facilityIds = $this->coupledFacilityIds($reservation->facility);

        $nextClaim = $this->claimsInWindow($searchStart, $searchEnd, $facilityIds)
            ->reject(fn (OccupancyClaim $claim) => $claim->claimType === OccupancyClaim::TYPE_RESERVATION
                && $claim->url === route('admin.reservations.show', $reservation))
            ->sortBy(fn (OccupancyClaim $claim) => $claim->from->timestamp)
            ->first();

        $options = [];

        for ($hours = 1; $hours <= $maxHours; $hours++) {
            $releaseAt = $searchStart->copy()->addHours($hours);
            $collides = $nextClaim !== null && $releaseAt->greaterThan($nextClaim->from);

            $options[] = [
                'hours' => $hours,
                'charge' => $hourlyRate !== null ? $hourlyRate * $hours : 0.0,
                'collides' => $collides,
                'collides_with' => $collides ? $nextClaim->label . ' on ' . $nextClaim->facilityName : null,
                'blocked_release' => $releaseAt->format('M d, g:i A'),
            ];
        }

        return [
            'hourly_rate' => $hourlyRate,
            'next_claim' => $nextClaim,
            'options' => $options,
        ];
    }

    /**
     * Headline numbers for the board.
     *
     * @return array<string, mixed>
     */
    public function summary(Carbon $from, Carbon $to): array
    {
        $pending = Reservation::with(['facility.facilityType', 'payments'])
            ->where('status', ReservationStatus::Pending->value)
            ->get();

        $frozenListValue = $pending->sum(fn (Reservation $reservation) => $this->analyze($reservation)->opportunityCost);

        $claims = $this->claimsInWindow($from, $to);
        $bookableCount = Facility::where('is_active', true)->count();
        $claimedFacilityIds = $claims->pluck('facilityId')->unique();

        return [
            'pending_count' => $pending->count(),
            'pending_value' => (float) $pending->sum('total_price'),
            'frozen_list_value' => (float) $frozenListValue,
            'claim_count' => $claims->count(),
            'training_claim_count' => $claims->filter(fn (OccupancyClaim $c) => $c->isTraining())->count(),
            'unclaimed_facilities' => max(0, $bookableCount - $claimedFacilityIds->count()),
            'bookable_facilities' => $bookableCount,
        ];
    }

    /**
     * @param list<int>|null $facilityIds
     * @return Collection<int, OccupancyClaim>
     */
    private function reservationClaims(Carbon $from, Carbon $to, ?array $facilityIds = null): Collection
    {
        $pad = (int) config('resort.stay.max_additional_hours', 12);

        return Reservation::with(['user', 'facility'])
            ->whereIn('status', Reservation::blockingStatuses())
            ->when($facilityIds !== null, fn ($q) => $q->whereIn('facility_id', $facilityIds))
            ->where('check_in', '<', $to->copy()->addHours($pad))
            ->where('check_out', '>', $from->copy()->subHours($pad))
            ->get()
            ->filter(fn (Reservation $reservation) => $reservation->occupancyStart()->lessThan($to)
                && $reservation->occupancyEnd()->greaterThan($from))
            ->map(fn (Reservation $reservation) => new OccupancyClaim(
                claimType: OccupancyClaim::TYPE_RESERVATION,
                facilityId: $reservation->facility_id,
                facilityName: $reservation->facility->name,
                label: $reservation->reservation_code . ' — ' . $reservation->guestName(),
                from: $reservation->occupancyStart(),
                to: $reservation->occupancyEnd(),
                status: $reservation->status->label(),
                value: (float) $reservation->total_price,
                url: route('admin.reservations.show', $reservation),
                note: $reservation->guest_count . ' pax',
            ))
            ->values();
    }

    /**
     * @param list<int>|null $facilityIds
     * @return Collection<int, OccupancyClaim>
     */
    private function trainingClaims(Carbon $from, Carbon $to, ?array $facilityIds = null): Collection
    {
        $batches = TrainingBatch::with('facilities')
            ->blocking()
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->get();

        $claims = collect();

        foreach ($batches as $batch) {
            $facilities = $facilityIds === null
                ? $batch->facilities
                : $batch->facilities->whereIn('id', $facilityIds);

            // A batch with session hours holds its rooms once per training day,
            // so each day is its own claim on the calendar.
            foreach ($batch->occupancyWindows() as [$start, $end]) {
                if ($start->greaterThanOrEqualTo($to) || $end->lessThanOrEqualTo($from)) {
                    continue;
                }

                foreach ($facilities as $facility) {
                    $claims->push(new OccupancyClaim(
                        claimType: OccupancyClaim::TYPE_TRAINING,
                        facilityId: $facility->id,
                        facilityName: $facility->name,
                        label: $batch->cohort_code . ' — ' . $batch->program,
                        from: $start,
                        to: $end,
                        status: $batch->status->label(),
                        value: 0.0,
                        url: route('admin.training-batches.show', $batch),
                        note: $batch->slotsLabel(),
                    ));
                }
            }
        }

        return $claims->values();
    }

    /**
     * Claims that overlap this reservation's window on any coupled facility.
     *
     * @return Collection<int, OccupancyClaim>
     */
    private function conflictingClaims(Reservation $reservation, Carbon $windowStart, Carbon $windowEnd): Collection
    {
        $ownUrl = route('admin.reservations.show', $reservation);

        return $this->claimsInWindow($windowStart, $windowEnd, $this->coupledFacilityIds($reservation->facility))
            ->reject(fn (OccupancyClaim $claim) => $claim->url === $ownUrl);
    }

    /**
     * The facility plus every facility coupled to it through package links.
     *
     * @return list<int>
     */
    private function coupledFacilityIds(Facility $facility): array
    {
        return collect([$facility->id])
            ->merge($facility->contendingFacilities()->pluck('id'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * What a facility would sell for over this window at configured rates.
     * Facilities without a rate contribute nothing rather than guessing.
     */
    private function listValue(Facility $facility, Carbon $from, Carbon $to): float
    {
        try {
            return $this->pricingService->calculate($facility, $from, $to)->totalAmount / 100;
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function humanDuration(Carbon $from, Carbon $to): string
    {
        $minutes = max(0, $from->diffInMinutes($to));

        if ($minutes < 60) {
            return $minutes . ' min';
        }

        $hours = intdiv($minutes, 60);

        if ($hours < 48) {
            return $hours . 'h';
        }

        return intdiv($hours, 24) . ' days';
    }
}
