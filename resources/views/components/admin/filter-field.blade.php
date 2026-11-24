@props([
    'label',
    'for' => null,
    'error' => null,
])

{{-- One field in an <x-admin.filter-bar> row: equal share of the line, the
     shared admin label above the control, and the validation message under it.
     The width lives here so every bar wraps at the same points. --}}
<div class="min-w-0 flex-1">
    <label @if ($for) for="{{ $for }}" @endif class="admin-label">{{ $label }}</label>

    {{ $slot }}

    @if ($error)
        <x-input-error class="mt-1" :messages="$error" />
    @endif
</div>
