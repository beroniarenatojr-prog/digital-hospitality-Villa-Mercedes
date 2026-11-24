@props([
    'variant' => 'info',
    'title' => null,
    'dismissible' => true,
])

@php
    $variant = in_array($variant, ['success', 'warning', 'danger', 'info'], true) ? $variant : 'info';

    // Square corners, one hairline, one tinted surface. No colored left bar:
    // the tint and the icon already carry the state, and a 4px stripe per
    // message is decoration that costs horizontal room in a dense back office.
    [$surface, $iconPath, $defaultTitle] = match ($variant) {
        'success' => [
            'border-success/30 bg-success-light text-text',
            'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            'Saved',
        ],
        'warning' => [
            'border-warning/30 bg-warning-light text-text',
            'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
            'Check this before continuing',
        ],
        'danger' => [
            'border-danger/30 bg-danger-light text-text',
            'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
            'That did not work',
        ],
        default => [
            'border-info/30 bg-info-light text-text',
            'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            'Note',
        ],
    };

    $iconTint = match ($variant) {
        'success' => 'text-success',
        'warning' => 'text-warning',
        'danger' => 'text-danger',
        default => 'text-info',
    };
@endphp

<div
    x-data="{ visible: true }"
    x-show="visible"
    {{ $attributes->class(['flex items-start gap-3 rounded-xl border p-4 ' . $surface]) }}
    role="{{ $variant === 'danger' ? 'alert' : 'status' }}"
    aria-live="{{ $variant === 'danger' ? 'assertive' : 'polite' }}"
>
    <svg class="mt-0.5 h-5 w-5 shrink-0 {{ $iconTint }}" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
        <path d="{{ $iconPath }}" />
    </svg>

    <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold">{{ $title ?? $defaultTitle }}</p>
        <div class="mt-0.5 text-sm">{{ $slot }}</div>
    </div>

    @if ($dismissible)
        <button
            type="button"
            @click="visible = false"
            class="-m-1 shrink-0 rounded-lg p-1 text-text-muted hover:bg-black/5 hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
        >
            <span class="sr-only">Dismiss message</span>
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    @endif
</div>
