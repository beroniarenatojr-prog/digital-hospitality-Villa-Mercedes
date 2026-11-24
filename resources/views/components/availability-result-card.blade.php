@props(['item'])

@php
    $item = is_array($item) ? $item : [];
    $detailUrl = $item['detail_url'] ?? null;
    if (! empty($item['id']) && ($item['type'] ?? null) !== \App\Enums\BookingServiceType::Pool->value && \App\Support\CustomerNav::isCustomer()) {
        $detailUrl = route('customer.facilities.show', [
            'facility' => $item['id'],
            ...($item['detail_query'] ?? []),
        ]);
    }
@endphp

<article class="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4 shadow-none transition-colors duration-200 hover:border-primary/50">
    <div class="flex gap-3">
        @if(! empty($item['image_url']))
            <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}" class="h-16 w-20 rounded-lg object-cover shrink-0">
        @endif
        <div class="min-w-0">
            @if(! empty($item['type_label']))
                <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ $item['type_label'] }}</p>
            @endif
            <h3 class="font-heading text-lg font-semibold text-text leading-snug">{{ $item['name'] }}</h3>
            @if(! empty($item['description']))
                <p class="mt-1 text-sm text-text-muted line-clamp-2">{{ $item['description'] }}</p>
            @endif
        </div>
    </div>
    <div class="mt-auto flex flex-wrap items-end justify-between gap-2">
        <div class="text-sm text-text-muted">
            @if(! empty($item['capacity']))
                <p>Up to {{ $item['capacity'] }} guests</p>
            @endif
            @if(! empty($item['price_label']))
                <p class="font-semibold text-primary">From {{ $item['price_label'] }}@if(! empty($item['price_unit'])) <span class="font-normal text-text-muted">/ {{ $item['price_unit'] }}</span>@endif</p>
            @else
                <p class="font-semibold text-primary">Ask us for the rate</p>
            @endif
        </div>
        @if($detailUrl)
            <a href="{{ $detailUrl }}" class="inline-flex min-h-[44px] items-center rounded-lg border border-primary px-4 py-2 text-sm font-semibold text-primary">
                View details<span class="sr-only"> for {{ $item['name'] }}</span>
            </a>
        @endif
    </div>
</article>
