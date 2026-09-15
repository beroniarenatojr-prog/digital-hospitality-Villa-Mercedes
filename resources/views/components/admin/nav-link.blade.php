@props([
    'href',
    'active' => false,
    'badge' => null,
])

<a href="{{ $href }}" {{ $attributes->class([
    'group flex items-center gap-3 rounded-md px-2 py-2 text-sm font-medium transition',
    'bg-primary-light text-primary' => $active,
    'text-gray-600 hover:bg-gray-50 hover:text-gray-900' => ! $active,
]) }}>
    <span @class([
        'flex h-5 w-5 shrink-0 items-center justify-center',
        'text-primary' => $active,
        'text-gray-400 group-hover:text-gray-500' => ! $active,
    ])>
        {{ $icon ?? '' }}
    </span>
    <span class="flex-1 truncate">{{ $slot }}</span>
    @if($badge !== null && (int) $badge > 0)
        <span class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-white">
            {{ $badge > 99 ? '99+' : $badge }}
        </span>
    @endif
</a>
