@php
    $maximum = max(1, collect($chart['rows'])->max('value') ?? 0);
    $hasValues = collect($chart['rows'])->contains(fn ($row) => $row['value'] != 0);
    $formatAxis = fn ($value) => ($chart['money'] ? '₱' : '').number_format($value, 0);
@endphp
<x-admin.panel :title="$chart['title']" class="mb-6 min-w-0">
    @if (isset($chart['grouping']))<p class="mb-3 text-xs text-text-muted">By {{ strtolower($chart['grouping']) }} · {{ \App\Support\ResortTime::timezone() }}</p>@endif
    @if (! $hasValues)
        <p class="py-10 text-sm text-text-muted">No activity recorded for this chart in the selected period.</p>
    @elseif ($chart['type'] === 'line')
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="{{ $chart['title'] }}">
            <svg viewBox="0 0 700 240" class="w-full min-w-[420px]" role="img" aria-label="{{ $chart['title'] }}. Exact values are in the table below.">
                @foreach ([0, 0.5, 1] as $tick)
                    <line x1="100" x2="680" y1="{{ 190 - $tick * 160 }}" y2="{{ 190 - $tick * 160 }}" stroke="#D9C9B2" />
                    <text x="90" y="{{ 194 - $tick * 160 }}" text-anchor="end" fill="#596356" font-size="12">{{ $formatAxis($maximum * $tick) }}</text>
                @endforeach
                @php($points = collect($chart['rows'])->map(fn ($row, $i) => (110 + 550 * $i / max(1, count($chart['rows']) - 1)).','.(190 - 160 * $row['value'] / $maximum))->implode(' '))
                <polyline points="{{ $points }}" fill="none" stroke="#623F1F" stroke-width="3" />
                @foreach ($chart['rows'] as $i => $row)
                    <circle cx="{{ 110 + 550 * $i / max(1, count($chart['rows']) - 1) }}" cy="{{ 190 - 160 * $row['value'] / $maximum }}" r="3" fill="#623F1F"><title>{{ $row['label'] }}: {{ $formatAxis($row['value']) }}</title></circle>
                    @if (count($chart['rows']) <= 6 || $i === 0 || $i === count($chart['rows']) - 1 || $i === intdiv(count($chart['rows']), 2))
                        <text x="{{ 110 + 550 * $i / max(1, count($chart['rows']) - 1) }}" y="220" text-anchor="middle" fill="#596356" font-size="12">{{ $row['label'] }}</text>
                    @endif
                @endforeach
            </svg>
        </div>
    @else
        <div class="space-y-5">
            @foreach ($chart['rows'] as $row)
                <div>
                    <div class="mb-2 flex flex-wrap justify-between gap-2 text-sm">
                        <span>{{ $row['label'] }}</span>
                        <strong class="tabular-nums">@if ($chart['money'])<x-amount :value="$row['value']" />@else{{ number_format($row['value']) }}@endif</strong>
                    </div>
                    <div class="h-2 rounded-sm bg-muted" aria-hidden="true"><div class="h-2 rounded-sm bg-primary" style="width: {{ 100 * $row['value'] / $maximum }}%"></div></div>
                </div>
            @endforeach
        </div>
    @endif
    <x-content-panel class="mt-5 border-t border-border pt-3">
        <x-slot:heading>View exact values</x-slot:heading>
        <div class="mt-3">
            @include('admin.reports.partials.management-table', ['table' => ['title' => $chart['title'], 'columns' => ['Period / category', $chart['money'] ? 'Amount' : 'Reservations'], 'types' => ['text', $chart['money'] ? 'money' : 'number'], 'rows' => array_map(fn ($row) => ['cells' => [$row['label'], $row['value']]], $chart['rows'])]])
        </div>
    </x-content-panel>
</x-admin.panel>
