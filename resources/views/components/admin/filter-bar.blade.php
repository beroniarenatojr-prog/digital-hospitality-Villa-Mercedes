@props([
    'action',
    'title' => 'Filters',
    'submit' => 'Apply',
])

{{-- Screens that filter on a button share this bar: a panel, one row of fields,
     and Apply. The fields and the button stay on one line from 1280px up and
     wrap into readable pairs below it, because five fields plus a button at
     1024px squeezes a date input to about a hundred pixels — a control nobody
     can read is not a compact design.

     Lists that filter as you type use <x-admin.filter-form> instead: same
     intent, different interaction. The panel header is the one place a reset
     lives, the `below` slot is for anything that belongs to the bar but not to
     the query (quick ranges, a comparison note), and each field is wrapped in
     <x-admin.filter-field> so no screen can quietly grow its own column width.
--}}
<x-admin.panel class="mb-6" :title="$title">
    @isset($actions)
        <x-slot:actions>{{ $actions }}</x-slot:actions>
    @endisset

    <form
        method="GET"
        action="{{ $action }}"
        {{ $attributes->class(['flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end xl:flex-nowrap']) }}
    >
        {{ $slot }}

        <div class="flex items-end sm:flex-none">
            <x-admin.button type="submit" class="w-full sm:w-auto">{{ $submit }}</x-admin.button>
        </div>
    </form>

    @isset($below)
        {{ $below }}
    @endisset
</x-admin.panel>
