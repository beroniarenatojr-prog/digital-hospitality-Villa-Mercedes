@props(['status'])

@php
    $colors = match (strtolower($status)) {
        'approved', 'verified', 'available', 'completed' => 'bg-success-light text-success',
        'pending' => 'bg-warning-light text-warning',
        'rejected', 'cancelled', 'unavailable' => 'bg-danger-light text-danger',
        'rescheduled', 'maintenance' => 'bg-secondary text-text-muted',
        default => 'bg-secondary text-text',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium " . $colors]) }}>
    {{ $status }}
</span>
