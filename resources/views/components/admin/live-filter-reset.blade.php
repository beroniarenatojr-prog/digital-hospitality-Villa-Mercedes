@props([
    'visible' => false,
    'label' => 'Clear filters',
])

<button
    type="button"
    data-live-filter-reset
    {{ $attributes->class([
        'inline-flex min-h-[42px] shrink-0 items-center justify-center rounded-lg border border-border bg-surface px-4 text-sm font-semibold text-text transition-colors duration-150 hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2',
        'hidden' => ! $visible,
    ]) }}
>
    {{ $label }}
</button>
