@if($featuredRooms->isNotEmpty())
    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        @foreach($featuredRooms as $facility)
            <x-featured-facility-card :facility="$facility" />
        @endforeach
    </div>
@else
    <div class="rounded-3xl border border-dashed border-border bg-surface px-6 py-10 text-center">
        <p class="font-semibold text-text">More rooms are being prepared.</p>
        <p class="mt-2 text-sm text-text-muted">Browse all accommodations or contact us for the latest availability.</p>
        <a href="{{ route('rooms') }}" class="mt-5 inline-flex font-semibold text-primary hover:underline">View accommodations &rarr;</a>
    </div>
@endif
