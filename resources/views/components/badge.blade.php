@props(['status', 'variant' => 'default', 'label' => null])

@php
    // A state carries its own word and its own colour. Pass the enum and the badge
    // asks it, so the two can never drift: the guest portal and the back office
    // render the same state through this one component, which is the whole point.
    $isState = $status instanceof \BackedEnum;

    $label ??= $isState ? $status->label() : (string) $status;

    // Plain strings still work for chips that are not states — an activity action,
    // a manually written word — and fall back to the historical word map.
    $tone = $isState && method_exists($status, 'tone')
        ? $status->tone()
        : match (strtolower((string) $status)) {
            'approved', 'confirmed', 'approve', 'verified', 'available', 'completed', 'closed', 'accepted', 'create' => 'success',
            'pending', 'open', 'waitlisted' => 'warning',
            'rejected', 'cancelled', 'expired', 'unavailable', 'delete' => 'danger',
            'ongoing', 'planned', 'sign in' => 'info',
            'rescheduled', 'maintenance', 'sign out' => 'muted',
            default => 'neutral',
        };

    $colors = [
        'success' => 'bg-success-light text-success',
        'warning' => 'bg-warning-light text-warning',
        'danger' => 'bg-danger-light text-danger',
        'info' => 'bg-info-light text-info',
        'muted' => 'bg-secondary-light text-text-muted',
        'neutral' => 'bg-secondary-light text-text',
    ][$tone];

    $shape = $variant === 'compact'
        ? 'rounded-sm border border-current px-2 py-0.5'
        : 'rounded-full px-2.5 py-0.5';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center text-xs font-medium {$shape} {$colors}"]) }}>
    {{ $label }}
</span>
