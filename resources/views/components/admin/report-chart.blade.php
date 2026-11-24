@props(['chart'])

@php
    // Series colours are the palette tokens themselves (primary, info, success)
    // so a chart can never drift away from the rest of the back office. The
    // axis and gridline greys are the border/text-muted tokens for the same
    // reason: SVG cannot read Tailwind classes, so the values are written here
    // once and nowhere else.
    $colors = ['#623F1F', '#6B7F5B', '#C96F4F'];
    $gridColor = '#D9C9B2';
    $labelColor = '#596356';

    $maximum = max([1, ...collect($chart['rows'])->flatMap(fn ($row) => collect(array_keys($chart['series']))->map(fn ($key) => $row[$key] ?? 0))->all()]);

    // Inside <svg> and its <text> nodes HTML is not allowed, so the axis labels
    // and bar tooltips are the one place in the admin that formats money by
    // hand. Everything in real HTML below goes through x-amount instead.
    $format = fn ($value) => ($chart['money'] ? '₱' : '').number_format($value, $chart['money'] ? 2 : 0);
@endphp

<x-admin.panel :title="$chart['title']" class="mb-6 min-w-0">
    <x-slot:actions>
        <x-admin.data-status :status="$chart['status']" />
    </x-slot:actions>

    <p class="text-xs text-text-muted">{{ $chart['basis'] }} · {{ $chart['comparison'] }}</p>

    <x-content-panel class="mt-2 text-sm text-text">
        <x-slot:heading>Metric definition</x-slot:heading>
        <p class="mt-2 max-w-3xl text-text-muted">{{ $chart['definition'] }}</p>
    </x-content-panel>

    {{-- The number in each swatch is what ties a legend entry to its bars in the
         charts below, so it is kept for every series rather than only the ones
         whose colour is easy to tell apart. The swatch is decorative: the
         series name beside it carries the meaning. --}}
    <div class="my-4 flex flex-wrap gap-x-5 gap-y-2 text-xs font-medium text-text">
        @foreach ($chart['series'] as $key => $label)
            <span class="inline-flex items-center gap-2">
                <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded text-[10px] font-semibold text-white" style="background: {{ $colors[$loop->index % 3] }}" aria-hidden="true">{{ $loop->iteration }}</span>
                {{ $label }}
            </span>
        @endforeach
    </div>

    @if (count($chart['rows']) === 0)
        <p class="py-8 text-sm text-text-muted">Not enough data for this chart.</p>
    @elseif ($chart['type'] === 'line')
        <div class="overflow-x-auto" tabindex="0" aria-label="Scrollable trend chart">
            <svg viewBox="0 0 800 260" class="w-full" style="min-width:560px" role="img" aria-label="{{ $chart['title'] }}; exact values in the data table below">
                @foreach ([0, .5, 1] as $tick)
                    <line x1="90" y1="{{ 215 - 180 * $tick }}" x2="780" y2="{{ 215 - 180 * $tick }}" stroke="{{ $gridColor }}" />
                    <text x="82" y="{{ 219 - 180 * $tick }}" text-anchor="end" font-size="12" fill="{{ $labelColor }}">{{ $format($maximum * $tick) }}</text>
                @endforeach
                @foreach ($chart['series'] as $key => $label)
                    @php($color = $colors[$loop->index % 3])
                    <polyline fill="none" stroke="{{ $color }}" stroke-width="3" points="@foreach ($chart['rows'] as $row){{ 100 + $loop->index * (670 / max(1, count($chart['rows']) - 1)) }},{{ 215 - 180 * ($row[$key] ?? 0) / $maximum }} @endforeach" />
                    @foreach ($chart['rows'] as $row)
                        <circle cx="{{ 100 + $loop->index * (670 / max(1, count($chart['rows']) - 1)) }}" cy="{{ 215 - 180 * ($row[$key] ?? 0) / $maximum }}" r="3" fill="{{ $color }}"><title>{{ $row['label'] }} · {{ $label }}: {{ $format($row[$key] ?? 0) }}</title></circle>
                    @endforeach
                @endforeach
                <text x="100" y="245" font-size="12" fill="{{ $labelColor }}">{{ $chart['rows'][0]['label'] }}</text>
                <text x="770" y="245" text-anchor="end" font-size="12" fill="{{ $labelColor }}">{{ $chart['rows'][count($chart['rows']) - 1]['label'] }}</text>
            </svg>
        </div>
    @elseif ($chart['type'] === 'column')
        <div class="overflow-x-auto" tabindex="0" aria-label="Scrollable fourteen-day chart">
            <div class="flex gap-3" style="min-width:720px">
                @foreach ($chart['rows'] as $row)
                    <div class="min-w-0 flex-1 text-center">
                        <div class="flex h-40 items-end justify-center gap-1">
                            @foreach ($chart['series'] as $key => $label)
                                <div style="height:{{ 100 * ($row[$key] ?? 0) / $maximum }}%;width:40%;background:{{ $colors[$loop->index % 3] }};opacity:.55" title="{{ $label }}: {{ $row[$key] }}"></div>
                            @endforeach
                        </div>
                        <span class="mt-2 block text-xs text-text-muted">{{ $row['label'] }}</span>
                        <span class="block text-xs text-text-muted tabular-nums">{{ $row['arrivals'] }} / {{ $row['departures'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        <p class="mt-2 text-xs text-text-muted">Lighter columns: scheduled on-books demand. Counts below each date: check ins / check outs. Shared scale: 0–{{ $maximum }} reservations.</p>
    @else
        {{-- A bar chart is a set of exact numbers, so the numbers are read from
             the data table below rather than repeated as SVG text. --}}
        <div class="space-y-5" aria-hidden="true">
            @foreach ($chart['rows'] as $row)
                <div>
                    <p class="mb-2 text-sm font-medium text-text">{{ $row['label'] }}</p>
                    @foreach ($chart['series'] as $key => $label)
                        <div class="mb-1 flex items-center gap-3">
                            <span class="w-4 shrink-0 text-xs tabular-nums text-text-muted">{{ $loop->iteration }}</span>
                            <div class="h-2 flex-1 overflow-hidden rounded-sm bg-muted">
                                <div style="width:{{ 100 * ($row[$key] ?? 0) / $maximum }}%;height:100%;background:{{ $colors[$loop->index % 3] }};opacity:{{ $key === 'on_books' ? '.5' : '1' }}"></div>
                            </div>
                            <span class="w-28 shrink-0 text-right text-xs tabular-nums text-text">{{ $format($row[$key] ?? 0) }}</span>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    <x-content-panel class="mt-5 border-t border-border pt-3">
        <x-slot:heading>View data table</x-slot:heading>
        <div class="mt-3">
            <x-admin.table plain>
                <caption class="sr-only">{{ $chart['title'] }}</caption>
                <x-admin.table-head>
                    <x-admin.table-cell :isHeader="true">Period / category</x-admin.table-cell>
                    @foreach ($chart['series'] as $label)
                        <x-admin.table-cell :isHeader="true" numeric>{{ $label }}</x-admin.table-cell>
                    @endforeach
                </x-admin.table-head>
                <x-admin.table-body>
                    @foreach ($chart['rows'] as $row)
                        <x-admin.table-row>
                            <x-admin.table-cell :isHeader="true" scope="row" class="font-normal">{{ $row['label'] }}</x-admin.table-cell>
                            @foreach ($chart['series'] as $key => $label)
                                <x-admin.table-cell numeric>
                                    @if ($chart['money'])
                                        <x-amount :value="$row[$key] ?? 0" />
                                    @else
                                        {{ number_format($row[$key] ?? 0) }}
                                    @endif
                                </x-admin.table-cell>
                            @endforeach
                        </x-admin.table-row>
                    @endforeach
                </x-admin.table-body>
            </x-admin.table>
        </div>
    </x-content-panel>

    {{-- The id is the anchor ReportCharts hands out as drill_down, so it stays
         addressable from anywhere that wants to open the evidence. --}}
    <x-content-panel id="{{ $chart['id'] }}-records" class="mt-3">
        <x-slot:heading>Inspect contributing records ({{ count($chart['records']) }})</x-slot:heading>
        <ul class="mt-3 max-h-72 space-y-2 overflow-y-auto text-sm">
            @forelse ($chart['records'] as $record)
                <li>
                    <a href="{{ $record['url'] }}" class="rounded font-medium text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">{{ $record['label'] }}</a>
                    <span class="text-text-muted"> · {{ $record['date'] }}</span>
                    @if ($record['value'] !== null)
                        <span class="text-text-muted"> · </span>
                        @if ($chart['money'])
                            <x-amount :value="$record['value']" />
                        @else
                            <span class="tabular-nums">{{ number_format($record['value']) }}</span>
                        @endif
                    @endif
                </li>
            @empty
                <li class="text-text-muted">No contributing records in this selection.</li>
            @endforelse
        </ul>
    </x-content-panel>
</x-admin.panel>
