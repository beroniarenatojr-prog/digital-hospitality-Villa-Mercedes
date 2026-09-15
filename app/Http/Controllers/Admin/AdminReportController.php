<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Services\Admin\ReportingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportController extends Controller
{
    public function __construct(
        private readonly ReportingService $service
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->resolveFilters($request);

        return view('admin.reports.index', array_merge($filters, $this->reportData($filters)));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = $this->resolveFilters($request);
        $data = $this->reportData($filters);

        $filename = 'villa-mercedes-reservations-report-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($filters, $data) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Villa Mercedes — Reservations Report']);
            fputcsv($handle, ['Generated at', now()->toDateTimeString()]);
            fputcsv($handle, [
                'Date from',
                $filters['startDate']?->format('Y-m-d') ?? 'All',
            ]);
            fputcsv($handle, [
                'Date until',
                $filters['endDate']?->format('Y-m-d') ?? 'All',
            ]);
            fputcsv($handle, ['Trends grouping', ucfirst($filters['grouping'])]);
            fputcsv($handle, []);

            fputcsv($handle, ['Summary']);
            fputcsv($handle, ['Metric', 'Value']);
            fputcsv($handle, ['Total Reservations', $data['bookingSummary']['total']]);
            fputcsv($handle, ['Reservation Value', number_format((float) $data['revenueSummary']['reservation_value'], 2, '.', '')]);
            fputcsv($handle, ['Verified Payment Revenue', number_format((float) $data['revenueSummary']['verified_payment_revenue'], 2, '.', '')]);
            fputcsv($handle, ['Outstanding Balance', number_format((float) $data['revenueSummary']['outstanding_balance'], 2, '.', '')]);
            fputcsv($handle, ['Total Payments', $data['paymentSummary']['total_payments']]);
            fputcsv($handle, []);

            fputcsv($handle, ['Reservation Trends ('.ucfirst($filters['grouping']).')']);
            fputcsv($handle, ['Period', 'Reservations']);
            foreach ($data['bookingTrends'] as $point) {
                fputcsv($handle, [$point['period'], $point['value']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Reservations by Status']);
            fputcsv($handle, ['Status', 'Count']);
            foreach (ReservationStatus::cases() as $status) {
                fputcsv($handle, [
                    $status->label(),
                    $data['bookingSummary']['by_status'][$status->value] ?? 0,
                ]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Reservations by Source']);
            fputcsv($handle, ['Source', 'Count']);
            foreach ($data['bookingSummary']['by_source'] as $source => $count) {
                fputcsv($handle, [str_replace('_', ' ', (string) $source), $count]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Most Reserved Services']);
            fputcsv($handle, ['Service', 'Count']);
            foreach ($data['popularServices'] as $name => $count) {
                fputcsv($handle, [$name, $count]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Package Popularity']);
            fputcsv($handle, ['Package', 'Count', 'Share %']);
            foreach ($data['packagePopularity']['breakdown'] as $package) {
                fputcsv($handle, [$package['name'], $package['count'], $package['share_percentage']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Payment Status Breakdown']);
            fputcsv($handle, ['Status', 'Count', 'Amount']);
            foreach (PaymentStatus::cases() as $status) {
                $row = $data['paymentSummary']['by_status'][$status->value] ?? ['count' => 0, 'total_amount' => 0];
                fputcsv($handle, [
                    $status->label(),
                    $row['count'],
                    number_format((float) $row['total_amount'], 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request): View
    {
        $filters = $this->resolveFilters($request);

        return view('admin.reports.print', array_merge($filters, $this->reportData($filters)));
    }

    /**
     * @return array{startDate: ?Carbon, endDate: ?Carbon, grouping: string}
     */
    private function resolveFilters(Request $request): array
    {
        $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'grouping' => ['nullable', 'in:week,month,year'],
        ]);

        $startDate = $request->filled('start_date') ? Carbon::parse($request->start_date) : null;
        $endDate = $request->filled('end_date') ? Carbon::parse($request->end_date) : null;

        $grouping = $request->input('grouping', 'month');
        if (! in_array($grouping, ['week', 'month', 'year'], true)) {
            $grouping = 'month';
        }

        return compact('startDate', 'endDate', 'grouping');
    }

    /**
     * @param  array{startDate: ?Carbon, endDate: ?Carbon, grouping: string}  $filters
     * @return array{
     *     bookingSummary: array,
     *     bookingTrends: array,
     *     popularServices: array,
     *     packagePopularity: array,
     *     revenueSummary: array,
     *     paymentSummary: array
     * }
     */
    private function reportData(array $filters): array
    {
        $data = [
            'bookingSummary' => $this->service->getBookingSummary($filters['startDate'], $filters['endDate']),
            'bookingTrends' => $this->service->getBookingTrends($filters['grouping'], $filters['startDate'], $filters['endDate']),
            'popularServices' => $this->service->getPopularServices($filters['startDate'], $filters['endDate']),
            'packagePopularity' => $this->service->getPackagePopularity($filters['startDate'], $filters['endDate']),
            'revenueSummary' => $this->service->getRevenueSummary($filters['startDate'], $filters['endDate']),
            'paymentSummary' => $this->service->getPaymentSummary($filters['startDate'], $filters['endDate']),
        ];

        $data['comparison'] = $this->comparisonData($filters);

        return $data;
    }

    /**
     * Compare an explicit date range with the immediately preceding range of
     * the same length. All-time reports intentionally have no comparison.
     *
     * @param  array{startDate: ?Carbon, endDate: ?Carbon, grouping: string}  $filters
     */
    private function comparisonData(array $filters): ?array
    {
        if (! $filters['startDate'] || ! $filters['endDate']) {
            return null;
        }

        $days = (int) $filters['startDate']->copy()->startOfDay()
            ->diffInDays($filters['endDate']->copy()->startOfDay()) + 1;
        $previousEnd = $filters['startDate']->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($days - 1);

        return [
            'startDate' => $previousStart,
            'endDate' => $previousEnd,
            'bookingSummary' => $this->service->getBookingSummary($previousStart, $previousEnd),
            'revenueSummary' => $this->service->getRevenueSummary($previousStart, $previousEnd),
        ];
    }
}
