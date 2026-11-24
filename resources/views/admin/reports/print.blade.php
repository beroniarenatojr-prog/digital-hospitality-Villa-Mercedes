<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Villa Mercedes — {{ $reportView === 'operations' ? 'Operations Report' : 'Reservations Report' }}</title>
    <style>
        :root {
            color-scheme: light;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Georgia, 'Times New Roman', serif;
            color: #111827;
            background: #fff;
            line-height: 1.45;
        }
        .toolbar {
            position: sticky;
            top: 0;
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            padding: 0.75rem 1.25rem;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }
        .toolbar button,
        .toolbar a {
            appearance: none;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #111827;
            border-radius: 0.375rem;
            padding: 0.5rem 0.85rem;
            font: 600 0.875rem/1.25 system-ui, sans-serif;
            text-decoration: none;
            cursor: pointer;
        }
        .toolbar button.primary {
            background: #166534;
            border-color: #166534;
            color: #fff;
        }
        .sheet {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1.5rem 3rem;
        }
        .brand-header {
            text-align: center;
            border-bottom: 2px solid #623f1f;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }
        .brand-logo {
            width: 76px;
            height: 76px;
            object-fit: contain;
            margin-bottom: 0.45rem;
            padding: 0.65rem;
            background: #623f1f;
        }
        .brand-name { margin: 0; color: #3f2614; font: 700 1.35rem/1.2 Georgia, serif; }
        .brand-details { margin: 0.3rem 0 0; color: #4b5563; font: 0.78rem/1.45 system-ui, sans-serif; }
        h1 {
            margin: 0 0 0.25rem;
            font-size: 1.75rem;
        }
        .meta {
            color: #4b5563;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }
        h2 {
            margin: 1.75rem 0 0.75rem;
            font-size: 1.1rem;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 0.35rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.92rem;
        }
        th, td {
            border-bottom: 1px solid #e5e7eb;
            padding: 0.45rem 0.35rem;
            text-align: left;
            vertical-align: top;
        }
        th { color: #6b7280; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.04em; }
        td.num, th.num { text-align: right; }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
        }
        .kpi {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 0.75rem 0.9rem;
        }
        .kpi .label { color: #6b7280; font-size: 0.8rem; }
        .kpi .value { font-size: 1.25rem; font-weight: 700; margin-top: 0.15rem; }
        .report-section + .report-section { break-before: page; page-break-before: always; }
        @media print {
            .toolbar { display: none !important; }
            .sheet { max-width: none; padding: 0; }
            a { color: inherit; text-decoration: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('admin.reports.index', $reportQuery) }}">Back to reports</a>
        <button type="button" class="primary" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <main class="sheet">
        <header class="brand-header">
            @if ($reportLogo)
                <img src="{{ $reportLogo }}" alt="" class="brand-logo">
            @endif
            <p class="brand-name">{{ config('resort.name') }}</p>
            <p class="brand-details">
                {{ config('resort.location') }}<br>
                Contact: {{ config('resort.phone') }} · {{ config('resort.email') }}
            </p>
        </header>

        {{-- The operational report is its own document, not a section of the
             analytics one: printing the guest registry should not also print
             the revenue charts. --}}
        @if ($reportView === 'operations')
            @include('admin.reports.partials.print-operations')
        @else
            <h1>Reservations report</h1>
            <p class="meta">
                Reservations report generated {{ \App\Support\ResortTime::now()->format('M j, Y g:i A T') }}<br>
                Date from: {{ $startDate?->format('Y-m-d') ?? 'All' }}
                · Date until: {{ $endDate?->format('Y-m-d') ?? 'All' }}
                · View: {{ $reportView === 'revenue' ? 'Financial' : ucfirst($reportView) }}
                · Trends: {{ ucfirst($grouping) }}<br>
                Facility type: {{ $filters->facilityType ? \App\Enums\FacilityType::from($filters->facilityType)->label() : 'All' }}
                · Source: {{ $filters->source ? \App\Enums\ReservationSource::from($filters->source)->label() : 'All' }}
            </p>
            @foreach(['financial', 'departments', 'movement', 'occupancy', 'sources', 'reservations'] as $chartKey)
                @php
                    $chart = $charts[$chartKey];
                @endphp
                <h2>{{ $chart['title'] }}</h2>
                <p class="meta">{{ $chart['status'] }} · {{ $chart['basis'] }}<br>{{ $chart['comparison'] }}<br>{{ $chart['definition'] }}</p>
                <table><thead><tr><th>Period / category</th>@foreach($chart['series'] as $label)<th class="num">{{ $label }}</th>@endforeach</tr></thead><tbody>
                    @foreach($chart['rows'] as $row)<tr><td>{{ $row['label'] }}</td>@foreach($chart['series'] as $key => $label)<td class="num">{{ $chart['money'] ? '₱' : '' }}{{ number_format($row[$key] ?? 0, $chart['money'] ? 2 : 0) }}</td>@endforeach</tr>@endforeach
                </tbody></table>
            @endforeach

            <h2>Current facility status · Partial data</h2>
        <p>Cleaning and inspection are not tracked. Current snapshot; no historical comparison.</p>
        <table><thead><tr><th>Facility</th><th>Type</th><th>Status</th></tr></thead><tbody>@foreach($charts['facility_grid'] as $facility)<tr><td>{{ $facility['label'] }}</td><td>{{ $facility['type'] }}</td><td>{{ $facility['state'] }}</td></tr>@endforeach</tbody></table>
        <h2>Summary</h2>
        <div class="kpi-grid">
            <div class="kpi">
                <div class="label">Total Reservations</div>
                <div class="value">{{ number_format($bookingSummary['total']) }}</div>
            </div>
            <div class="kpi">
                <div class="label">Booking Value</div>
                <div class="value">₱{{ number_format($revenueSummary['booking_value'], 2) }}</div>
            </div>
            <div class="kpi">
                <div class="label">Cash Collected</div>
                <div class="value">₱{{ number_format($revenueSummary['verified_payment_revenue'], 2) }}</div>
            </div>
            <div class="kpi">
                <div class="label">Outstanding Balance</div>
                <div class="value">₱{{ number_format($revenueSummary['outstanding_balance'], 2) }}</div>
            </div>
        </div>

        <p class="meta" style="margin-top:.75rem">
            Booking value uses reservation creation date. Cash collected uses recorded payment date.
            Earned operational revenue uses scheduled check out date and requires a completed or checked-out service.
        </p>

        <h2>Room performance</h2>
        <table>
            <thead><tr><th>Metric</th><th class="num">Value</th><th>Completeness</th></tr></thead>
            <tbody>
                <tr><td>Actual occupancy</td><td class="num">{{ $roomPerformance['occupancy'] === null ? 'Not enough data' : number_format($roomPerformance['occupancy'], 1).'%' }}</td><td>{{ $metricDefinitions['occupancy']['completeness'] }}</td></tr>
                <tr><td>Average Room Rate (ADR/ARR)</td><td class="num">{{ $roomPerformance['adr'] === null ? 'Not enough data' : '₱'.number_format($roomPerformance['adr'], 2) }}</td><td>{{ $metricDefinitions['adr']['completeness'] }}</td></tr>
                <tr><td>RevPAR</td><td class="num">{{ $roomPerformance['revpar'] === null ? 'Not enough data' : '₱'.number_format($roomPerformance['revpar'], 2) }}</td><td>{{ $metricDefinitions['revpar']['completeness'] }}</td></tr>
                <tr><td>On-books room nights</td><td class="num">{{ number_format($roomPerformance['on_books_room_nights']) }}</td><td>Approved future stays; not actual occupancy</td></tr>
            </tbody>
        </table>

        <h2>Peak season and forward demand</h2>
        <p class="meta">
            Demand by month of stay across {{ $seasonality['window_label'] }} (a fixed twelve-month window; the service type
            and source filters apply). Confirmed and completed stays only: pending requests, cancellations, rejections,
            and expiries are excluded.
            @if ($seasonality['peak'])
                Peak: {{ $seasonality['peak']['label'] }} with {{ number_format($seasonality['peak']['guests']) }} guests.
                Quietest: {{ $seasonality['quietest']['label'] }} with {{ number_format($seasonality['quietest']['guests']) }} guests.
            @else
                No stays recorded in the window yet.
            @endif
        </p>
        <table>
            <thead>
                <tr><th>Month</th><th class="num">Parties</th><th class="num">Guests</th><th class="num">Space nights</th><th class="num">Booking value</th></tr>
            </thead>
            <tbody>
                @foreach ($seasonality['series'] as $month)
                    <tr>
                        <td>{{ $month['label'] }}{{ $month['is_current'] ? ' (current)' : '' }}</td>
                        <td class="num">{{ number_format($month['parties']) }}</td>
                        <td class="num">{{ number_format($month['guests']) }}</td>
                        <td class="num">{{ number_format($month['nights']) }}</td>
                        <td class="num">₱{{ number_format($month['booking_value'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="meta">
            Weekend parties (Friday to Sunday): {{ number_format($seasonality['weekend']['parties']) }} ·
            weekday parties: {{ number_format($seasonality['weekday']['parties']) }}
            @if ($seasonality['weekend_share'] !== null)
                · weekend share {{ number_format($seasonality['weekend_share'], 1) }}%
            @endif
        </p>

        @if ($seasonality['on_books'] !== [])
            <h3>Already on the books</h3>
            <table>
                <thead><tr><th>Month</th><th class="num">Parties</th><th class="num">Guests</th></tr></thead>
                <tbody>
                    @foreach ($seasonality['on_books'] as $month)
                        <tr>
                            <td>{{ $month['label'] }}</td>
                            <td class="num">{{ number_format($month['parties']) }}</td>
                            <td class="num">{{ number_format($month['guests']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <h2>50% down payment health</h2>
        <p class="meta">
            {{ number_format($depositHealth['bookings']) }} pending and approved bookings as of
            {{ $depositHealth['as_of']->format('M j, Y g:i A T') }} ·
            ₱{{ number_format($depositHealth['deposit_collected'], 2) }} verified of
            ₱{{ number_format($depositHealth['deposit_expected'], 2) }} required
            @if ($depositHealth['collection_rate'] !== null)
                ({{ number_format($depositHealth['collection_rate'], 1) }}%)
            @endif
            · {{ number_format($depositHealth['met']) }} met the half
            · {{ number_format($depositHealth['short']) }} still short
            · ₱{{ number_format($depositHealth['outstanding_total'], 2) }} outstanding
            · {{ number_format($depositHealth['overdue']) }} past the deadline
            · ₱{{ number_format($depositHealth['awaiting_verification'], 2) }} awaiting verification
            on {{ number_format($depositHealth['unverified']) }} bookings.
        </p>
        <table>
            <thead>
                <tr>
                    <th>Reservation</th><th>Guest</th><th>Space</th><th>Visit</th><th>Deposit due</th>
                    <th class="num">Required</th><th class="num">Verified</th><th class="num">Still needed</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($depositHealth['outstanding'] as $row)
                    <tr>
                        <td>{{ $row['reservation_code'] }}</td>
                        <td>{{ $row['guest_name'] }}</td>
                        <td>{{ $row['facility_name'] }}</td>
                        <td>{{ $row['check_in']->format('M j, Y') }} ({{ $row['status_label'] }})</td>
                        <td>{{ $row['deposit_due_at'] ? $row['deposit_due_at']->format('M j, Y').($row['is_overdue'] ? ' — OVERDUE' : '') : 'Not set' }}</td>
                        <td class="num">₱{{ number_format($row['required'], 2) }}</td>
                        <td class="num">₱{{ number_format($row['verified'], 2) }}</td>
                        <td class="num">₱{{ number_format($row['short'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8">Every pending and approved booking in this period has met its 50% down payment.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Revenue by service department</h2>
        <table>
            <thead><tr><th>Department</th><th class="num">Booking value</th><th class="num">Earned revenue</th><th class="num">Cash collected</th></tr></thead>
            <tbody>
                @foreach($departmentRevenue as $department)
                    <tr>
                        <td>{{ $department['label'] }}</td>
                        @if($department['tracked'])
                            <td class="num">₱{{ number_format($department['booking_value'], 2) }}</td>
                            <td class="num">₱{{ number_format($department['earned_revenue'], 2) }}</td>
                            <td class="num">₱{{ number_format($department['cash_collected'], 2) }}</td>
                        @else
                            <td colspan="3">Not tracked yet</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>Reservation Trends ({{ ucfirst($grouping) }})</h2>
        <table>
            <thead>
                <tr>
                    <th>Period</th>
                    <th class="num">Reservations</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bookingTrends as $point)
                    <tr>
                        <td>{{ $point['period'] }}</td>
                        <td class="num">{{ number_format($point['value']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2">No trend data</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Reservations by Status</h2>
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th class="num">Count</th>
                </tr>
            </thead>
            <tbody>
                @foreach(\App\Enums\ReservationStatus::cases() as $status)
                    <tr>
                        <td>{{ $status->label() }}</td>
                        <td class="num">{{ number_format($bookingSummary['by_status'][$status->value] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>Reservations by Source</h2>
        <table>
            <thead>
                <tr>
                    <th>Source</th>
                    <th class="num">Count</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bookingSummary['by_source'] as $source => $count)
                    <tr>
                        <td>{{ ucwords(str_replace('_', ' ', $source)) }}</td>
                        <td class="num">{{ number_format($count) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2">No source data</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Most Reserved Services</h2>
        <table>
            <thead>
                <tr>
                    <th>Service</th>
                    <th class="num">Count</th>
                </tr>
            </thead>
            <tbody>
                @forelse($popularServices as $name => $count)
                    <tr>
                        <td>{{ $name }}</td>
                        <td class="num">{{ number_format($count) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2">No service data</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Package Popularity</h2>
        <p class="meta" style="margin-top:0">Total package reservations: {{ number_format($packagePopularity['total_package_bookings']) }}</p>
        <table>
            <thead>
                <tr>
                    <th>Package</th>
                    <th class="num">Count</th>
                    <th class="num">Share</th>
                </tr>
            </thead>
            <tbody>
                @forelse($packagePopularity['breakdown'] as $package)
                    <tr>
                        <td>{{ $package['name'] }}</td>
                        <td class="num">{{ number_format($package['count']) }}</td>
                        <td class="num">{{ $package['share_percentage'] }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="3">No package data</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Payment Status Breakdown</h2>
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th class="num">Count</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach(\App\Enums\PaymentStatus::cases() as $status)
                    @php
                        $row = $paymentSummary['by_status'][$status->value] ?? ['count' => 0, 'total_amount' => 0];
                    @endphp
                    <tr>
                        <td>{{ $status->label() }}</td>
                        <td class="num">{{ number_format($row['count']) }}</td>
                        <td class="num">₱{{ number_format($row['total_amount'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </main>
</body>
</html>
