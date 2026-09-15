<?php

namespace App\Services\Admin;

use App\Enums\FacilityType;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ReportingService
{
    /**
     * Get booking summary counts (totals, by status, by source).
     */
    public function getBookingSummary(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = Reservation::query();
        $this->applyDateFilter($query, 'reservations.created_at', $startDate, $endDate);

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
    public function getBookingTrends(string $grouping = 'month', ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $grouping = in_array($grouping, ['week', 'month', 'year', 'quarter'], true) ? $grouping : 'month';

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
    public function getPopularServices(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = Reservation::query()
            ->join('facilities', 'reservations.facility_id', '=', 'facilities.id')
            ->join('facility_types', 'facilities.facility_type_id', '=', 'facility_types.id')
            ->whereIn('facility_types.slug', [FacilityType::Room->value, FacilityType::FunctionHall->value]);

        $this->applyDateFilter($query, 'reservations.created_at', $startDate, $endDate);

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
    public function getPackagePopularity(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = Reservation::query()
            ->join('facilities', 'reservations.facility_id', '=', 'facilities.id')
            ->join('facility_types', 'facilities.facility_type_id', '=', 'facility_types.id')
            ->where('facility_types.slug', FacilityType::ResortPackage->value);

        $this->applyDateFilter($query, 'reservations.created_at', $startDate, $endDate);

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
    public function getRevenueSummary(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $resQuery = Reservation::query()
            ->whereNotIn('status', [ReservationStatus::Cancelled->value, ReservationStatus::Rejected->value]);
        $this->applyDateFilter($resQuery, 'reservations.created_at', $startDate, $endDate);

        $reservationValue = $resQuery->sum('total_price');

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
        $this->applyDateFilter($payQuery, 'payments.created_at', $startDate, $endDate);

        $verifiedRevenue = $payQuery->sum('amount');

        return [
            'reservation_value' => (float) $reservationValue,
            'verified_payment_revenue' => (float) $verifiedRevenue,
            'outstanding_balance' => $outstandingBalance,
        ];
    }

    /**
     * Get payment summaries.
     */
    public function getPaymentSummary(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = Payment::query();
        $this->applyDateFilter($query, 'payments.created_at', $startDate, $endDate);

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
        $day = ($day ?? now())->copy();
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
            ->whereRaw('TIMESTAMPADD(HOUR, -COALESCE(early_check_in_hours, 0), check_in) < ?', [$to->toDateTimeString()])
            ->whereRaw('TIMESTAMPADD(HOUR, COALESCE(late_check_out_hours, 0), check_out) > ?', [$from->toDateTimeString()])
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
     * Verified payment totals grouped by day or month, with zero-filled periods.
     *
     * @return list<array{period: string, label: string, value: float}>
     */
    public function getVerifiedRevenueTrend(string $grouping = 'day', ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $endDate = ($endDate ?? now())->copy()->endOfDay();

        if ($grouping === 'month') {
            $startDate = ($startDate ?? $endDate->copy()->subMonths(11)->startOfMonth())->copy()->startOfMonth();
        } else {
            $grouping = 'day';
            $startDate = ($startDate ?? $endDate->copy()->subDays(6)->startOfDay())->copy()->startOfDay();
        }

        $driver = DB::connection()->getDriverName();

        if ($grouping === 'month') {
            $selectRaw = $driver === 'sqlite'
                ? "strftime('%Y-%m', created_at) as period"
                : "DATE_FORMAT(created_at, '%Y-%m') as period";
        } else {
            $selectRaw = $driver === 'sqlite'
                ? "strftime('%Y-%m-%d', created_at) as period"
                : "DATE_FORMAT(created_at, '%Y-%m-%d') as period";
        }

        $totals = Payment::query()
            ->where('status', PaymentStatus::Verified->value)
            ->whereBetween('created_at', [$startDate, $endDate])
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
     * Helper to safely apply date filters.
     *
     * @param  Builder<Model>  $query
     */
    private function applyDateFilter(Builder $query, string $column, ?Carbon $startDate, ?Carbon $endDate): void
    {
        if ($startDate) {
            $query->where($column, '>=', $startDate->startOfDay());
        }

        if ($endDate) {
            // Need to handle cloned instances to not modify original if passed by ref
            $end = clone $endDate;
            $query->where($column, '<=', $end->endOfDay());
        }
    }
}
