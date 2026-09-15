@props(['title', 'eyebrow' => null, 'align' => 'center', 'tone' => 'dark'])

@php
    $wrap = $align === 'left' ? 'text-left' : 'text-center mx-auto';
    $titleColor = $tone === 'light' ? 'text-white' : 'text-primary';
    $bodyColor = $tone === 'light' ? 'text-white/85' : 'text-text-muted';
    $ruleColor = $tone === 'light' ? 'bg-white' : 'bg-primary';
@endphp

<div {{ $attributes->merge(['class' => 'max-w-3xl '.$wrap]) }}>
    @if($eyebrow)
        <p class="text-xs font-semibold uppercase tracking-[0.2em] {{ $tone === 'light' ? 'text-white/70' : 'text-accent' }} mb-3">{{ $eyebrow }}</p>
    @endif
    <h2 class="font-heading text-4xl md:text-5xl font-semibold {{ $titleColor }}">{{ $title }}</h2>
    <span class="mt-4 block h-1 w-16 rounded-full {{ $ruleColor }} {{ $align === 'left' ? '' : 'mx-auto' }}" aria-hidden="true"></span>
    @if(isset($slot) && trim($slot) !== '')
        <p class="mt-5 text-base {{ $bodyColor }} leading-relaxed">{{ $slot }}</p>
    @endif
</div>
