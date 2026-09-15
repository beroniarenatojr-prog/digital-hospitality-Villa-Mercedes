<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\Admin\ReportingService;
use App\Support\LayoutBadges;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly ReportingService $reportingService
    ) {}

    public function index(Request $request): View
    {
        $today = now();

        // Keep the first screen operational: staff should immediately see the
        // work that needs a decision, today’s arrivals, and the next stays.
        $pendingReservationCount = LayoutBadges::pendingReservationCount();

        $pendingPaymentCount = Payment::query()
            ->where('status', PaymentStatus::Pending->value)
            ->count();

        $arrivalsToday = Reservation::query()
            ->with(['user', 'facility'])
            ->arrivingOn($today)
            ->get();

        $upcomingReservations = Reservation::query()
            ->with(['user', 'facility'])
            ->whereIn('status', Reservation::blockingStatuses())
            ->whereBetween('check_in', [
                $today->copy()->addDay()->startOfDay(),
                $today->copy()->addDays(7)->endOfDay(),
            ])
            ->orderBy('check_in')
            ->take(5)
            ->get();

        // Recent activity remains useful, but sits below the live queues.
        $recentReservations = Reservation::with(['user', 'facility'])
            ->latest()
            ->take(15)
            ->get();

        $occupancyByType = $this->reportingService->getOccupancyByType($today);
        $weeklyRevenue = $this->reportingService->getVerifiedRevenueTrend('day', $today->copy()->subDays(6), $today);
        $monthlyRevenue = $this->reportingService->getVerifiedRevenueTrend(
            'month',
            $today->copy()->subMonths(11)->startOfMonth(),
            $today
        );

        $recentPayments = Payment::with(['reservation.user'])
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', compact(
            'pendingReservationCount',
            'pendingPaymentCount',
            'arrivalsToday',
            'upcomingReservations',
            'occupancyByType',
            'weeklyRevenue',
            'monthlyRevenue',
            'recentReservations',
            'recentPayments'
        ));
    }
}
