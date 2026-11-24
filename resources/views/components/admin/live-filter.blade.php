@props(['action'])

{{-- The live filter script needs one element that contains both the controls and
     the results it swaps, so this wrapper is explicit instead of implied. --}}
<div data-live-filter data-live-filter-url="{{ $action }}" {{ $attributes }}>
    {{ $slot }}
</div>
