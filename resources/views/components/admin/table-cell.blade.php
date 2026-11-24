@props([
    'isHeader' => false,
    'numeric' => false,
    'nowrap' => false,
])

@php
    // Numeric cells are right-aligned and tabular so amounts line up on the
    // decimal point. Misread figures are the most expensive mistake a back
    // office makes, and this is the cheapest way to prevent it.
    $align = $numeric ? 'text-right' : 'text-left';
    $whitespace = $nowrap ? 'whitespace-nowrap' : '';
    $base = 'px-4 py-3 align-middle ' . $align . ' ' . $whitespace;
@endphp

@if ($isHeader)
    <th {{ $attributes->merge(['scope' => 'col', 'class' => $base . ' text-xs font-semibold uppercase tracking-wide text-text-muted']) }}>
        {{ $slot }}
    </th>
@else
    <td {{ $attributes->merge(['class' => $base . ' text-sm text-text']) }}>
        {{ $slot }}
    </td>
@endif
