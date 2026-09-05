@props(['status'])

@php
    $colors = match (strtolower($status)) {
        'approved', 'verified', 'available', 'completed', 'closed', 'accepted' => 'bg-success-light text-success',
        'pending', 'open', 'waitlisted' => 'bg-warning-light text-warning',
        'rejected', 'cancelled', 'unavailable' => 'bg-danger-light text-danger',
        'ongoing', 'planned' => 'bg-info-light text-info',
        'rescheduled', 'maintenance' => 'bg-secondary text-text-muted',
        default => 'bg-secondary text-text',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium " . $colors]) }}>
    {{ $status }}
</span>
