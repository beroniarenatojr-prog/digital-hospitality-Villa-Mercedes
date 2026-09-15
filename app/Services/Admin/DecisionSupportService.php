<?php

namespace App\Services\Admin;

use App\DTOs\DecisionImpact;
use App\DTOs\OccupancyClaim;
use App\Enums\ReservationStatus;
use App\Models\Facility;
use App\Models\Reservation;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns reservation data into decisions an owner can act on.
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
     * Rows include active facilities plus any inactive ones that still have a
     * hold in this window (so pending halls/packages are never hidden just
     * because a catalog item was renamed or turned off).
     *
     * @return list<array{facility: Facility, claims: list<array{claim: OccupancyClaim, offset: float, width: float}>}>
     */
    public function timeline(Carbon $from, Carbon $to, ?string $facilityTypeSlug = null): array
    {
        // Load every overlapping hold first, then decide which facility rows to show.
        // Filtering by active facilities alone used to drop pending packages/halls
        // that still sit on retired catalog rows.
        $claims = $this->claimsInWindow($from, $to);

        $facilityIds = Facility::query()
            ->where('is_active', true)
            ->when($facilityTypeSlug, fn ($q) => $q->whereHas('facilityType', fn ($t) => $t->where('slug', $facilityTypeSlug)))
            ->pluck('id')
            ->merge($claims->pluck('facilityId'))
            ->unique()
            ->values()
            ->all();

        $facilities = Facility::with('facilityType')
            ->whereIn('id', $facilityIds)
            ->when($facilityTypeSlug, fn ($q) => $q->whereHas('facilityType', fn ($t) => $t->where('slug', $facilityTypeSlug)))
            ->get()
            ->sortBy(function (Facility $facility) use ($claims) {
                // Keep resource types together, then booked rows first within each type.
                $hasClaim = $claims->contains(fn (OccupancyClaim $claim) => $claim->facilityId === $facility->id) ? 0 : 1;

                return sprintf(
                    '%05d-%d-%05d-%s',
                    $facility->facility_type_id,
                    $hasClaim,
                    $facility->sort_order,
                    mb_strtolower($facility->name)
                );
            })
            ->values();

        $shownIds = $facilities->pluck('id')->all();
        $claims = $claims->whereIn('facilityId', $shownIds);
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
     * @param  list<int>|null  $facilityIds
     * @return Collection<int, OccupancyClaim>
     */
    public function claimsInWindow(Carbon $from, Carbon $to, ?array $facilityIds = null): Collection
    {
        return $this->reservationClaims($from, $to, $facilityIds);
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
            'This booking keeps %s from %s until %s (%s).',
            $facility->name,
            $windowStart->format('M j, g:i A'),
            $windowEnd->format('M j, g:i A'),
            $this->humanDuration($windowStart, $windowEnd)
        );

        if ($reservation->early_check_in_hours || $reservation->late_check_out_hours) {
            $explanations[] = sprintf(
                'Extra time is included (%d hour(s) early check-in, %d hour(s) late check-out), so the space stays occupied longer than the main stay.',
                (int) $reservation->early_check_in_hours,
                (int) $reservation->late_check_out_hours
            );
        }

        foreach ($facility->contendingFacilities() as $contender) {
            $listValue = $this->listValue($contender, $windowStart, $windowEnd);
            $opportunityCost += $listValue;

            $reason = $facility->isPackage()
                ? sprintf('Included in the %s package, so it cannot be sold on its own', $facility->name)
                : sprintf('Tied to this booking because %s is part of that package', $facility->name);

            $frozenResources[] = [
                'name' => $contender->name,
                'reason' => $reason,
                'list_value' => $listValue,
            ];
        }

        if ($frozenResources === []) {
            $explanations[] = 'Nothing else is blocked — this space is not tied to a package or shared hall.';
        } else {
            $names = implode(', ', array_column($frozenResources, 'name'));
            $explanations[] = sprintf(
                'Approving this also blocks %s for the same dates (about ₱%s if those were sold separately).',
                $names,
                number_format($opportunityCost, 2)
            );
        }

        $conflicts = $this->conflictingClaims($reservation, $windowStart, $windowEnd);

        foreach ($conflicts as $conflict) {
            $explanations[] = sprintf(
                'Heads up: %s already has %s for %s. Approving this would double-book that space.',
                $conflict->facilityName,
                $conflict->label,
                $conflict->durationLabel()
            );
        }

        $reservationValue = (float) $reservation->total_price;
        $unpaid = $reservation->getRemainingBalance();

        if ($reservation->status === ReservationStatus::Pending) {
            $explanations[] = sprintf(
                'This is still waiting for your answer. The dates are already held so no one else can book them (waiting for %s).',
                $this->humanDuration($reservation->created_at, now())
            );
        }

        if ($unpaid > 0) {
            $explanations[] = sprintf(
                'Guest still owes ₱%s of ₱%s — collect the rest at the resort on arrival.',
                number_format($unpaid, 2),
                number_format($reservationValue, 2)
            );
            if ($reservation->deposit_required !== null) {
                $verified = round($reservationValue - $unpaid, 2);
                $explanations[] = $verified >= $reservation->requiredDepositAmount()
                    ? 'The required deposit is already verified.'
                    : 'The required deposit is not fully verified yet — check payments before you confirm.';
            }
        }

        $net = $reservationValue - $opportunityCost;
        $explanations[] = $net >= 0
            ? sprintf(
                'If you approve: you earn ₱%s from this booking, while about ₱%s of other sellable space stays blocked. Net: +₱%s.',
                number_format($reservationValue, 2),
                number_format($opportunityCost, 2),
                number_format($net, 2)
            )
            : sprintf(
                'If you approve: this booking earns ₱%s, but it blocks about ₱%s of space that could be sold separately. Net: -₱%s.',
                number_format($reservationValue, 2),
                number_format($opportunityCost, 2),
                number_format(abs($net), 2)
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
    public function extraHourOptions(Reservation $reservation, ?int $maxHours = null): array
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
                'collides_with' => $collides ? $nextClaim->label.' on '.$nextClaim->facilityName : null,
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
        $activeFacilityIds = Facility::where('is_active', true)->pluck('id');
        $bookableCount = $activeFacilityIds->count();
        $claimedActiveCount = $claims->pluck('facilityId')->unique()->intersect($activeFacilityIds)->count();

        return [
            'pending_count' => $pending->count(),
            'pending_value' => (float) $pending->sum('total_price'),
            'frozen_list_value' => (float) $frozenListValue,
            'claim_count' => $claims->pluck('url')->filter()->unique()->count(),
            'unclaimed_facilities' => max(0, $bookableCount - $claimedActiveCount),
            'bookable_facilities' => $bookableCount,
        ];
    }

    /**
     * @param  list<int>|null  $facilityIds
     * @return Collection<int, OccupancyClaim>
     */
    private function reservationClaims(Carbon $from, Carbon $to, ?array $facilityIds = null): Collection
    {
        $pad = (int) config('resort.stay.max_additional_hours', 12);

        // Do not filter by facility_id in SQL: package holds often sit on a
        // different facility id than the rooms/halls they freeze. We expand
        // each reservation onto its linked resources, then filter.
        return Reservation::with([
            'user',
            'facility.facilityType',
            'facility.packageComponents.componentFacility.facilityType',
            'facility.partOfPackages.facilityType',
        ])
            ->whereIn('status', Reservation::blockingStatuses())
            ->where('check_in', '<', $to->copy()->addHours($pad))
            ->where('check_out', '>', $from->copy()->subHours($pad))
            ->get()
            ->toBase()
            ->filter(fn (Reservation $reservation) => $reservation->occupancyStart()->lessThan($to)
                && $reservation->occupancyEnd()->greaterThan($from))
            ->flatMap(function (Reservation $reservation) use ($facilityIds) {
                $claims = $this->claimsForReservation($reservation);

                if ($facilityIds !== null) {
                    $claims = $claims->whereIn('facilityId', $facilityIds)->values();
                }

                return $claims;
            })
            ->values();
    }

    /**
     * Primary claim on the booked facility, plus linked holds on package components
     * (or parent packages) so the calendar shows halls/rooms blocked by packages.
     *
     * @return Collection<int, OccupancyClaim>
     */
    private function claimsForReservation(Reservation $reservation): Collection
    {
        $facility = $reservation->facility;
        $from = $reservation->occupancyStart();
        $to = $reservation->occupancyEnd();
        $url = route('admin.reservations.show', $reservation);
        $status = $reservation->status->label();
        $value = (float) $reservation->total_price;

        $claims = collect([
            new OccupancyClaim(
                claimType: OccupancyClaim::TYPE_RESERVATION,
                facilityId: $facility->id,
                facilityName: $facility->name,
                label: $reservation->reservation_code.' — '.$reservation->guestName(),
                from: $from,
                to: $to,
                status: $status,
                value: $value,
                url: $url,
                note: $reservation->guest_count.' guests',
                isLinkedHold: false,
            ),
        ]);

        foreach ($facility->contendingFacilities() as $contender) {
            $claims->push(new OccupancyClaim(
                claimType: OccupancyClaim::TYPE_RESERVATION,
                facilityId: $contender->id,
                facilityName: $contender->name,
                label: $reservation->reservation_code.' — via '.$facility->name,
                from: $from,
                to: $to,
                status: $status,
                value: 0.0,
                url: $url,
                note: 'Also blocked by this booking',
                isLinkedHold: true,
            ));
        }

        return $claims;
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
            return $minutes === 1 ? '1 minute' : $minutes.' minutes';
        }

        $hours = intdiv($minutes, 60);

        if ($hours < 48) {
            return $hours === 1 ? 'about 1 hour' : 'about '.$hours.' hours';
        }

        $days = intdiv($hours, 24);

        return $days === 1 ? 'about 1 day' : 'about '.$days.' days';
    }
}
