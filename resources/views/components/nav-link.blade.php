@props(['active' => false, 'onDark' => false])

@php
if ($onDark) {
    $classes = $active
        ? 'inline-block max-w-full min-w-0 text-center px-1 py-1 border-b-2 border-white text-xs font-semibold leading-5 text-white sm:inline-flex sm:items-center sm:text-sm sm:whitespace-nowrap focus:outline-none transition duration-150 ease-in-out'
        : 'inline-block max-w-full min-w-0 text-center px-1 py-1 border-b-2 border-transparent text-xs font-medium leading-5 text-white/80 sm:inline-flex sm:items-center sm:text-sm sm:whitespace-nowrap hover:text-white hover:border-white/50 focus:outline-none focus:text-white transition duration-150 ease-in-out';
} else {
    $classes = $active
        ? 'inline-flex shrink-0 items-center px-1 pt-1 border-b-2 border-accent text-sm font-semibold leading-5 text-primary whitespace-nowrap focus:outline-none transition duration-150 ease-in-out'
        : 'inline-flex shrink-0 items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-text-muted whitespace-nowrap hover:text-primary hover:border-accent/40 focus:outline-none focus:text-primary transition duration-150 ease-in-out';
}
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if($active) aria-current="page" @endif>
    {{ $slot }}
</a>
