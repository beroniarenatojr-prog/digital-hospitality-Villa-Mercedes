@props([
    'from',
    'to',
    'time' => true,
])

@php
    $start = $from instanceof \Carbon\CarbonInterface ? $from : ($from ? \Carbon\Carbon::parse($from) : null);
    $end = $to instanceof \Carbon\CarbonInterface ? $to : ($to ? \Carbon\Carbon::parse($to) : null);

    // Same day collapses to one date so a one-night stay reads as one stay.
    $sameDay = $start && $end && $start->isSameDay($end);
    $timePattern = $time ? ' g:i A' : '';
@endphp

<span {{ $attributes->class(['whitespace-nowrap tabular-nums']) }}>
    @if (! $start)
        <span class="text-text-muted">Not set</span>
    @elseif (! $end)
        {{ $start->format('M j, Y'.$timePattern) }}
    @elseif ($sameDay)
        {{ $start->format('M j, Y'.$timePattern) }} - {{ $end->format($time ? 'g:i A' : 'M j, Y') }}
    @else
        {{ $start->format('M j, Y'.$timePattern) }} - {{ $end->format('M j, Y'.$timePattern) }}
    @endif
</span>
