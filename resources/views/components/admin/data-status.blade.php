@props(['status' => 'Complete'])

{{-- Data completeness is a status like any other, so it wears the same tint
     vocabulary as x-badge: the two must not disagree when a chart labelled
     "Partial data" sits next to a row labelled "Verified". --}}
<span
    {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '.($status === 'Complete' ? 'bg-success-light text-success' : 'bg-warning-light text-warning')]) }}
>
    {{ $status }}
</span>
