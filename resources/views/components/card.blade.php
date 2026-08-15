@props(['class' => ''])

<div {{ $attributes->merge(['class' => 'bg-surface overflow-hidden shadow-sm rounded-xl border border-border ' . $class]) }}>
    {{ $slot }}
</div>
