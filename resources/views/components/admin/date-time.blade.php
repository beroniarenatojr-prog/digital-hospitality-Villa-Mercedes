@props([
    'value',
    'format' => 'datetime',
])

@php
    $formats = [
        'date' => 'M j, Y',
        'datetime' => 'M j, Y g:i A',
        'time' => 'g:i A',
        'day' => 'D, M j, Y',
    ];

    $pattern = $formats[$format] ?? $formats['datetime'];
    $moment = $value instanceof \Carbon\CarbonInterface
        ? $value
        : ($value ? \Carbon\Carbon::parse($value) : null);
@endphp

{{-- Dates in the back office always carry their year and an explicit AM/PM:
     "Sep 16" alone is the kind of ambiguity that books the wrong day. --}}
<span {{ $attributes->class(['whitespace-nowrap tabular-nums']) }}>
    @if ($moment)
        {{ $moment->format($pattern) }}
    @else
        <span class="text-text-muted">Not set</span>
    @endif
</span>
