@props(['facility'])

<x-card class="flex flex-col h-full overflow-hidden transition-shadow duration-300 hover:shadow-lg">
    {{-- Image Placeholder --}}
    <div class="relative w-full h-48 bg-muted flex items-center justify-center text-muted-foreground">
        @if($facility->galleryImages->count() > 0)
            <img src="{{ Storage::url($facility->galleryImages->first()->image_path) }}" alt="{{ $facility->name }}" class="w-full h-full object-cover" />
        @else
            <svg class="w-12 h-12 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        @endif
    </div>

    {{-- Content --}}
    <div class="flex flex-col flex-grow p-4">
        <h3 class="text-xl font-bold text-text">{{ $facility->name }}</h3>
        
        <p class="text-sm text-muted-foreground mt-2 flex-grow line-clamp-2">
            {{ $facility->description }}
        </p>

        <div class="mt-4 flex flex-wrap gap-2 text-sm text-muted-foreground">
            <div class="flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                Up to {{ $facility->effectiveMaxCapacity() }} pax
            </div>
            
            @if($facility->amenities->count() > 0)
            <div class="flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                {{ $facility->amenities->take(2)->pluck('name')->join(', ') }}
                @if($facility->amenities->count() > 2)
                    +{{ $facility->amenities->count() - 2 }} more
                @endif
            </div>
            @endif
        </div>

        <div class="mt-6 flex items-end justify-between">
            <div>
                <p class="text-xs text-muted-foreground uppercase tracking-wide">Starting from</p>
                @php
                    $baseRate = $facility->rates->first();
                @endphp
                @if($baseRate)
                    <div class="flex items-baseline gap-1">
                        <x-price-display :amount="$baseRate->amount" class="text-lg text-primary" />
                        <span class="text-sm text-muted-foreground">/ {{ $baseRate->unit }}</span>
                    </div>
                @else
                    <span class="text-sm font-semibold text-muted-foreground">Rate unavailable</span>
                @endif
            </div>
            
            <x-primary-button tag="a" href="{{ route('facilities.show', $facility) }}">
                View Details
            </x-primary-button>
        </div>
    </div>
</x-card>
