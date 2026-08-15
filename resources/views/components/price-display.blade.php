@props(['amount', 'class' => ''])

<span {{ $attributes->merge(['class' => 'tabular-nums font-semibold ' . $class]) }}>
    ₱{{ number_format($amount, 2) }}
</span>
