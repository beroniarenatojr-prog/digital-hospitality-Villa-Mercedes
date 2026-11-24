@props([
    'action',
    'active' => false,
    'label' => 'Filter this list',
])

{{-- Every list screen searches through this shell, so the search box, the reset
     control, and the advanced filters sit in the same place and read the same
     way wherever staff land. Wrap it in <x-admin.live-filter> to get live
     results. --}}
<form
    method="GET"
    action="{{ $action }}"
    role="search"
    aria-label="{{ $label }}"
    {{ $attributes->class(['mb-6 rounded-xl border border-border bg-surface']) }}
>
    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-end sm:p-5">
        <div class="min-w-0 flex-1">
            {{ $slot }}
        </div>

        <x-admin.live-filter-reset :visible="$active" />
    </div>

    @isset($more)
        <div class="border-t border-border px-4 pb-4 pt-3 sm:px-5">
            {{ $more }}
        </div>
    @endisset
</form>
