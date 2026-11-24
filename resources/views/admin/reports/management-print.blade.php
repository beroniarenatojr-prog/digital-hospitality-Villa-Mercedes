<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report['title'] }} — Villa Mercedes</title>
    <style>
        body { color: #2E3A2F; background: #fffdf8; font: 14px/1.5 sans-serif; margin: 0; }
        main { max-width: 1100px; margin: auto; padding: 32px; }
        h1 { font-size: 26px; } h2 { font-size: 18px; margin-top: 28px; }
        p { margin: 8px 0; } a { color: #623F1F; } th, td { padding: 9px; border-bottom: 1px solid #D9C9B2; text-align: left; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; } thead { display: table-header-group; }
        tr { break-inside: avoid; } .toolbar { padding: 16px; text-align: right; border-bottom: 1px solid #D9C9B2; }
        button { padding: 12px 16px; background: #623F1F; color: white; border: 0; border-radius: 8px; cursor: pointer; }
        .text-right { text-align: right; } .whitespace-nowrap { white-space: nowrap; } .sr-only { display: none; }
        .logo { width: 60px; height: 60px; object-fit: contain; background: #623F1F; padding: 8px; }
        @media print { .toolbar { display: none; } main { padding: 0; } body { background: white; font-size: 10pt; } a { color: inherit; text-decoration: none; } }
    </style>
</head>
<body>
    <div class="toolbar"><a href="{{ route('admin.reports.index', $reportQuery) }}">Back to report</a> <button onclick="window.print()">Print / Save PDF</button></div>
    <main>
        @if ($reportLogo)<img class="logo" src="{{ $reportLogo }}" alt="Villa Mercedes">@endif
        <p>{{ config('resort.name') }}</p>
        <h1>{{ $report['title'] }}</h1>
        <p>Generated {{ \App\Support\ResortTime::now()->format('M j, Y g:i A') }} · {{ \App\Support\ResortTime::timezone() }}</p>
        @if ($startDate)<p>{{ $startDate->format('M j, Y') }} to {{ $endDate->format('M j, Y') }}</p>@endif
        <p>Service type: {{ $filters->facilityType ? \App\Enums\FacilityType::from($filters->facilityType)->label() : 'All' }} · Source: {{ $filters->source ? \App\Enums\ReservationSource::from($filters->source)->label() : 'All' }}</p>
        <p>{{ $report['basis'] }}</p><p>{{ $report['note'] }}</p>
        <table><thead><tr><th>Measure</th><th>Value</th><th>Basis</th></tr></thead><tbody>
            @foreach ($report['metrics'] as $metric)
                <tr><td>{{ $metric['label'] }}</td><td class="whitespace-nowrap">@if ($metric['money'])<x-amount :value="$metric['value']" />@else{{ number_format($metric['value']) }}@endif</td><td>{{ $metric['note'] }}</td></tr>
            @endforeach
        </tbody></table>
        @include('admin.reports.partials.management-findings')
        @foreach ($report['charts'] as $chart)
            <h2>{{ $chart['title'] }}</h2>
            @include('admin.reports.partials.management-table', ['table' => ['title' => $chart['title'], 'columns' => ['Period / category', $chart['money'] ? 'Amount' : 'Reservations'], 'types' => ['text', $chart['money'] ? 'money' : 'number'], 'rows' => array_map(fn ($row) => ['cells' => [$row['label'], $row['value']]], $chart['rows'])]])
        @endforeach
        @foreach ($report['tables'] as $table)
            <h2>{{ $table['title'] }}</h2>
            @include('admin.reports.partials.management-table')
        @endforeach
    </main>
</body>
</html>
