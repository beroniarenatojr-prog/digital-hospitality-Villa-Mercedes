@props(['type' => 'info'])

@php
    $classes = match ($type) {
        'success' => 'bg-success-light border-success text-success',
        'warning' => 'bg-warning-light border-warning text-warning',
        'danger', 'error' => 'bg-danger-light border-danger text-danger',
        default => 'bg-info-light border-info text-info',
    };
@endphp

<div {{ $attributes->merge(['class' => "p-4 border-l-4 rounded-r-lg " . $classes]) }} role="alert">
    <div class="flex">
        <div class="ml-3">
            <p class="text-sm">
                {{ $slot }}
            </p>
        </div>
    </div>
</div>
