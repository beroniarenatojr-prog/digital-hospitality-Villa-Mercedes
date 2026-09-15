@props(['icon' => null, 'title', 'description' => null])

@php
    // Callers pass a name (e.g. "home"), not markup.
    $iconPath = match ($icon) {
        'home' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        'sparkles' => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
        'ticket' => 'M15 5v2m0 4v2m0 4v2M5 5h14a2 2 0 012 2v3a2 2 0 000 4v3a2 2 0 01-2 2H5a2 2 0 01-2-2v-3a2 2 0 000-4V7a2 2 0 012-2z',
        'search' => 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
        'calendar' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        default => null,
    };
@endphp

<div {{ $attributes->merge(['class' => 'text-center py-12 px-4 sm:px-6 lg:px-8 border-2 border-dashed border-border rounded-xl bg-surface']) }}>
    @if ($iconPath)
        <svg class="mx-auto h-12 w-12 text-text-muted opacity-60 mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}" />
        </svg>
    @endif
    <h3 class="text-base font-semibold text-text">{{ $title }}</h3>
    @if ($description)
        <p class="mt-2 text-sm text-text-muted max-w-md mx-auto">{{ $description }}</p>
    @endif
    @if (isset($action))
        <div class="mt-6">
            {{ $action }}
        </div>
    @endif
</div>
