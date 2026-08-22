<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\Admin\ReportingService;
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
        $startDate = $today->copy()->startOfMonth();
        $endDate = $today->copy()->endOfMonth();

        $bookingSummary = $this->reportingService->getBookingSummary($startDate, $endDate);
        $paymentSummary = $this->reportingService->getPaymentSummary($startDate, $endDate);
        
        // Facilities
        $facilityStatuses = $this->reportingService->getFacilityStatusSummary();
        
        // Customers
        $customerCount = $this->reportingService->getCustomerCount();

        // Recent items
        $recentReservations = Reservation::with(['user', 'facility'])
            ->latest()
            ->take(5)
            ->get();
            
        $recentPayments = Payment::with(['reservation.user'])
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', compact(
            'bookingSummary',
            'paymentSummary',
            'facilityStatuses',
            'customerCount',
            'recentReservations',
            'recentPayments'
        ));
    }
}
