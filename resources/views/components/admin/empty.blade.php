@props([
    'title',
    'description' => null,
    'hint' => null,
    'icon' => 'reservations',
])

{{-- An empty screen is the one place a tool can explain itself, so every empty
     state names what would fill it rather than reporting that it is empty. --}}
<div {{ $attributes->class(['flex flex-col items-center px-5 py-10 text-center']) }}>
    <span class="flex h-11 w-11 items-center justify-center rounded-full bg-muted text-text-muted" aria-hidden="true">
        <x-admin.nav-icon :name="$icon" />
    </span>

    <p class="mt-3 text-sm font-semibold text-text">{{ $title }}</p>

    @if ($description)
        <p class="mt-1 max-w-[52ch] text-sm text-text-muted">{{ $description }}</p>
    @endif

    @isset($action)
        <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
            {{ $action }}
        </div>
    @endisset

    @if ($hint)
        <p class="mt-3 text-xs text-text-muted">{{ $hint }}</p>
    @endif
</div>
