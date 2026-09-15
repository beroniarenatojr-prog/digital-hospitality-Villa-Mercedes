@props(['size' => 'md'])

@php
    $button = $size === 'sm'
        ? 'inline-flex items-center justify-center w-9 h-9 rounded-full text-white'
        : 'inline-flex items-center justify-center w-12 h-12 rounded-full text-white';
    $icon = $size === 'sm' ? 'w-4 h-4' : 'w-5 h-5';
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-3']) }}>
    <a
        href="{{ config('resort.facebook_url') }}"
        target="_blank"
        rel="noopener noreferrer"
        class="{{ $button }} bg-[#1877F2] hover:brightness-110"
        aria-label="{{ config('resort.facebook_label') }}"
    >
        <span class="font-bold {{ $size === 'sm' ? 'text-sm' : '' }}">f</span>
    </a>
    <a
        href="{{ config('resort.instagram_url') }}"
        target="_blank"
        rel="noopener noreferrer"
        class="{{ $button }} bg-[linear-gradient(45deg,#f9ce34,#ee2a7b,#6228d7)] hover:brightness-110"
        aria-label="{{ config('resort.instagram_label') }}"
    >
        <svg class="{{ $icon }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
            <rect x="3" y="3" width="18" height="18" rx="5" />
            <circle cx="12" cy="12" r="4" />
            <circle cx="17.5" cy="6.5" r="0.8" fill="currentColor" stroke="none" />
        </svg>
    </a>
</div>
