<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ReportingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminReportController extends Controller
{
    public function __construct(
        private readonly ReportingService $service
    ) {}

    public function index(Request $request): View
    {
        $startDate = $request->filled('start_date') ? Carbon::parse($request->start_date) : null;
        $endDate = $request->filled('end_date') ? Carbon::parse($request->end_date) : null;

        $grouping = $request->input('grouping', 'month');
        if (!in_array($grouping, ['month', 'quarter'])) {
            $grouping = 'month';
        }

        $bookingSummary = $this->service->getBookingSummary($startDate, $endDate);
        $bookingTrends = $this->service->getBookingTrends($grouping, $startDate, $endDate);
        $popularServices = $this->service->getPopularServices($startDate, $endDate);
        $packagePopularity = $this->service->getPackagePopularity($startDate, $endDate);
        $revenueSummary = $this->service->getRevenueSummary($startDate, $endDate);
        $paymentSummary = $this->service->getPaymentSummary($startDate, $endDate);

        return view('admin.reports.index', compact(
            'startDate',
            'endDate',
            'grouping',
            'bookingSummary',
            'bookingTrends',
            'popularServices',
            'packagePopularity',
            'revenueSummary',
            'paymentSummary'
        ));
    }
}
