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
     * Get booking trends over time.
     */
    public function getBookingTrends(string $grouping = 'month', ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = Reservation::query();
        $this->applyDateFilter($query, 'reservations.created_at', $startDate, $endDate);

        $driver = DB::connection()->getDriverName();
        $selectRaw = '';

        if ($driver === 'sqlite') {
            if ($grouping === 'quarter') {
                $selectRaw = "strftime('%Y', created_at) || '-Q' || ((cast(strftime('%m', created_at) as integer) + 2) / 3) as period";
            } else {
                // Default to month
                $selectRaw = "strftime('%Y-%m', created_at) as period";
            }
        } else {
            if ($grouping === 'quarter') {
                $selectRaw = "CONCAT(YEAR(created_at), '-Q', QUARTER(created_at)) as period";
            } else {
                // Default to month
                $selectRaw = "DATE_FORMAT(created_at, '%Y-%m') as period";
            }
        }

        return $query->selectRaw($selectRaw)
            ->selectRaw('count(id) as count')
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->pluck('count', 'period')
            ->toArray();
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

        $payQuery = Payment::query()->where('status', PaymentStatus::Verified->value);
        $this->applyDateFilter($payQuery, 'payments.created_at', $startDate, $endDate);

        $verifiedRevenue = $payQuery->sum('amount');

        return [
            'reservation_value' => (float) $reservationValue,
            'verified_payment_revenue' => (float) $verifiedRevenue,
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
     * Helper to safely apply date filters.
     */
    private function applyDateFilter($query, string $column, ?Carbon $startDate, ?Carbon $endDate): void
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
