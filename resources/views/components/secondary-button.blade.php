@props(['tag' => 'button'])

@php
    $classes = 'inline-flex items-center justify-center gap-2 px-5 py-2.5 min-h-[44px] bg-surface border border-border rounded-lg font-semibold text-sm text-text shadow-sm hover:bg-background hover:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150';
@endphp

@if($tag === 'a')
    <a {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
