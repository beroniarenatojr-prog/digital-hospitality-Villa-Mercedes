<?php

namespace App\Services\Admin;

use App\Enums\FacilityStatus;
use App\Enums\FacilityType;
use App\Enums\PaymentStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Facility;
use App\Models\GuestReview;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Admin\ReportFilters;
use App\Support\ResortTime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ReportingService
{
    use ReportCharts;

    /**
     * Get booking summary counts (totals, by status, by source).
     */
    public function getBookingSummary(
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?string $facilityType = null,
        ?string $source = null,
    ): array {
        $query = Reservation::query();
        $this->applyDateFilter($query, 'reservations.created_at', $startDate, $endDate);
        $this->applyReservationDimensions($query, $facilityType, $source);

        $total = $query->count();

        $byStatus = (clone $query)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $bySource = (clone $query)
            ->select('reservation_source', DB::raw('count(*) as count'))
            ->groupBy('reservation_source')
            ->pluck('count', 'reservation_source')
            ->toArray();

        $byFacilityType = (clone $query)
            ->join('facilities', 'reservations.facility_id', '=', 'facilities.id')
            ->join('facility_types', 'facilities.facility_type_id', '=', 'facility_types.id')
            ->select('facility_types.slug', DB::raw('count(reservations.id) as count'))
            ->groupBy('facility_types.slug')
            ->pluck('count', 'slug')
            ->toArray();

        return [
            'total' => $total,
            'by_status' => $byStatus,
            'by_source' => $bySource,
            'by_facility_type' => $byFacilityType,
        ];
    }

    /**
     * Get reservation trends over time, zero-filled for chart rendering.
     *
     * Supported groupings: week, month, year (quarter retained for compatibility).
     *
     * @return list<array{period: string, label: string, value: int}>
     */
    public function getBookingTrends(
        string $grouping = 'month',
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?string $facilityType = null,
        ?string $source = null,
    ): array {
        $grouping = in_array($grouping, ['week', 'month', 'year', 'quarter'], true) ? $grouping : 'month';

        // Booking trends count creation instants, which are stored in UTC, so
        // this one legitimately ends on the UTC today. Resort days belong to
        // operations and payment_date, not here.
        $endDate = ($endDate ?? now())->copy()->endOfDay();

        if ($startDate) {
            $startDate = match ($grouping) {
                'week' => $startDate->copy()->startOfWeek(Carbon::MONDAY),
                'year' => $startDate->copy()->startOfYear(),
                'quarter' => $startDate->copy()->firstOfQuarter(),
                default => $startDate->copy()->startOfMonth(),
            };
        } else {
            $startDate = match ($grouping) {
                'week' => $endDate->copy()->subWeeks(7)->startOfWeek(Carbon::MONDAY),
                'year' => $endDate->copy()->subYears(4)->startOfYear(),
                'quarter' => $endDate->copy()->subQuarters(3)->firstOfQuarter(),
                default => $endDate->copy()->subMonths(11)->startOfMonth(),
            };
        }

        $query = Reservation::query()
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate);
        $this->applyReservationDimensions($query, $facilityType, $source);

        // Group in PHP so period keys always match the zero-fill cursor
        // (SQLite week numbering differs from ISO / MySQL).
        $totals = [];
        foreach ($query->pluck('created_at') as $createdAt) {
            $period = $this->bookingTrendsPeriodKey(Carbon::parse($createdAt), $grouping);
            $totals[$period] = ($totals[$period] ?? 0) + 1;
        }

        $cursor = $startDate->copy();
        $series = [];

        while ($cursor->lte($endDate)) {
            $period = $this->bookingTrendsPeriodKey($cursor, $grouping);
            $label = $this->bookingTrendsPeriodLabel($cursor, $grouping);

            $series[] = [
                'period' => $period,
                'label' => $label,
                'value' => (int) ($totals[$period] ?? 0),
            ];

            match ($grouping) {
                'week' => $cursor->addWeek(),
                'year' => $cursor->addYear(),
                'quarter' => $cursor->addQuarter(),
                default => $cursor->addMonth(),
            };
        }

        return $series;
    }

    private function bookingTrendsPeriodKey(Carbon $cursor, string $grouping): string
    {
        return match ($grouping) {
            'week' => sprintf('%d-W%02d', (int) $cursor->isoWeekYear(), (int) $cursor->isoWeek()),
            'year' => $cursor->format('Y'),
            'quarter' => $cursor->format('Y').'-Q'.$cursor->quarter,
            default => $cursor->format('Y-m'),
        };
    }

    private function bookingTrendsPeriodLabel(Carbon $cursor, string $grouping): string
    {
        return match ($grouping) {
            'week' => 'W'.$cursor->isoWeek(),
            'year' => $cursor->format('Y'),
            'quarter' => 'Q'.$cursor->quarter.' '.$cursor->format('Y'),
            default => $cursor->format('M'),
        };
    }

    /**
     * Get the most reserved services (rooms and function halls).
     */
    public function getPopularServices(
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?string $facilityType = null,
        ?string $source = null,
    ): array {
        $query = Reservation::query()
            ->join('facilities', 'reservations.facility_id', '=', 'facilities.id')
            ->join('facility_types', 'facilities.facility_type_id', '=', 'facility_types.id')
            ->whereIn('facility_types.slug', [FacilityType::Room->value, FacilityType::FunctionHall->value]);

        $this->applyDateFilter($query, 'reservations.created_at', $startDate, $endDate);
        if ($facilityType) {
            $query->where('facility_types.slug', $facilityType);
        }
        if ($source) {
            $query->where('reservations.reservation_source', $source);
        }

        return $query->select('facilities.name', DB::raw('count(reservations.id) as count'))
            ->groupBy('facilities.id', 'facilities.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->pluck('count', 'name')
            ->toArray();
    }

    /**
     * Get package popularity.
     */
    public function getPackagePopularity(
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?string $facilityType = null,
        ?string $source = null,
    ): array {
        $query = Reservation::query()
            ->join('facilities', 'reservations.facility_id', '=', 'facilities.id')
            ->join('facility_types', 'facilities.facility_type_id', '=', 'facility_types.id')
            ->where('facility_types.slug', FacilityType::ResortPackage->value);

        $this->applyDateFilter($query, 'reservations.created_at', $startDate, $endDate);
        if ($facilityType) {
            $query->where('facility_types.slug', $facilityType);
        }
        if ($source) {
            $query->where('reservations.reservation_source', $source);
        }

        $totalPackages = (clone $query)->count();

        $packages = $query->select('facilities.name', DB::raw('count(reservations.id) as count'))
            ->groupBy('facilities.id', 'facilities.name')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) use ($totalPackages) {
                return [
                    'name' => $item->name,
                    'count' => $item->count,
                    'share_percentage' => $totalPackages > 0 ? round(($item->count / $totalPackages) * 100, 2) : 0,
                ];
            })
            ->toArray();

        return [
            'total_package_bookings' => $totalPackages,
            'breakdown' => $packages,
        ];
    }

    /**
     * Get revenue summaries.
     */
    public function getRevenueSummary(
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?string $facilityType = null,
        ?string $source = null,
    ): array {
        $resQuery = Reservation::query()
            ->whereNotIn('status', [ReservationStatus::Cancelled->value, ReservationStatus::Rejected->value]);
        $this->applyDateFilter($resQuery, 'reservations.created_at', $startDate, $endDate);
        $this->applyReservationDimensions($resQuery, $facilityType, $source);

        $reservationValue = $resQuery->sum('total_price');
        $bookingValue = (clone $resQuery)
            ->whereIn('status', [ReservationStatus::Approved->value, ReservationStatus::Completed->value])
            ->sum('total_price');

        $outstandingBalance = (clone $resQuery)
            ->withSum([
                'payments as verified_paid_total' => fn (Builder $query) => $query
                    ->where('status', PaymentStatus::Verified->value),
            ], 'amount')
            ->get(['reservations.id', 'reservations.total_price'])
            ->sum(fn (Reservation $reservation): float => max(
                0,
                (float) $reservation->total_price - (float) ($reservation->verified_paid_total ?? 0)
            ));

        $payQuery = Payment::query()->where('status', PaymentStatus::Verified->value);
        $this->applyDateFilter($payQuery, 'payments.payment_date', $startDate, $endDate);
        $this->applyPaymentDimensions($payQuery, $facilityType, $source);

        $verifiedRevenue = $payQuery->sum('amount');

        return [
            'reservation_value' => (float) $reservationValue,
            'booking_value' => (float) $bookingValue,
            'verified_payment_revenue' => (float) $verifiedRevenue,
            'outstanding_balance' => $outstandingBalance,
        ];
    }

    /**
     * Get payment summaries.
     */
    public function getPaymentSummary(
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?string $facilityType = null,
        ?string $source = null,
    ): array {
        $query = Payment::query();
        $this->applyDateFilter($query, 'payments.payment_date', $startDate, $endDate);
        $this->applyPaymentDimensions($query, $facilityType, $source);

        $totalPayments = (clone $query)->count();

        $byStatus = (clone $query)
            ->select('status', DB::raw('count(id) as count'), DB::raw('sum(amount) as total_amount'))
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->status->value => [
                    'count' => $item->count,
                    'total_amount' => (float) $item->total_amount,
                ]];
            })
            ->toArray();

        return [
            'total_payments' => $totalPayments,
            'by_status' => $byStatus,
        ];
    }

    /**
     * Get total customer count.
     */
    public function getCustomerCount(): int
    {
        return User::role('Customer')->count();
    }

    /**
     * Get facility status summary.
     */
    public function getFacilityStatusSummary(): array
    {
        return Facility::query()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Occupancy for a calendar day, grouped by facility type.
     *
     * A facility counts as occupied when a blocking reservation's occupancy
     * window overlaps that day.
     *
     * @return list<array{slug: string, label: string, total: int, occupied: int, pct: int}>
     */
    public function getOccupancyByType(?Carbon $day = null): array
    {
        // Occupancy is a resort calendar day measured against the wall-clock
        // check-in and check-out values, never against a UTC date.
        $day = ($day ?? ResortTime::now())->copy();
        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();
        $paddingHours = (int) config('resort.stay.max_additional_hours', 12);

        $facilities = Facility::query()
            ->where('is_active', true)
            ->with('facilityType:id,slug,name')
            ->get(['id', 'facility_type_id']);

        $occupiedIds = Reservation::query()
            ->whereIn('status', Reservation::blockingStatuses())
            ->where('check_in', '<=', $to->copy()->addHours($paddingHours))
            ->where('check_out', '>=', $from->copy()->subHours($paddingHours))
            ->whereRaw(DB::connection()->getDriverName() === 'sqlite'
                ? "datetime(check_in, '-' || COALESCE(early_check_in_hours, 0) || ' hours') < ?"
                : 'TIMESTAMPADD(HOUR, -COALESCE(early_check_in_hours, 0), check_in) < ?', [$to->toDateTimeString()])
            ->whereRaw(DB::connection()->getDriverName() === 'sqlite'
                ? "datetime(check_out, '+' || COALESCE(late_check_out_hours, 0) || ' hours') > ?"
                : 'TIMESTAMPADD(HOUR, COALESCE(late_check_out_hours, 0), check_out) > ?', [$from->toDateTimeString()])
            ->distinct()
            ->pluck('facility_id')
            ->all();

        return $facilities
            ->groupBy(fn (Facility $facility) => $facility->facilityType?->slug ?? 'unknown')
            ->map(function ($group, string $slug) use ($occupiedIds) {
                $total = $group->count();
                $occupied = $group->whereIn('id', $occupiedIds)->count();
                $first = $group->first();

                return [
                    'slug' => $slug,
                    'label' => $first?->facilityType?->name ?? $slug,
                    'total' => $total,
                    'occupied' => $occupied,
                    'pct' => $total > 0 ? (int) round(($occupied / $total) * 100) : 0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Checked-in guests for the selected range, with contact details for the
     * emergency / front-desk roll call.
     *
     * @return list<array{
     *   reservation_code: string,
     *   guest_name: string,
     *   facility_name: string,
     *   facility_type: string,
     *   guest_count: int,
     *   guest_phone: ?string,
     *   guest_email: ?string,
     *   checked_in_at: Carbon,
     *   check_out: Carbon,
     *   expected_departure_label: string,
     * }>
     */
    public function getCheckedInGuests(
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?string $facilityType = null,
        ?string $source = null,
    ): array {
        $query = Reservation::query()
            ->with(['facility.facilityType', 'user'])
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at');

        $this->applyDateFilter($query, 'reservations.checked_in_at', $from, $to);
        $this->applyReservationDimensions($query, $facilityType, $source);

        return $query
            ->orderBy('checked_in_at')
            ->get()
            ->map(static function (Reservation $reservation): array {
                return [
                    'reservation_id' => $reservation->id,
                    'reservation_code' => $reservation->reservation_code,
                    'guest_name' => $reservation->guestName(),
                    'facility_name' => $reservation->facility?->name ?? 'Unknown',
                    'facility_type' => $reservation->facility?->facilityType?->name ?? 'Unknown',
                    'guest_count' => $reservation->guest_count,
                    'guest_phone' => $reservation->guestPhone(),
                    'guest_email' => $reservation->guestEmail(),
                    'checked_in_at' => $reservation->checked_in_at,
                    'check_out' => $reservation->check_out,
                    'expected_departure_label' => $reservation->check_out->format('M j, Y g:i A'),
                ];
            })
            ->all();
    }

    /**
     * Arrivals per calendar day for a bounded check-in range: how many parties
     * arrived that day and how many people arrived in them.
     *
     * This answers "how many guests checked in today / this week / this month /
     * this year". It counts actual check-ins (checked_in_at), not bookings.
     *
     * @return list<array{date: string, label: string, guests: int, parties: int}>
     */
    public function getCheckInVolumeByDay(
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?string $facilityType = null,
        ?string $source = null,
    ): array {
        if (! $from || ! $to) {
            return [];
        }

        // A check-in range can never run past the present shift; future days have
        // no arrivals to count yet.
        $windowStart = $from->copy()->startOfDay();
        $windowEnd = $to->copy()->endOfDay()->min(ResortTime::now()->copy()->endOfDay());

        if ($windowStart->gt($windowEnd)) {
            return [];
        }

        $query = Reservation::query()
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at');

        $this->applyDateFilter($query, 'reservations.checked_in_at', $windowStart, $windowEnd);
        $this->applyReservationDimensions($query, $facilityType, $source);

        $byDay = $query
            ->selectRaw('DATE(reservations.checked_in_at) as day, count(reservations.id) as parties, COALESCE(SUM(reservations.guest_count), 0) as guests')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $cursor = $windowStart->copy();
        $series = [];

        while ($cursor->lte($windowEnd)) {
            $date = $cursor->toDateString();
            $row = $byDay->get($date);

            $series[] = [
                'date' => $date,
                'label' => $cursor->format('M j, Y'),
                'guests' => (int) ($row->guests ?? 0),
                'parties' => (int) ($row->parties ?? 0),
            ];

            $cursor->addDay();
        }

        return $series;
    }

    /**
     * The checked-in guest registry: every stay checked in for the selected
     * range, with the headcounts an emergency roll call actually needs.
     *
     * Guests and parties are different numbers and are reported separately: a
     * party of six arriving as one booking is one party and six guests. Counting
     * rows instead of `guest_count` under-reports who is on the property.
     *
     * @return array{
     *   rows: list<array<string, mixed>>,
     *   guest_total: int,
     *   party_total: int,
     *   is_live: bool,
     *   range_label: string,
     *   volume_by_day: list<array{date: string, label: string, guests: int, parties: int}>,
     * }
     */
    public function buildCheckInRegistry(
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?string $facilityType = null,
        ?string $source = null,
    ): array {
        // With no range selected the registry is the live in-house list, which is
        // the list a front desk wants when it opens the screen.
        $isLive = ! $from && ! $to;
        $rows = $this->getCheckedInGuests($from, $to, $facilityType, $source);

        return [
            'rows' => $rows,
            'guest_total' => array_sum(array_column($rows, 'guest_count')),
            'party_total' => count($rows),
            'is_live' => $isLive,
            'range_label' => $isLive
                ? 'the current shift'
                : ResortTime::dateRangeLabel($from, $to),
            'volume_by_day' => $this->getCheckInVolumeByDay($from, $to, $facilityType, $source),
        ];
    }

    /**
     * Current occupancy snapshot across all facility types.
     *
     * "Occupied" means the facility has a guest checked in right now. Because
     * capacity-based facilities can host more than one stay, the same room can
     * appear more than once on the occupancy list; the inventory counts here are
     * unique facilities.
     *
     * @return array{
     *   total_facilities: int,
     *   occupied_facilities: int,
     *   by_type: list<array{label: string, type: string, occupied: int, inventory: int, pct: int}>,
     *   occupied_list: array<int, array{
     *     reservation_code: string,
     *     guest_name: string,
     *     facility_name: string,
     *     facility_type: string,
     *     guest_count: int,
     *     guest_phone: ?string,
     *     checked_in_at: Carbon,
     *     check_out: Carbon,
     *   }>,
     * }
     */
    public function getCurrentOccupancy(?string $facilityType = null): array {
        $occupied = Reservation::query()
            ->with(['facility.facilityType', 'user'])
            ->withPaymentAllocation()
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at');

        $this->applyReservationDimensions($occupied, $facilityType, null);

        $occupiedList = $occupied
            ->orderBy('checked_in_at')
            ->get()
            ->map(static function (Reservation $reservation): array {
                return [
                    'reservation_id' => $reservation->id,
                    'group_id' => $reservation->reservation_group_id,
                    'group_code' => $reservation->reservationGroup?->group_code,
                    'group_guest_name' => $reservation->reservationGroup?->guest_name,
                    'reservation_code' => $reservation->reservation_code,
                    'guest_name' => $reservation->guestName(),
                    'facility_id' => $reservation->facility_id,
                    'facility_name' => $reservation->facility?->name ?? 'Unknown',
                    'facility_type' => $reservation->facility?->facilityType?->name ?? 'Unknown',
                    'guest_count' => $reservation->guest_count,
                    'guest_phone' => $reservation->guestPhone(),
                    'checked_in_at' => $reservation->checked_in_at,
                    'check_out' => $reservation->occupancyEnd(),
                    'remaining_balance' => $reservation->getRemainingBalance(),
                ];
            })
            ->all();

        // A facility can host more than one stay (halls and cottages are sold by
        // seat or session), so "in use" counts distinct facilities, not rows.
        $occupiedUnique = collect($occupiedList)->unique('facility_id');

        $inventoryByType = Facility::query()
            ->with('facilityType')
            ->where('is_active', true)
            ->when($facilityType, fn ($q, $type) => $q->whereHas('facilityType', fn ($tq) => $tq->where('slug', $type)))
            ->get()
            ->groupBy(fn (Facility $f) => $f->facilityType?->name ?? 'Unknown')
            ->map(fn ($group) => $group->count());

        $occupiedByType = $occupiedUnique
            ->groupBy('facility_type')
            ->map(fn ($group) => $group->count());

        $byType = $inventoryByType
            ->keys()
            ->merge($occupiedByType->keys())
            ->unique()
            ->values()
            ->map(function (string $label) use ($inventoryByType, $occupiedByType): array {
                $inventory = (int) ($inventoryByType[$label] ?? 0);
                $occupied = (int) ($occupiedByType[$label] ?? 0);

                return [
                    'label' => $label,
                    'type' => $label,
                    'occupied' => $occupied,
                    'inventory' => $inventory,
                    'pct' => $inventory > 0 ? (int) round(($occupied / $inventory) * 100) : 0,
                ];
            })
            ->all();

        return [
            'total_facilities' => (int) $inventoryByType->sum(),
            'occupied_facilities' => $occupiedUnique->count(),
            'by_type' => $byType,
            'occupied_list' => $occupiedList,
        ];
    }

    /**
     * Everything a walk-in can actually be sold right now, with the capacity and
     * the next claim on each space.
     *
     * This is the screen a front desk uses when a party of twenty walks in and
     * asks to stay tonight: which spaces are open at this moment, which of them
     * fit the party, and when the next booking takes each one away.
     *
     * Availability here follows the same blocking statuses and occupancy windows
     * the booking engine uses, so a space shown as open is genuinely sellable.
     *
     * @return array{
     *   minimum_pax: ?int,
     *   rows: list<array<string, mixed>>,
     *   recommended: list<array<string, mixed>>,
     *   open_count: int,
     *   sellable_count: int,
     * }
     */
    public function getWalkInAvailability(?int $minimumPax = null, ?string $facilityType = null): array
    {
        $now = ResortTime::databaseNow();

        $facilities = Facility::query()
            ->with('facilityType')
            ->where('is_active', true)
            ->where('is_listed', true)
            ->when($facilityType, fn (Builder $query, string $type) => $query->whereHas('facilityType', fn (Builder $typeQuery) => $typeQuery->where('slug', $type)))
            ->orderBy('name')
            ->get();

        // Every hold that could still matter today, plus anything starting later:
        // the next claim is what turns "free now" into "free until".
        $blocking = Reservation::query()
            ->whereIn('status', Reservation::blockingStatuses())
            ->where('check_out', '>', $now->copy()->subDay())
            ->orderBy('check_in')
            ->get([
                'id',
                'facility_id',
                'reservation_code',
                'check_in',
                'check_out',
                'early_check_in_hours',
                'late_check_out_hours',
                'guest_count',
            ]);

        $rows = $facilities->map(function (Facility $facility) use ($blocking, $now, $minimumPax): array {
            $claims = $blocking->where('facility_id', $facility->id);

            $current = $claims->first(
                fn (Reservation $claim): bool => $claim->occupancyStart()->lessThanOrEqualTo($now)
                    && $claim->occupancyEnd()->greaterThan($now)
            );

            $next = $claims
                ->filter(fn (Reservation $claim): bool => $claim->occupancyStart()->greaterThan($now))
                ->sortBy(fn (Reservation $claim): int => $claim->occupancyStart()->timestamp)
                ->first();

            $maxCapacity = $facility->effectiveMaxCapacity();
            $fits = $minimumPax === null || ($maxCapacity !== null && $maxCapacity >= $minimumPax);
            $openNow = $facility->status === FacilityStatus::Available && $current === null;

            return [
                'id' => $facility->id,
                'name' => $facility->name,
                'type' => $facility->facilityType?->name ?? 'Unknown',
                'type_slug' => $facility->facilityType?->slug ?? 'unknown',
                'included_capacity' => $facility->includedGuestCount(),
                'max_capacity' => $maxCapacity,
                'status_label' => $facility->status->label(),
                'open_now' => $openNow,
                'fits' => $fits,
                'occupied_until' => $current?->occupancyEnd(),
                'occupied_by' => $current?->reservation_code,
                'next_booking_at' => $next?->occupancyStart(),
                'next_booking_by' => $next?->reservation_code,
            ];
        });

        $recommended = $rows
            ->filter(fn (array $row): bool => $row['open_now'] && $row['fits'])
            ->sortBy([
                fn (array $a, array $b) => ($a['max_capacity'] ?? PHP_INT_MAX) <=> ($b['max_capacity'] ?? PHP_INT_MAX),
                fn (array $a, array $b) => strcasecmp($a['name'], $b['name']),
            ])
            ->values()
            ->all();

        return [
            'minimum_pax' => $minimumPax,
            'rows' => $rows->values()->all(),
            'recommended' => $recommended,
            'open_count' => $rows->where('open_now', true)->count(),
            'sellable_count' => count($recommended),
        ];
    }

    /**
     * Operational positions that need a staff decision today.
     *
     * @return array{
     *   walk_ins_today: list<array{reservation_code: string, guest_name: string, facility_name: string, guest_count: int, created_at: Carbon}>,
     *   missed_arrivals: list<array{reservation_code: string, guest_name: string, facility_name: string, check_in: Carbon, guest_count: int}>,
     *   overdue_departures: list<array{reservation_code: string, guest_name: string, facility_name: string, check_out: Carbon, guest_count: int}>,
     * }
     */
    public function getOperationalPositions(): array {
        $now = ResortTime::now();
        $today = $now->copy()->startOfDay();

        $walkIns = Reservation::query()
            ->with(['facility.facilityType', 'user'])
            ->where('reservation_source', ReservationSource::WalkIn->value)
            ->whereBetween('created_at', [$today, $now->copy()->endOfDay()])
            ->orderByDesc('created_at')
            ->get()
            ->map(static function (Reservation $reservation): array {
                return [
                    'reservation_id' => $reservation->id,
                    'reservation_code' => $reservation->reservation_code,
                    'guest_name' => $reservation->guestName(),
                    'facility_name' => $reservation->facility?->name ?? 'Unknown',
                    'guest_count' => $reservation->guest_count,
                    'created_at' => $reservation->created_at,
                ];
            })
            ->all();

        $missedArrivals = Reservation::query()
            ->with(['facility.facilityType', 'user'])
            ->where('status', ReservationStatus::Approved->value)
            ->whereNull('checked_in_at')
            ->where('check_out', '<', $now)
            ->orderBy('check_out')
            ->get()
            ->map(static function (Reservation $reservation): array {
                return [
                    'reservation_id' => $reservation->id,
                    'reservation_code' => $reservation->reservation_code,
                    'guest_name' => $reservation->guestName(),
                    'facility_name' => $reservation->facility?->name ?? 'Unknown',
                    'check_in' => $reservation->check_in,
                    'guest_count' => $reservation->guest_count,
                ];
            })
            ->all();

        $overdueDepartures = Reservation::query()
            ->with(['facility.facilityType', 'user'])
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->where('check_out', '<', $now)
            ->orderBy('check_out')
            ->get()
            ->map(static function (Reservation $reservation): array {
                return [
                    'reservation_id' => $reservation->id,
                    'reservation_code' => $reservation->reservation_code,
                    'guest_name' => $reservation->guestName(),
                    'facility_name' => $reservation->facility?->name ?? 'Unknown',
                    'check_out' => $reservation->check_out,
                    'guest_count' => $reservation->guest_count,
                ];
            })
            ->all();

        return [
            'walk_ins_today' => $walkIns,
            'missed_arrivals' => $missedArrivals,
            'overdue_departures' => $overdueDepartures,
        ];
    }

    /**
     * Verified payment totals grouped by day or month, with zero-filled periods.
     *
     * @return list<array{period: string, label: string, value: float}>
     */
    public function getVerifiedRevenueTrend(string $grouping = 'day', ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        // payment_date is the receipt date staff recorded, so the series ends
        // on the resort's today, not the UTC today.
        $endDate = ($endDate ?? ResortTime::now())->copy()->endOfDay();

        if ($grouping === 'month') {
            $startDate = ($startDate ?? $endDate->copy()->subMonths(11)->startOfMonth())->copy()->startOfMonth();
        } else {
            $grouping = 'day';
            $startDate = ($startDate ?? $endDate->copy()->subDays(6)->startOfDay())->copy()->startOfDay();
        }

        $driver = DB::connection()->getDriverName();

        if ($grouping === 'month') {
            $selectRaw = $driver === 'sqlite'
                ? "strftime('%Y-%m', payment_date) as period"
                : "DATE_FORMAT(payment_date, '%Y-%m') as period";
        } else {
            $selectRaw = $driver === 'sqlite'
                ? "strftime('%Y-%m-%d', payment_date) as period"
                : "DATE_FORMAT(payment_date, '%Y-%m-%d') as period";
        }

        $totals = Payment::query()
            ->where('status', PaymentStatus::Verified->value)
            ->whereDate('payment_date', '>=', $startDate->toDateString())
            ->whereDate('payment_date', '<=', $endDate->toDateString())
            ->selectRaw($selectRaw)
            ->selectRaw('sum(amount) as total_amount')
            ->groupBy('period')
            ->orderBy('period')
            ->pluck('total_amount', 'period')
            ->all();

        $cursor = $startDate->copy();
        $series = [];

        while ($cursor->lte($endDate)) {
            $period = $grouping === 'month' ? $cursor->format('Y-m') : $cursor->format('Y-m-d');
            $label = $grouping === 'month' ? $cursor->format('M') : $cursor->format('D');

            $series[] = [
                'period' => $period,
                'label' => $label,
                'value' => (float) ($totals[$period] ?? 0),
            ];

            $grouping === 'month' ? $cursor->addMonth() : $cursor->addDay();
        }

        return $series;
    }

    /**
     * Current-data analytics used by all report surfaces.
     *
     * @return array<string, mixed>
     */
    public function getDecisionMetrics(ReportFilters $filters): array
    {
        return [
            'departmentRevenue' => $this->getDepartmentRevenue($filters),
            'demandMetrics' => $this->getDemandMetrics($filters),
            'roomPerformance' => $this->getRoomPerformance($filters),
            'operations' => $this->getOperationsSummary($filters),
            'alerts' => $this->getReportingAlerts($filters),
            'metricDefinitions' => $this->metricDefinitions(),
            'charts' => $this->getCharts($filters),
        ];
    }

    /**
     * @return list<array{key: string, label: string, booking_value: float, earned_revenue: float, cash_collected: float, tracked: bool}>
     */
    public function getDepartmentRevenue(ReportFilters $filters): array
    {
        $labels = [
            FacilityType::Room->value => 'Rooms',
            FacilityType::FunctionHall->value => 'Events / function halls',
            FacilityType::Cottage->value => 'Cottages / resort services',
            FacilityType::ResortPackage->value => 'Resort packages',
        ];

        $reservationQuery = Reservation::query()
            ->join('facilities', 'reservations.facility_id', '=', 'facilities.id')
            ->join('facility_types', 'facilities.facility_type_id', '=', 'facility_types.id')
            ->whereIn('reservations.status', [ReservationStatus::Approved->value, ReservationStatus::Completed->value]);
        $this->applyDateFilter($reservationQuery, 'reservations.created_at', $filters->startDate, $filters->endDate);
        if ($filters->facilityType) {
            $reservationQuery->where('facility_types.slug', $filters->facilityType);
        }
        if ($filters->source) {
            $reservationQuery->where('reservations.reservation_source', $filters->source);
        }

        $booked = (clone $reservationQuery)
            ->select('facility_types.slug', DB::raw('sum(reservations.total_price) as total'))
            ->groupBy('facility_types.slug')
            ->pluck('total', 'slug');

        $deliveredQuery = Reservation::query()
            ->join('facilities', 'reservations.facility_id', '=', 'facilities.id')
            ->join('facility_types', 'facilities.facility_type_id', '=', 'facility_types.id')
            ->whereIn('reservations.status', [ReservationStatus::Approved->value, ReservationStatus::Completed->value])
            ->where(function (Builder $query) {
                $query->where('reservations.status', ReservationStatus::Completed->value)
                    ->orWhereNotNull('reservations.checked_out_at');
            });
        $this->applyDateFilter($deliveredQuery, 'reservations.check_out', $filters->startDate, $filters->endDate);
        if ($filters->facilityType) {
            $deliveredQuery->where('facility_types.slug', $filters->facilityType);
        }
        if ($filters->source) {
            $deliveredQuery->where('reservations.reservation_source', $filters->source);
        }
        $earned = $deliveredQuery
            ->select('facility_types.slug', DB::raw('sum(reservations.total_price) as total'))
            ->groupBy('facility_types.slug')
            ->pluck('total', 'slug');

        $cashQuery = Payment::query()
            ->join('reservations', 'payments.reservation_id', '=', 'reservations.id')
            ->join('facilities', 'reservations.facility_id', '=', 'facilities.id')
            ->join('facility_types', 'facilities.facility_type_id', '=', 'facility_types.id')
            ->where('payments.status', PaymentStatus::Verified->value);
        $this->applyDateFilter($cashQuery, 'payments.payment_date', $filters->startDate, $filters->endDate);
        if ($filters->facilityType) {
            $cashQuery->where('facility_types.slug', $filters->facilityType);
        }
        if ($filters->source) {
            $cashQuery->where('reservations.reservation_source', $filters->source);
        }
        $cash = $cashQuery
            ->select('facility_types.slug', DB::raw('sum(payments.amount) as total'))
            ->groupBy('facility_types.slug')
            ->pluck('total', 'slug');

        $rows = [];
        foreach ($labels as $key => $label) {
            if ($filters->facilityType && $filters->facilityType !== $key) {
                continue;
            }
            $rows[] = [
                'key' => $key,
                'label' => $label,
                'booking_value' => (float) ($booked[$key] ?? 0),
                'earned_revenue' => (float) ($earned[$key] ?? 0),
                'cash_collected' => (float) ($cash[$key] ?? 0),
                'tracked' => true,
            ];
        }
        $rows[] = [
            'key' => 'food_beverage',
            'label' => 'Food & beverage',
            'booking_value' => 0.0,
            'earned_revenue' => 0.0,
            'cash_collected' => 0.0,
            'tracked' => false,
        ];

        return $rows;
    }

    /** @return array<string, mixed> */
    public function getDemandMetrics(ReportFilters $filters): array
    {
        $query = Reservation::query();
        $this->applyDateFilter($query, 'reservations.created_at', $filters->startDate, $filters->endDate);
        $this->applyReservationDimensions($query, $filters->facilityType, $filters->source);

        $total = (clone $query)->count();
        $cancelled = (clone $query)->where('status', ReservationStatus::Cancelled->value)->count();
        $eligible = (clone $query)
            ->whereNotIn('status', [ReservationStatus::Rejected->value, ReservationStatus::Expired->value])
            ->get(['created_at', 'check_in']);
        $leadTimes = $eligible->map(fn (Reservation $reservation): int => max(
            0,
            (int) $reservation->created_at->copy()->startOfDay()->diffInDays($reservation->check_in->copy()->startOfDay(), false)
        ));

        $windows = ['0–1 days' => 0, '2–7 days' => 0, '8–30 days' => 0, '31+ days' => 0];
        foreach ($leadTimes as $days) {
            $key = match (true) {
                $days <= 1 => '0–1 days',
                $days <= 7 => '2–7 days',
                $days <= 30 => '8–30 days',
                default => '31+ days',
            };
            $windows[$key]++;
        }

        $onlineUserIds = (clone $query)
            ->where('reservation_source', 'online')
            ->whereIn('status', [ReservationStatus::Approved->value, ReservationStatus::Completed->value])
            ->distinct()
            ->pluck('user_id');
        $repeatGuests = $onlineUserIds->isEmpty() ? 0 : Reservation::query()
            ->whereIn('user_id', $onlineUserIds)
            ->where('reservation_source', 'online')
            ->whereIn('status', [ReservationStatus::Approved->value, ReservationStatus::Completed->value])
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('count(*) >= 2')
            ->get()
            ->count();

        $reviewQuery = GuestReview::query();
        $this->applyDateFilter($reviewQuery, 'guest_reviews.created_at', $filters->startDate, $filters->endDate);
        if ($filters->facilityType || $filters->source) {
            $reviewQuery->whereHas('reservation', function (Builder $reservationQuery) use ($filters) {
                $this->applyReservationDimensions($reservationQuery, $filters->facilityType, $filters->source);
            });
        }

        return [
            'total' => $total,
            'cancelled' => $cancelled,
            'cancellation_rate' => $total > 0 ? round(($cancelled / $total) * 100, 1) : null,
            'average_lead_time' => $leadTimes->isNotEmpty() ? round((float) $leadTimes->average(), 1) : null,
            'booking_windows' => $windows,
            'registered_guests' => $onlineUserIds->count(),
            'repeat_guests' => $repeatGuests,
            'repeat_guest_rate' => $onlineUserIds->count() > 0 ? round(($repeatGuests / $onlineUserIds->count()) * 100, 1) : null,
            'average_rating' => $reviewQuery->exists() ? round((float) $reviewQuery->avg('rating'), 1) : null,
            'review_count' => $reviewQuery->count(),
        ];
    }

    /**
     * Where demand concentrates across the year, and what is already on the books
     * for the months ahead.
     *
     * The window is always the twelve whole months ending with the current one:
     * a season cannot be read from an arbitrary date range, and a resort that
     * filters to one week would otherwise see every week as its peak. The service
     * type and source filters still apply, so "peak month for cottages" works.
     *
     * A month counts by when guests actually stay (check-in), not when they booked,
     * because that is what staffing, stock, and pricing decisions turn on.
     *
     * @return array<string, mixed>
     */
    public function getSeasonalityReport(ReportFilters $filters): array
    {
        $reportNow = ResortTime::now();
        $windowStart = $reportNow->copy()->startOfMonth()->subMonths(11);
        $windowEnd = $reportNow->copy()->endOfMonth();

        $query = Reservation::query()->with('payments');
        $this->applyReservationDimensions($query, $filters->facilityType, $filters->source);
        // Only stays that are actually going to happen: a cancelled, rejected,
        // expired, or rescheduled booking never puts a guest on the property, and
        // a pending request is not yet a commitment. Including them would move the
        // peak month for reasons that have nothing to do with demand.
        $query->whereIn('status', [ReservationStatus::Approved->value, ReservationStatus::Completed->value])
            ->where('check_in', '<=', $windowEnd)
            ->where('check_out', '>', $windowStart);

        $stays = $query->get([
            'id',
            'reservation_code',
            'facility_id',
            'status',
            'check_in',
            'check_out',
            'guest_count',
            'total_price',
            'created_at',
        ]);

        $months = [];
        for ($offset = 0; $offset < 12; $offset++) {
            $month = $windowStart->copy()->addMonths($offset);
            $key = $month->format('Y-m');
            $months[$key] = [
                'key' => $key,
                'label' => $month->format('M Y'),
                'short_label' => $month->format('M'),
                'is_current' => $month->format('Y-m') === $reportNow->format('Y-m'),
                'parties' => 0,
                'guests' => 0,
                'nights' => 0,
                'booking_value' => 0.0,
            ];
        }

        $weekend = ['parties' => 0, 'guests' => 0];
        $weekday = ['parties' => 0, 'guests' => 0];

        foreach ($stays as $stay) {
            $key = $stay->check_in->format('Y-m');
            if (! isset($months[$key])) {
                continue;
            }

            $nights = max(1, (int) $stay->check_in->copy()->startOfDay()
                ->diffInDays($stay->check_out->copy()->startOfDay()));

            $months[$key]['parties']++;
            $months[$key]['guests'] += (int) $stay->guest_count;
            $months[$key]['nights'] += $nights;
            $months[$key]['booking_value'] += (float) $stay->total_price;

            // Philippines resort demand lands on Friday to Sunday. The split is
            // labelled in the view so nobody reads it as a labour-law definition.
            if (in_array((int) $stay->check_in->dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY, Carbon::SUNDAY], true)) {
                $weekend['parties']++;
                $weekend['guests'] += (int) $stay->guest_count;
            } else {
                $weekday['parties']++;
                $weekday['guests'] += (int) $stay->guest_count;
            }
        }

        $series = array_values($months);
        $withDemand = collect($series)->filter(fn (array $month): bool => $month['parties'] > 0);

        $peak = $withDemand->sortByDesc('guests')->first();
        $quietest = $withDemand->sortBy('guests')->first();
        $totalGuests = $withDemand->sum('guests');
        $averageGuests = $withDemand->isNotEmpty()
            ? round($totalGuests / $withDemand->count(), 1)
            : null;

        // Forward demand: approved stays that have not happened yet, by month.
        $onBooksQuery = Reservation::query()
            ->where('status', ReservationStatus::Approved->value)
            ->where('check_in', '>', $reportNow)
            ->orderBy('check_in');
        $this->applyReservationDimensions($onBooksQuery, $filters->facilityType, $filters->source);

        $onBooks = $onBooksQuery->get(['check_in', 'guest_count'])
            ->groupBy(fn (Reservation $reservation): string => $reservation->check_in->format('Y-m'))
            ->map(fn (Collection $group, string $key): array => [
                'key' => $key,
                'label' => Carbon::parse($key.'-01')->format('M Y'),
                'parties' => $group->count(),
                'guests' => (int) $group->sum('guest_count'),
            ])
            ->sortKeys()
            ->take(3)
            ->values()
            ->all();

        return [
            'window_label' => $windowStart->format('M Y').' – '.$windowEnd->format('M Y'),
            'is_rolling' => true,
            'series' => $series,
            'peak' => $peak,
            'quietest' => $quietest,
            'average_guests' => $averageGuests,
            'peak_lift' => ($peak && $averageGuests) ? round((($peak['guests'] - $averageGuests) / $averageGuests) * 100) : null,
            'weekend' => $weekend,
            'weekday' => $weekday,
            'weekend_share' => ($weekend['parties'] + $weekday['parties']) > 0
                ? round(($weekend['parties'] / ($weekend['parties'] + $weekday['parties'])) * 100, 1)
                : null,
            'confirmed_parties' => $stays->count(),
            'confirmed_guests' => $stays->sum(fn (Reservation $reservation): int => (int) $reservation->guest_count),
            'on_books' => $onBooks,
        ];
    }

    /**
     * Who still owes the 50% down payment, and who is already past the deadline.
     *
     * Only live bookings count. A cancelled or rejected stay has no deposit
     * obligation left, and a completed stay has already settled at the desk, so
     * including either would flatter the collection rate.
     *
     * Reading of the deposit follows the same rule the guest portal enforces:
     * `deposit_required` when it is set, otherwise half of the booking total.
     *
     * @return array<string, mixed>
     */
    public function getDepositHealth(ReportFilters $filters): array
    {
        $reportNow = ResortTime::now();

        $query = Reservation::query()->with('facility')->withPaymentAllocation();
        $this->applyReservationDimensions($query, $filters->facilityType, $filters->source);

        // Approval is what raises the deposit, so the approval date is the honest
        // basis for "deposits raised in this period" — a request still sitting in
        // Review pending owes nothing yet and is not this report's business.
        $this->applyDateFilter($query, 'reservations.approved_at', $filters->startDate, $filters->endDate);

        $bookings = $query
            ->where('status', ReservationStatus::Approved->value)
            ->orderBy('deposit_due_at')
            ->get();

        $expected = 0.0;
        $collected = 0.0;
        $awaitingVerification = 0.0;
        $met = 0;
        $short = 0;
        $overdue = 0;
        $unverified = 0;
        $outstanding = [];

        foreach ($bookings as $booking) {
            $required = $booking->requiredDepositAmount();
            $paid = $booking->getVerifiedTotalPaid();
            $pending = $booking->getPendingVerificationTotal();
            $gap = max(0.0, round($required - $paid, 2));

            $expected += $required;
            $collected += $paid;
            $awaitingVerification += $pending;

            if ($gap <= 0) {
                $met++;

                continue;
            }

            $short++;
            if ($booking->deposit_due_at?->isPast()) {
                $overdue++;
            }
            if ($pending > 0) {
                $unverified++;
            }

            $outstanding[] = [
                'reservation_id' => $booking->id,
                'reservation_code' => $booking->reservation_code,
                'guest_name' => $booking->guestName(),
                'facility_name' => $booking->facility?->name ?? 'Unassigned',
                'guest_phone' => $booking->guest_phone,
                'guest_email' => $booking->guest_email,
                'status_label' => $booking->status->label(),
                'check_in' => $booking->check_in,
                'deposit_due_at' => $booking->deposit_due_at,
                'is_overdue' => (bool) $booking->deposit_due_at?->isPast(),
                'required' => round($required, 2),
                'verified' => round($paid, 2),
                'pending_verification' => round($pending, 2),
                'short' => $gap,
            ];
        }

        // The urgent list is what a front desk works through: money past its
        // deadline first, then whoever is furthest from the half they owe.
        usort($outstanding, function (array $a, array $b): int {
            if ($a['is_overdue'] !== $b['is_overdue']) {
                return $a['is_overdue'] ? -1 : 1;
            }

            return $b['short'] <=> $a['short'];
        });

        return [
            'bookings' => $bookings->count(),
            'deposit_expected' => round($expected, 2),
            'deposit_collected' => round($collected, 2),
            'awaiting_verification' => round($awaitingVerification, 2),
            'collection_rate' => $expected > 0 ? round(($collected / $expected) * 100, 1) : null,
            'met' => $met,
            'short' => $short,
            'overdue' => $overdue,
            'unverified' => $unverified,
            'outstanding_count' => count($outstanding),
            'outstanding_total' => round(array_sum(array_column($outstanding, 'short')), 2),
            'outstanding' => $outstanding,
            'as_of' => $reportNow,
        ];
    }

    /** @return array<string, mixed> */
    public function getRoomPerformance(ReportFilters $filters): array
    {
        [$from, $to, $usesToday] = $filters->roomPeriod();
        $reportNow = ResortTime::now();
        $applicable = $filters->facilityType === null || $filters->facilityType === FacilityType::Room->value;
        $roomQuery = Facility::query()
            ->whereHas('facilityType', fn (Builder $query) => $query->where('slug', FacilityType::Room->value))
            ->where('is_active', true);
        $totalRooms = (clone $roomQuery)->count();
        $availableRooms = (clone $roomQuery)->where('status', FacilityStatus::Available->value)->count();
        $periodDays = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        $availableRoomNights = $applicable ? $availableRooms * $periodDays : 0;

        $reservationQuery = Reservation::query()
            ->whereHas('facility.facilityType', fn (Builder $query) => $query->where('slug', FacilityType::Room->value))
            ->where('check_in', '<', $to->copy()->addDay()->startOfDay())
            ->where('check_out', '>', $from->copy()->startOfDay());
        if ($filters->source) {
            $reservationQuery->where('reservation_source', $filters->source);
        }

        $actualRoomNights = 0;
        $earnedAccommodation = 0.0;
        foreach ((clone $reservationQuery)->whereNotNull('checked_in_at')->get() as $reservation) {
            $actualEnd = $reservation->checked_out_at
                ?? ($reportNow->lt($reservation->occupancyEnd()) ? $reportNow->copy() : $reservation->occupancyEnd());
            $selectedNights = $this->overlapNights($reservation->checked_in_at, $actualEnd, $from, $to);
            if ($selectedNights === 0) {
                continue;
            }
            $scheduledNights = max(1, $this->overlapNights(
                $reservation->occupancyStart(),
                $reservation->occupancyEnd(),
                $reservation->occupancyStart(),
                $reservation->occupancyEnd()
            ));
            $actualRoomNights += $selectedNights;
            $earnedAccommodation += ((float) $reservation->base_price / $scheduledNights) * $selectedNights;
        }

        $onBooksRoomNights = 0;
        foreach ((clone $reservationQuery)
            ->where('status', ReservationStatus::Approved->value)
            ->whereNull('checked_in_at')
            ->where('check_out', '>', $reportNow)
            ->get() as $reservation) {
            $onBooksRoomNights += $this->overlapNights(
                $reservation->occupancyStart(),
                $reservation->occupancyEnd(),
                $from,
                $to
            );
        }

        return [
            'applicable' => $applicable,
            'period_start' => $from,
            'period_end' => $to,
            'uses_today' => $usesToday,
            'total_rooms' => $totalRooms,
            'available_rooms' => $availableRooms,
            'available_room_nights' => $availableRoomNights,
            'occupied_room_nights' => $actualRoomNights,
            'on_books_room_nights' => $onBooksRoomNights,
            'earned_accommodation_revenue' => round($earnedAccommodation, 2),
            'occupancy' => $availableRoomNights > 0 ? round(($actualRoomNights / $availableRoomNights) * 100, 1) : null,
            'on_books_occupancy' => $availableRoomNights > 0 ? round(($onBooksRoomNights / $availableRoomNights) * 100, 1) : null,
            'adr' => $actualRoomNights > 0 ? round($earnedAccommodation / $actualRoomNights, 2) : null,
            'revpar' => $availableRoomNights > 0 ? round($earnedAccommodation / $availableRoomNights, 2) : null,
            'inventory_complete' => $totalRooms === $availableRooms,
        ];
    }

    /** @return array<string, mixed> */
    public function getOperationsSummary(ReportFilters $filters): array
    {
        $from = ResortTime::today();
        $to = $from->copy()->endOfDay();
        $reportNow = ResortTime::now();
        $base = Reservation::query()->with(['facility', 'user']);
        $this->applyReservationDimensions($base, $filters->facilityType, $filters->source);

        $arrivals = (clone $base)
            ->where('status', ReservationStatus::Approved->value)
            ->whereNull('checked_in_at')
            ->whereBetween('check_in', [$from, $to])
            ->orderBy('check_in')
            ->get();
        $departures = (clone $base)
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->where('check_out', '<=', $to)
            ->orderBy('check_out')
            ->get();
        $missedArrivals = (clone $base)
            ->where('status', ReservationStatus::Approved->value)
            ->whereNull('checked_in_at')
            ->where('check_out', '<', $reportNow)
            ->orderBy('check_out')
            ->get();

        $arrivalPerformance = (clone $base)->whereNotNull('checked_in_at');
        $this->applyDateFilter($arrivalPerformance, 'check_in', $filters->startDate, $filters->endDate);
        $arrivalCount = (clone $arrivalPerformance)->count();
        $onTimeArrivals = (clone $arrivalPerformance)->whereColumn('checked_in_at', '<=', 'check_in')->count();
        $departurePerformance = (clone $base)->whereNotNull('checked_out_at');
        $this->applyDateFilter($departurePerformance, 'check_out', $filters->startDate, $filters->endDate);
        $departureCount = (clone $departurePerformance)->count();
        $onTimeDepartures = (clone $departurePerformance)->whereColumn('checked_out_at', '<=', 'check_out')->count();

        $maintenance = Facility::query()
            ->with('facilityType')
            ->whereIn('status', [FacilityStatus::Maintenance->value, FacilityStatus::Unavailable->value])
            ->when($filters->facilityType, fn (Builder $query, string $type) => $query->whereHas('facilityType', fn (Builder $typeQuery) => $typeQuery->where('slug', $type)))
            ->orderBy('name')
            ->get();

        return [
            'arrivals' => $arrivals,
            'departures' => $departures,
            'missed_arrivals' => $missedArrivals,
            'maintenance_facilities' => $maintenance,
            'pending_reservations' => (clone $base)->where('status', ReservationStatus::Pending->value)->count(),
            'pending_payments' => Payment::query()->where('status', PaymentStatus::Pending->value)
                ->when($filters->facilityType || $filters->source, function (Builder $query) use ($filters) {
                    $this->applyPaymentDimensions($query, $filters->facilityType, $filters->source);
                })->count(),
            'on_time_arrival_rate' => $arrivalCount > 0 ? round(($onTimeArrivals / $arrivalCount) * 100, 1) : null,
            'on_time_departure_rate' => $departureCount > 0 ? round(($onTimeDepartures / $departureCount) * 100, 1) : null,
        ];
    }

    /** @return array<string, mixed> */
    public function getReportingAlerts(ReportFilters $filters): array
    {
        $balanceQuery = Reservation::query()
            ->with(['facility'])
            ->withSum(['payments as verified_paid_total' => fn (Builder $query) => $query->where('status', PaymentStatus::Verified->value)], 'amount')
            ->where(function (Builder $query) {
                $query->where('status', ReservationStatus::Completed->value)->orWhereNotNull('checked_out_at');
            });
        $this->applyReservationDimensions($balanceQuery, $filters->facilityType, $filters->source);
        $balanceAlerts = $balanceQuery->get()
            ->filter(fn (Reservation $reservation): bool => (float) $reservation->total_price > (float) ($reservation->verified_paid_total ?? 0))
            ->take(8)
            ->values();

        $duplicateReferences = Payment::query()
            ->join('reservations', 'payments.reservation_id', '=', 'reservations.id')
            ->whereNotNull('reference_number')
            ->where('payments.reference_number', '<>', '');
        $this->applyDateFilter($duplicateReferences, 'payments.payment_date', $filters->startDate, $filters->endDate);
        if ($filters->facilityType) {
            $duplicateReferences->whereHas('reservation.facility.facilityType', fn (Builder $query) => $query->where('slug', $filters->facilityType));
        }
        if ($filters->source) {
            $duplicateReferences->where('reservations.reservation_source', $filters->source);
        }
        $duplicateReferences = $duplicateReferences
            ->select('payments.reference_number', DB::raw('count(*) as count'))
            ->groupBy('payments.reference_number')
            ->havingRaw('count(*) > 1')
            ->orderByDesc('count')
            ->limit(8)
            ->get();
        $verifiedCancelled = Payment::query()
            ->with('reservation')
            ->where('status', PaymentStatus::Verified->value)
            ->whereHas('reservation', function (Builder $query) use ($filters) {
                $query->whereIn('status', [ReservationStatus::Cancelled->value, ReservationStatus::Rejected->value, ReservationStatus::Expired->value]);
                $this->applyReservationDimensions($query, $filters->facilityType, $filters->source);
            })
            ->limit(8)
            ->get();
        $unclassifiedQuery = Reservation::query()
            ->where('additional_charges', '>', 0)
            ->whereIn('status', [ReservationStatus::Approved->value, ReservationStatus::Completed->value]);
        $this->applyDateFilter($unclassifiedQuery, 'reservations.created_at', $filters->startDate, $filters->endDate);
        $this->applyReservationDimensions($unclassifiedQuery, $filters->facilityType, $filters->source);

        return [
            'outstanding_completed' => $balanceAlerts,
            'duplicate_references' => $duplicateReferences,
            'verified_cancelled' => $verifiedCancelled,
            'unclassified_ancillary_count' => $unclassifiedQuery->count(),
            'total' => $balanceAlerts->count() + $duplicateReferences->count() + $verifiedCancelled->count() + $unclassifiedQuery->count(),
        ];
    }

    /** @return array<string, array{label: string, definition: string, basis: string, completeness: string}> */
    public function metricDefinitions(): array
    {
        return [
            'booking_value' => ['label' => 'Booking value', 'definition' => 'Accepted reservation totals created in the selected period.', 'basis' => 'Reservation creation date', 'completeness' => 'Complete for recorded reservations'],
            'earned_revenue' => ['label' => 'Earned operational revenue', 'definition' => 'Completed or checked-out service value in the selected period.', 'basis' => 'Scheduled service check out date', 'completeness' => 'Depends on staff completing check-out'],
            'cash_collected' => ['label' => 'Cash collected', 'definition' => 'Payments whose status is Verified.', 'basis' => 'Recorded payment date', 'completeness' => 'Complete for verified payments entered in the system'],
            'outstanding_balance' => ['label' => 'Outstanding balance', 'definition' => 'Selected reservation totals less verified payments, never below zero.', 'basis' => 'Reservation creation date', 'completeness' => 'Depends on payment verification'],
            'occupancy' => ['label' => 'Actual room occupancy', 'definition' => 'Actual occupied room nights divided by available room nights.', 'basis' => 'Actual check-in and check-out timestamps', 'completeness' => 'Requires staff check-in/out and has no historical room-status periods'],
            'adr' => ['label' => 'Average Room Rate (ADR/ARR)', 'definition' => 'Earned room accommodation value divided by actual occupied room nights.', 'basis' => 'Actual occupied room nights', 'completeness' => 'Ancillary charges excluded'],
            'revpar' => ['label' => 'RevPAR', 'definition' => 'Earned room accommodation value divided by available room nights.', 'basis' => 'Actual occupied room nights and current inventory status', 'completeness' => 'Historical out-of-service periods are not yet tracked'],
        ];
    }

    private function overlapNights(Carbon $start, Carbon $end, Carbon $rangeStart, Carbon $rangeEnd): int
    {
        $clippedStart = $start->copy()->max($rangeStart);
        $exclusiveRangeEnd = $rangeEnd->copy()->endOfDay();
        $clippedEnd = $end->copy()->min($exclusiveRangeEnd);
        if ($clippedEnd->lte($clippedStart)) {
            return 0;
        }

        return max(1, $clippedStart->copy()->startOfDay()->diffInDays($clippedEnd->copy()->startOfDay()));
    }

    /** @param Builder<Reservation> $query */
    private function applyReservationDimensions(Builder $query, ?string $facilityType, ?string $source): void
    {
        if ($facilityType) {
            $query->whereHas('facility.facilityType', fn (Builder $facilityQuery) => $facilityQuery->where('slug', $facilityType));
        }
        if ($source) {
            $query->where('reservations.reservation_source', $source);
        }
    }

    /** @param Builder<Payment> $query */
    private function applyPaymentDimensions(Builder $query, ?string $facilityType, ?string $source): void
    {
        if (! $facilityType && ! $source) {
            return;
        }
        $query->whereHas('reservation', function (Builder $reservationQuery) use ($facilityType, $source) {
            $this->applyReservationDimensions($reservationQuery, $facilityType, $source);
        });
    }

    /**
     * Helper to safely apply date filters.
     *
     * @param  Builder<Model>  $query
     */
    private function applyDateFilter(Builder $query, string $column, ?Carbon $startDate, ?Carbon $endDate): void
    {
        if ($startDate) {
            $start = $startDate->copy()->startOfDay();
            if (str_ends_with($column, 'created_at')) {
                $start->timezone(config('app.timezone'));
            }
            $query->where($column, '>=', $start);
        }

        if ($endDate) {
            // Need to handle cloned instances to not modify original if passed by ref
            $end = clone $endDate;
            $end->endOfDay();
            if (str_ends_with($column, 'created_at')) {
                $end->timezone(config('app.timezone'));
            }
            $query->where($column, '<=', $end);
        }
    }
}
