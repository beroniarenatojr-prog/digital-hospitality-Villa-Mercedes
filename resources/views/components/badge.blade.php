@props(['status', 'variant' => 'default'])

@php
    $colors = match (strtolower($status)) {
        'approved', 'confirmed', 'approve', 'verified', 'available', 'completed', 'closed', 'accepted', 'create' => 'bg-success-light text-success',
        'pending', 'open', 'waitlisted' => 'bg-warning-light text-warning',
        'rejected', 'cancelled', 'expired', 'unavailable', 'delete' => 'bg-danger-light text-danger',
        'ongoing', 'planned' => 'bg-info-light text-info',
        'rescheduled', 'maintenance' => 'bg-secondary text-text-muted',
        default => 'bg-secondary text-text',
    };

    $shape = $variant === 'compact'
        ? 'rounded-sm border border-current px-2 py-0.5'
        : 'rounded-full px-2.5 py-0.5';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center text-xs font-medium {$shape} {$colors}"]) }}>
    {{ $status }}
</span>
