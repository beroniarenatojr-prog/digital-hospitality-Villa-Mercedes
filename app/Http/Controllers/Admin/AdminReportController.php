<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FacilityType;
use App\Enums\PaymentStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Services\Admin\ReportingService;
use App\Services\Admin\ManagementReports;
use App\Support\Admin\ReportFilters;
use App\Support\ResortTime;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportController extends Controller
{
    public function __construct(private readonly ReportingService $service, private readonly ManagementReports $management) {}

    public function checkedIn(): View
    {
        return view('admin.checked-in.index', [
            'currentOccupancy' => $this->service->getCurrentOccupancy(),
            'reportQuery' => ['ops' => 'occupancy', 'section' => 'occupancy'],
        ]);
    }

    public function operational(Request $request): View|RedirectResponse
    {
        $filters = $this->resolveFilters($request);

        // The operational screen has its own sub-view switch. It deliberately does
        // not reuse `view`, which the main report page already owns and validates
        // against executive/revenue/operations.
        $params = $request->validate([
            'ops' => ['nullable', 'in:registry,occupancy,action-lists,availability'],
            'pax' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $opsView = $params['ops'] ?? 'registry';
        if ($opsView === 'availability') {
            return redirect()->route('admin.decision-board');
        }
        if ($opsView === 'occupancy') {
            return redirect()->route('admin.checked-in.index');
        }
        if ($opsView === 'action-lists') {
            $filters = new ReportFilters(null, null);
        }

        return view('admin.reports.operational', array_merge(
            $this->viewFilters($filters),
            $this->operationalData($filters),
            ['opsView' => $opsView]
        ));
    }

    /**
     * Shared operational payload for the screen and its printable version.
     *
     * @return array<string, mixed>
     */
    private function operationalData(ReportFilters $filters): array
    {
        return [
            'registry' => $this->service->buildCheckInRegistry(
                $filters->startDate,
                $filters->endDate,
                $filters->facilityType,
                $filters->source,
            ),
            'currentOccupancy' => $this->service->getCurrentOccupancy($filters->facilityType),
            'operationalPositions' => $this->service->getOperationalPositions(),
            'operations' => $this->service->getOperationsSummary($filters),
        ];
    }

    public function index(Request $request): View|RedirectResponse
    {
        $filters = $this->resolveFilters($request);

        // Operations used to be a fourth tab on this page. It now has its own
        // screen, so an old bookmark or a stale link is sent there rather than
        // showing a second, weaker copy of the same queues.
        if ($filters->view === 'operations') {
            // Keep the range and dimensions; drop `view`, which means nothing on
            // the screen this now points at.
            $query = $request->query();
            unset($query['view']);

            return redirect()->route('admin.reports.operational', $query);
        }

        $filters = $this->managementFilters($filters);

        return view('admin.reports.index', array_merge($this->viewFilters($filters), ['report' => $this->management->build($filters)]));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = $this->managementFilters($this->resolveFilters($request));
        $report = $this->management->build($filters);
        return response()->streamDownload(function () use ($filters, $report) {
            $handle = fopen('php://output', 'w');
            // Protect spreadsheet consumers from formulas in guest-entered text.
            $write = static function (array $cells) use ($handle): void {
                fputcsv($handle, array_map(static fn ($value) => is_string($value) && preg_match('/^[=+@\\-\\t\\r\\n]/', $value) ? "'".$value : $value, $cells));
            };
            $write(['Villa Mercedes', $report['title']]);
            $write(['Date basis', $report['basis']]);
            $write(['From', $filters->startDate?->format('Y-m-d') ?? 'Current snapshot', 'Until', $filters->endDate?->format('Y-m-d') ?? 'Current snapshot']);
            $write(['Service type', $filters->facilityType ?? 'All', 'Source', $filters->source ?? 'All']);
            $write([$report['note']]);
            $write(['What this period shows']);
            foreach ($report['findings'] as $finding) $write([$finding]);
            if ($report['comparison']) {
                $write(['Compared with', $report['comparison']['from'], $report['comparison']['until'], 'Same filters; current recorded statuses']);
            }
            foreach ($report['metrics'] as $metric) {
                $write([$metric['label'], $metric['value'], $metric['note']]);
            }
            foreach ($report['charts'] as $chart) {
                $write([]);
                $write([$chart['title']]);
                $write(['Period / category', $chart['money'] ? 'Amount (PHP)' : 'Reservations']);
                foreach ($chart['rows'] as $row) $write([$row['label'], $row['value']]);
            }
            foreach ($report['tables'] as $table) {
                $write([]);
                $write([$table['title']]);
                $write($table['columns']);
                foreach ($table['rows'] as $row) $write($row['cells']);
            }
            fclose($handle);
        }, 'villa-mercedes-'.$filters->view.'-'.ResortTime::now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportPdf(Request $request): View|RedirectResponse
    {
        if ($request->query('section', $request->query('ops')) === 'availability') {
            return redirect()->route('admin.decision-board');
        }

        $operationalExport = $request->query('view') === 'operations'
            || $request->query('ops') !== null
            || $request->query('section') !== null;

        if (! $operationalExport) {
            $filters = $this->managementFilters($this->resolveFilters($request));
            return view('admin.reports.management-print', array_merge($this->viewFilters($filters), [
                'report' => $this->management->build($filters), 'reportLogo' => $this->reportLogo(),
            ]));
        }

        $exportOptions = $operationalExport
            ? $request->validate([
                'section' => ['nullable', 'in:all,registry,occupancy,action-lists,availability'],
                'period' => ['nullable', 'in:current,month,year,all'],
                'month' => ['nullable', 'required_if:period,month', 'date_format:Y-m'],
                'year' => ['nullable', 'required_if:period,year', 'integer', 'min:2000', 'max:2100'],
                'pax' => ['nullable', 'integer', 'min:1', 'max:500'],
            ])
            : [];

        $filterRequest = $operationalExport
            ? $this->operationalExportRequest($request, $exportOptions)
            : $request;
        $filters = $this->resolveFilters($filterRequest);
        $data = $this->reportData($filters);

        // The operational screen prints its own sub-view, so the printed page
        // mirrors whichever view the staff member was looking at. Anything other
        // than the explicit operational view keeps the older report printout.
        $opsView = in_array($request->query('ops'), ['registry', 'occupancy', 'action-lists', 'availability'], true)
            ? $request->query('ops')
            : null;

        if ($operationalExport || $filters->view === 'operations' || $opsView !== null) {
            $section = $exportOptions['section'] ?? $opsView ?? 'all';
            $sections = $section === 'all'
                ? ['registry', 'action-lists']
                : [$section];

            return view('admin.reports.print', array_merge(
                $this->viewFilters($filters),
                $data,
                $this->operationalData($filters),
                [
                    'opsView' => $section,
                    'operationsSections' => $sections,
                    'operationsPeriod' => $exportOptions['period'] ?? 'current',
                    'reportLogo' => $this->reportLogo(),
                ]
            ));
        }

        return view('admin.reports.print', array_merge(
            $this->viewFilters($filters),
            $data,
            ['reportLogo' => $this->reportLogo()],
        ));
    }

    /** @param array<string, mixed> $options */
    private function operationalExportRequest(Request $request, array $options): Request
    {
        $query = $request->query();
        $period = $options['period'] ?? 'current';

        if ($period === 'month' && isset($options['month'])) {
            $month = Carbon::createFromFormat('Y-m', $options['month'], ResortTime::timezone())->startOfMonth();
            $query['start_date'] = $month->format('Y-m-d');
            $query['end_date'] = $month->copy()->endOfMonth()->format('Y-m-d');
        } elseif ($period === 'year' && isset($options['year'])) {
            $year = Carbon::create((int) $options['year'], 1, 1, 0, 0, 0, ResortTime::timezone());
            $query['start_date'] = $year->format('Y-m-d');
            $query['end_date'] = $year->copy()->endOfYear()->format('Y-m-d');
        } elseif ($period === 'all') {
            unset($query['start_date'], $query['end_date']);
        }

        return Request::create($request->url(), 'GET', $query);
    }

    private function reportLogo(): ?string
    {
        $png = @file_get_contents(public_path('images/logo-villa-mercedes-mark.png'));

        return is_string($png) ? 'data:image/png;base64,'.base64_encode($png) : null;
    }

    private function resolveFilters(Request $request): ReportFilters
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'grouping' => ['nullable', 'in:auto,day,week,month,year'],
            'view' => ['nullable', 'in:executive,revenue,services,balances,operations'],
            'facility_type' => ['nullable', 'in:'.implode(',', FacilityType::values())],
            'source' => ['nullable', 'in:'.implode(',', ReservationSource::values())],
            'channel' => ['nullable', 'in:unspecified'],
            'segment' => ['nullable', 'in:unspecified'],
        ]);

        return new ReportFilters(
            startDate: isset($validated['start_date']) ? Carbon::parse($validated['start_date'], ResortTime::timezone()) : null,
            endDate: isset($validated['end_date']) ? Carbon::parse($validated['end_date'], ResortTime::timezone()) : null,
            grouping: $validated['grouping'] ?? ($request->routeIs('admin.reports.operational') || $request->query('view') === 'operations' ? 'month' : 'auto'),
            view: $validated['view'] ?? 'executive',
            facilityType: $validated['facility_type'] ?? null,
            source: $validated['source'] ?? null,
            channel: $validated['channel'] ?? null,
            segment: $validated['segment'] ?? null,
        );
    }

    private function managementFilters(ReportFilters $filters): ReportFilters
    {
        $snapshot = $filters->view === 'balances';
        $end = $filters->endDate?->copy() ?? ResortTime::today();
        $start = $filters->startDate?->copy() ?? $end->copy()->startOfMonth();
        if (! $snapshot && ($end->lt($start) || $start->diffInDays($end) > 3660)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['end_date' => 'Choose an end date on or after the start, within ten years.']);
        }
        return new ReportFilters(
            $snapshot ? null : $start,
            $snapshot ? null : $end,
            $filters->grouping,
            $filters->view === 'operations' ? 'executive' : $filters->view,
            in_array($filters->view, ['revenue', 'balances'], true) ? null : $filters->facilityType,
            $filters->source,
        );
    }

    /** @return array<string, mixed> */
    private function reportData(ReportFilters $filters): array
    {
        $args = [$filters->startDate, $filters->endDate, $filters->facilityType, $filters->source];
        $data = [
            'bookingSummary' => $this->service->getBookingSummary(...$args),
            'bookingTrends' => $this->service->getBookingTrends($filters->grouping, ...$args),
            'popularServices' => $this->service->getPopularServices(...$args),
            'packagePopularity' => $this->service->getPackagePopularity(...$args),
            'revenueSummary' => $this->service->getRevenueSummary(...$args),
            'paymentSummary' => $this->service->getPaymentSummary(...$args),
        ];
        $data = array_merge($data, $this->service->getDecisionMetrics($filters));

        // Seasonality and deposit health describe the resort's pattern rather than
        // the selected range, so they are built once for the decision-making view
        // instead of being recomputed per tab.
        $data['seasonality'] = $this->service->getSeasonalityReport($filters);
        $data['depositHealth'] = $this->service->getDepositHealth($filters);
        $data['bookingTrends'] = array_map(fn ($row) => ['period' => $row['label'], 'label' => $row['label'], 'value' => $row['count']], $data['charts']['reservations']['rows']);
        $data['comparison'] = $this->comparisonData($filters);

        return $data;
    }

    /** @return array<string, mixed> */
    private function viewFilters(ReportFilters $filters): array
    {
        return [
            'filters' => $filters,
            'startDate' => $filters->startDate,
            'endDate' => $filters->endDate,
            'grouping' => $filters->grouping,
            'reportView' => $filters->view,
            'reportQuery' => $filters->query(),
        ];
    }

    private function comparisonData(ReportFilters $filters): ?array
    {
        if (! $filters->startDate || ! $filters->endDate) {
            return null;
        }

        $days = (int) $filters->startDate->copy()->startOfDay()
            ->diffInDays($filters->endDate->copy()->startOfDay()) + 1;
        $previousEnd = $filters->startDate->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($days - 1);

        return [
            'startDate' => $previousStart,
            'endDate' => $previousEnd,
            'bookingSummary' => $this->service->getBookingSummary($previousStart, $previousEnd, $filters->facilityType, $filters->source),
            'revenueSummary' => $this->service->getRevenueSummary($previousStart, $previousEnd, $filters->facilityType, $filters->source),
        ];
    }

    private function csvMetric(float|int|null $value, string $suffix = ''): string
    {
        return $value === null ? 'Not enough data' : number_format((float) $value, 2, '.', '').$suffix;
    }
}
