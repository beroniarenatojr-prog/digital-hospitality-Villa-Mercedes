@props([
    'items' => [],
    'subtitle' => 'The rate you see covers the whole resort, not just your room.',
])

<section {{ $attributes->merge(['class' => 'listing-inclusives']) }} aria-labelledby="listing-inclusives-heading">
    <div class="text-center max-w-2xl mx-auto">
        <div class="flex items-center justify-center gap-3">
            <span class="hidden sm:block h-px w-8 bg-accent/70" aria-hidden="true"></span>
            <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-accent">NO EXTRA CHARGE</p>
            <span class="hidden sm:block h-px w-8 bg-accent/70" aria-hidden="true"></span>
        </div>
        <h2 id="listing-inclusives-heading" class="mt-3 font-heading text-4xl md:text-5xl font-semibold text-text tracking-tight">
            Included with every stay
        </h2>
        <p class="mt-4 text-sm sm:text-base text-text-muted leading-relaxed">
            {{ $subtitle }}
        </p>
    </div>

    <ul class="mt-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 lg:gap-5 list-none p-0 m-0">
        @foreach($items as $item)
            <li class="rounded-3xl bg-background px-5 py-8 text-center shadow-sm">
                <span class="mx-auto mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-success-light text-text-muted" aria-hidden="true">
                    @switch($item['icon'])
                        @case('wifi')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.3 12.7a5.5 5.5 0 017.4 0M5.6 9.8a9.5 9.5 0 0112.8 0M12 16.4h.01"/></svg>
                            @break
                        @case('snowflake')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 10h16M6 10V8a2 2 0 012-2h8a2 2 0 012 2v2M8 14h.01M12 14h.01M16 14h.01M7 18h10"/></svg>
                            @break
                        @case('water')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 17h16M6 17c0-2.2 1.8-4 4-4h4c2.2 0 4 1.8 4 4M8 13V9.5A2.5 2.5 0 0110.5 7h.2M14 13V9"/></svg>
                            @break
                        @case('coffee')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9h8a1 1 0 011 1v3a4 4 0 01-4 4H11a4 4 0 01-4-4v-3a1 1 0 011-1zm9 1h.8a2.2 2.2 0 010 4.4H17M8 20h8M10 4.5c.3.6.3 1.3 0 1.8M13 4.5c.3.6.3 1.3 0 1.8"/></svg>
                            @break
                        @case('parking')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><rect x="4.5" y="4.5" width="15" height="15" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M10 16V8h3.2a2.4 2.4 0 010 4.8H10"/></svg>
                            @break
                        @case('flame')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3.5s2.2 3 2.2 5.4c0 1.2-.8 2.1-1.8 2.6 1.7-.2 3.6-1.6 3.6-4 2.2 2.2 4 5 4 7.6A6 6 0 016 15.1c0-2.6 2-5.5 4-7.6-.2 1.4.3 2.6 1.4 3.4C11 9.4 12 6.8 12 3.5z"/></svg>
                            @break
                        @case('hall')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20V10l8-6 8 6v10M9 20v-6h6v6"/></svg>
                            @break
                        @case('villa')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 11l8-7 8 7v9H4v-9zM9 20v-6h6v6"/></svg>
                            @break
                        @case('grill')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10h14M6 10l1.2 8h9.6L18 10M8 6.5c.4.7.4 1.5 0 2.2M12 5.5c.4.7.4 1.5 0 2.2M16 6.5c.4.7.4 1.5 0 2.2M9 20h6"/></svg>
                            @break
                        @case('shower')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h8v3H8v5M8 14v.01M6 16.5v.01M10 16.5v.01M8 19v.01"/></svg>
                            @break
                        @case('towel')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 5h10a2 2 0 012 2v12H8a2 2 0 01-2-2V5zM8 5v12"/></svg>
                            @break
                        @case('bottle')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 8V6.5A1.5 1.5 0 0110.5 5h3A1.5 1.5 0 0115 6.5V8M10 8h4l1 11H9L10 8zM11 3h2"/></svg>
                            @break
                        @case('bath')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 12h16v3a4 4 0 01-4 4H8a4 4 0 01-4-4v-3zM7 12V8a3 3 0 013-3h1M7 19l-1 2M17 19l1 2"/></svg>
                            @break
                        @default
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3.5l1.2 3.6L17 8.3l-3 2.3.9 3.8L12 12.8 9.1 14.4 10 10.6 7 8.3l3.8-1.2L12 3.5z"/></svg>
                    @endswitch
                </span>
                <h3 class="text-sm font-semibold text-text">{{ $item['title'] }}</h3>
                <p class="mt-1 text-xs text-text-muted leading-relaxed">{{ $item['detail'] }}</p>
            </li>
        @endforeach
    </ul>
</section>
