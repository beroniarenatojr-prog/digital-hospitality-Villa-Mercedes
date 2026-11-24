@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
])

@php
    // One button vocabulary for the whole back office. Elevation is a border,
    // never a shadow; only the primary action is filled; destructive actions
    // are outlined until they are confirmed.
    $variants = [
        'primary' => 'border border-transparent bg-primary text-white hover:bg-primary-hover',
        'secondary' => 'border border-border bg-surface text-text hover:bg-muted',
        'danger' => 'border border-danger/40 bg-surface text-danger hover:bg-danger-light',
        'danger-solid' => 'border border-transparent bg-danger text-white hover:bg-danger/90',
        'ghost' => 'border border-transparent text-primary hover:bg-primary-light',
    ];

    $sizes = [
        'sm' => 'min-h-[44px] px-3 text-xs',
        'md' => 'min-h-[44px] px-4 text-sm',
    ];

    $classes = 'inline-flex items-center justify-center gap-1.5 rounded-lg font-semibold transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 '
        .($variants[$variant] ?? $variants['primary']).' '
        .($sizes[$size] ?? $sizes['md']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$classes]) }}>{{ $slot }}</button>
@endif
