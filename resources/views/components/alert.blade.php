@props(['type' => 'info', 'title' => null])

@php
    $type = in_array($type, ['success', 'warning', 'danger', 'error', 'info']) ? $type : 'info';

    [$classes, $iconPath, $defaultTitle] = match ($type) {
        'success' => [
            'bg-success-light border-success text-emerald-900',
            'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            'Success',
        ],
        'warning' => [
            'bg-warning-light border-warning text-amber-900',
            'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
            'Please check',
        ],
        'danger', 'error' => [
            'bg-danger-light border-danger text-rose-900',
            'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
            'Something went wrong',
        ],
        default => [
            'bg-info-light border-info text-sky-900',
            'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            'Note',
        ],
    };
@endphp

<div
    x-data="{ visible: true }"
    x-show="visible"
    x-transition.opacity.duration.150ms
    {{ $attributes->merge(['class' => 'flex items-start gap-3 p-4 border-l-4 rounded-r-lg ' . $classes]) }}
    role="alert"
    aria-live="polite"
>
    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}" />
    </svg>
    <div class="min-w-0 flex-1">
        <p class="font-semibold text-sm">{{ $title ?? $defaultTitle }}</p>
        <div class="text-sm mt-0.5">
            {{ $slot }}
        </div>
    </div>
    <button type="button" @click="visible = false" class="shrink-0 rounded p-1 -m-1 opacity-70 hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-current" aria-label="Dismiss message">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
        </svg>
    </button>
</div>
