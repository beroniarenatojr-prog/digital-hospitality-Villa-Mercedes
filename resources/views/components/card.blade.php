@props(['class' => ''])

<div {{ $attributes->merge(['class' => 'bg-surface overflow-hidden rounded-2xl border border-border shadow-none ' . $class]) }}>
    {{ $slot }}
</div>
