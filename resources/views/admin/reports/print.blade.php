<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Villa Mercedes — Reservations Report</title>
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
        @media print {
            .toolbar { display: none !important; }
            .sheet { max-width: none; padding: 0; }
            a { color: inherit; text-decoration: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('admin.reports.index', array_filter([
            'start_date' => $startDate?->format('Y-m-d'),
            'end_date' => $endDate?->format('Y-m-d'),
            'grouping' => $grouping,
        ])) }}">Back to reports</a>
        <button type="button" class="primary" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <main class="sheet">
        <h1>Villa Mercedes Nature Farm &amp; Resort</h1>
        <p class="meta">
            Reservations report generated {{ now()->format('M j, Y g:i A') }}<br>
            Date from: {{ $startDate?->format('Y-m-d') ?? 'All' }}
            · Date until: {{ $endDate?->format('Y-m-d') ?? 'All' }}
            · Trends: {{ ucfirst($grouping) }}
        </p>

        <h2>Summary</h2>
        <div class="kpi-grid">
            <div class="kpi">
                <div class="label">Total Reservations</div>
                <div class="value">{{ number_format($bookingSummary['total']) }}</div>
            </div>
            <div class="kpi">
                <div class="label">Reservation Value</div>
                <div class="value">₱{{ number_format($revenueSummary['reservation_value'], 2) }}</div>
            </div>
            <div class="kpi">
                <div class="label">Verified Payment</div>
                <div class="value">₱{{ number_format($revenueSummary['verified_payment_revenue'], 2) }}</div>
            </div>
            <div class="kpi">
                <div class="label">Outstanding Balance</div>
                <div class="value">₱{{ number_format($revenueSummary['outstanding_balance'], 2) }}</div>
            </div>
        </div>

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
    </main>
</body>
</html>
