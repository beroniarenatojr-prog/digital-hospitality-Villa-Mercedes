@props([
    'href',
    'active' => false,
    'badge' => null,
    'badgeTone' => null,
    'icon' => null,
])

@php
    $badgeCount = $badge !== null ? (int) $badge : 0;
    $label = trim(preg_replace('/\s+/', ' ', (string) $slot));
    $overdue = $badgeTone === 'warning';

    // A count says how much is waiting; the tone says whether any of it has
    // waited too long. Colour alone cannot carry that, so the accessible name
    // states it too.
    $accessibleName = $badgeCount > 0
        ? $label.', '.$badgeCount.' waiting for review'.($overdue ? ', overdue for a decision' : '')
        : $label;
@endphp

{{-- aria-current is emitted here and nowhere else: every destination that is the
     current page announces itself at the link, for pointer and screen readers
     alike. In rail mode the label is hidden, so aria-label carries the name. --}}
<a
    href="{{ $href }}"
    aria-label="{{ $accessibleName }}"
    @if ($active) aria-current="page" @endif
    x-bind:title="rail ? @js($label) : null"
    {{ $attributes->class(['admin-nav-link']) }}
>
    <x-admin.nav-icon :name="$icon" @class(['text-primary' => $active, 'text-text-muted' => ! $active]) />

    <span class="rail-hide min-w-0 flex-1 truncate">{{ $slot }}</span>

    @if ($badgeCount > 0)
        <span
            aria-hidden="true"
            class="admin-nav-badge inline-flex min-w-[1.5rem] items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-bold text-white shadow-sm ring-1 ring-white/70 tabular-nums {{ $overdue ? 'bg-warning text-text' : 'bg-accent text-white' }}"
        >{{ $badgeCount > 99 ? '99+' : $badgeCount }}</span>
    @endif
</a>
