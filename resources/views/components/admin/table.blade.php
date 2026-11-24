@props(['sticky' => false, 'plain' => false])

@php
    // Horizontal scrolling stays possible, but the bleed/margins that made the
    // same table look different on every screen are gone: one radius, one
    // border, one scroll behaviour, everywhere. A table already inside a panel
    // passes plain so the container border is drawn once, not twice.
    $headClasses = $sticky ? '[&_thead]:sticky [&_thead]:top-0 [&_thead]:z-10' : '';
    $shellClasses = $plain ? [] : ['rounded-xl border border-border bg-surface'];
@endphp

<div {{ $attributes->class(['min-w-0 overflow-x-auto', ...$shellClasses]) }}>
    <table class="min-w-full border-collapse text-sm {{ $headClasses }}">
        {{ $slot }}
    </table>
</div>
