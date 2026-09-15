@props([
    'visible' => false,
    'label' => 'Reset',
])

<button
    type="button"
    data-live-filter-reset
    {{ $attributes->class([
        'inline-flex min-h-[42px] shrink-0 items-center justify-center rounded-md border border-gray-300 px-4 text-sm font-semibold text-gray-700 transition hover:bg-gray-50',
        'hidden' => ! $visible,
    ]) }}
>
    {{ $label }}
</button>
