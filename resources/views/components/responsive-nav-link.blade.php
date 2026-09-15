@props(['active' => false])

@php
$classes = $active
            ? 'flex items-center w-full ps-4 pe-4 py-3 border-l-4 border-accent text-start text-base font-semibold text-primary bg-primary-light focus:outline-none transition duration-150 ease-in-out'
            : 'flex items-center w-full ps-4 pe-4 py-3 border-l-4 border-transparent text-start text-base font-medium text-text hover:text-primary hover:bg-background hover:border-accent/40 focus:outline-none focus:text-primary focus:bg-background transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if($active) aria-current="page" @endif>
    {{ $slot }}
</a>
