@props(['tag' => 'button'])

@if($tag === 'a')
    <a {{ $attributes->merge(['class' => 'inline-flex items-center px-4 py-2 bg-surface border border-border rounded-lg font-semibold text-xs text-text uppercase tracking-widest shadow-sm hover:bg-background focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150']) }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-surface border border-border rounded-lg font-semibold text-xs text-text uppercase tracking-widest shadow-sm hover:bg-background focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150']) }}>
        {{ $slot }}
    </button>
@endif
