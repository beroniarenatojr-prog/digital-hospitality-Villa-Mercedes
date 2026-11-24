@props([
    'label',
    'value' => null,
    'basis' => null,
    'note' => null,
    'definition' => null,
    'change' => null,
    'comparisonMissing' => false,
])

@php
    // A KPI tile: the number, the date basis it is measured on, and whether it
    // moved. Reports is the only screen assembled out of these, but it is
    // assembled out of dozens across its three views, so the tile lives here
    // rather than being re-typeset per section.
    //
    // The caption line is not decoration: a figure like "Cash collected" is
    // meaningless without saying which recorded date it counts, and a mistake
    // read off a report is expensive. `basis` is therefore always rendered —
    // pass '' only when the label already states it.
    $direction = $change['direction'] ?? null;
    $percentage = $change['percentage'] ?? null;
@endphp

<article
    {{ $attributes->class(['flex min-w-0 flex-col rounded-xl border border-border bg-surface p-5']) }}
    @if ($definition) title="{{ $definition }}" @endif
>
    <p class="text-sm font-medium text-text-muted">{{ $label }}</p>

    <p class="mt-2 text-2xl font-semibold tabular-nums text-text">{{ $value ?? $slot }}</p>

    @if ($basis)
        <p class="mt-1 text-xs text-text-muted">{{ $basis }}</p>
    @endif

    @if ($change)
        <p class="mt-1 text-xs font-semibold {{ ($direction ?? 0) >= 0 ? 'text-success' : 'text-warning' }}">
            {{ ($direction ?? 0) >= 0 ? '↑' : '↓' }} {{ number_format($percentage, 1) }}% vs previous period
        </p>
    @elseif ($comparisonMissing)
        <p class="mt-1 text-xs text-text-muted">No comparable previous-period value</p>
    @endif

    @if ($note)
        <p class="mt-2 text-xs text-text-muted">{{ $note }}</p>
    @endif
</article>
