@php
    // Every count this panel states, in the order it states them. The browser
    // compares this between polls, so a count that has not moved leaves the
    // panel alone — and the total in the header is only ever copied across
    // together with the counts it adds up.
    $signature = md5(collect($attention)->map(
        fn (array $item): string => $item['label'].'='.$item['count']
    )->implode('|'));
@endphp

{{-- The refresh target: the counts and the empty state, and nothing else. --}}
<div
    data-live-panel-body
    data-panel-signature="{{ $signature }}"
    data-panel-summary="{{ \App\Support\Admin\DashboardAttention::summary($outstandingTotal) }}"
>
    @if ($outstandingTotal === 0)
        <x-admin.empty
            icon="reviews"
            title="Nothing is waiting on you"
            description="No pending reservations, no unverified payments, and no guests left to check in today."
        />
    @else
        <ul class="divide-y divide-border">
            @foreach (array_filter($attention, fn ($item) => $item['count'] > 0) as $item)
                <li>
                    <a href="{{ $item['url'] }}" class="flex flex-wrap items-center gap-4 px-5 py-4 transition-colors duration-150 hover:bg-primary-light/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold tabular-nums {{ $item['count'] > 0 ? 'bg-primary text-white' : 'bg-muted text-text-muted' }}" aria-hidden="true">
                            {{ $item['count'] }}
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-text">{{ $item['label'] }}</span>
                            <span class="block text-sm text-text-muted">{{ $item['description'] }}</span>
                        </span>

                        <span class="inline-flex min-h-[44px] shrink-0 items-center gap-1 rounded-lg border border-border bg-surface px-4 text-sm font-semibold text-primary">
                            {{ $item['action'] }}
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M9 5l7 7-7 7" />
                            </svg>
                        </span>
                        <span class="sr-only">Open {{ $item['action'] }}, {{ $item['count'] }} waiting.</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
