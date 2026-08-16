<x-customer-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-12 pb-24">
        
        {{-- Breadcrumb --}}
        <nav class="flex mb-6 text-sm text-muted-foreground" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ route('home') }}" class="inline-flex items-center hover:text-text transition-colors">
                        Home
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-4 h-4 text-muted-foreground mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        @php
                            $listRoute = match($facility->facilityType->slug) {
                                'room' => 'rooms',
                                'function-hall' => 'halls',
                                'resort-package' => 'packages',
                                default => 'home'
                            };
                        @endphp
                        <a href="{{ route($listRoute) }}" class="hover:text-text transition-colors">
                            {{ $facility->facilityType->name }}s
                        </a>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 text-muted-foreground mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        <span class="text-text font-medium">{{ $facility->name }}</span>
                    </div>
                </li>
            </ol>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 xl:gap-12">
            
            {{-- Main Content --}}
            <div class="lg:col-span-2 space-y-10">
                
                {{-- Header & Image --}}
                <div class="space-y-6">
                    <div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-secondary text-text mb-3">{{ $facility->facilityType->name }}</span>
                        <h1 class="text-3xl md:text-4xl lg:text-5xl font-extrabold text-text tracking-tight">
                            {{ $facility->name }}
                        </h1>
                    </div>

                    <div class="relative w-full h-[300px] md:h-[400px] rounded-2xl overflow-hidden bg-muted flex items-center justify-center text-muted-foreground border border-border shadow-sm">
                        @if($facility->galleryImages->count() > 0)
                            <img src="{{ Storage::url($facility->galleryImages->first()->path) }}" alt="{{ $facility->name }}" class="w-full h-full object-cover" />
                        @else
                            <svg class="w-16 h-16 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <span class="absolute text-sm font-medium opacity-50 uppercase tracking-widest mt-24">No Image Available</span>
                        @endif
                    </div>
                </div>

                {{-- Overview --}}
                <section>
                    <h2 class="text-2xl font-bold text-text mb-4">Overview</h2>
                    <p class="text-lg text-muted-foreground leading-relaxed whitespace-pre-wrap">{{ $facility->description }}</p>
                </section>

                <hr class="border-border">

                {{-- Amenities / Inclusions --}}
                @if($facility->amenities->count() > 0)
                <section>
                    <h2 class="text-2xl font-bold text-text mb-6">Amenities / Inclusions</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach($facility->amenities as $amenity)
                            <div class="flex items-start gap-3 bg-surface p-4 rounded-xl border border-border">
                                <svg class="w-5 h-5 text-primary mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                <span class="text-text font-medium">{{ $amenity->name }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
                @endif

                {{-- Included Bookable Resources (Package Components) --}}
                @if($facility->packageComponents->count() > 0)
                <section>
                    <h2 class="text-2xl font-bold text-text mb-6">Included Resources</h2>
                    <p class="text-muted-foreground mb-4">This package reserves the following facilities for your exclusive use:</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach($facility->packageComponents as $component)
                            <div class="flex items-center gap-4 bg-surface p-4 rounded-xl border border-border">
                                <div class="w-12 h-12 rounded-lg bg-muted flex items-center justify-center shrink-0">
                                    <svg class="w-6 h-6 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                </div>
                                <div>
                                    <h4 class="font-bold text-text">{{ $component->componentFacility->name }}</h4>
                                    <p class="text-sm text-muted-foreground capitalize">{{ $component->componentFacility->facilityType->name }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
                @endif

            </div>

            {{-- Sidebar (Booking & Pricing CTA) --}}
            <div class="lg:col-span-1">
                <div class="sticky top-24">
                    <x-card class="p-6 md:p-8 border-primary/20 shadow-xl relative overflow-hidden">
                        {{-- Background Accent --}}
                        <div class="absolute top-0 right-0 w-32 h-32 bg-primary/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                        
                        <div class="relative z-10">
                            <h3 class="text-xl font-bold text-text mb-6">Booking Details</h3>
                            
                            <div class="space-y-4 mb-8">
                                <div class="flex items-center justify-between pb-4 border-b border-border">
                                    <span class="text-muted-foreground flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                        Standard Capacity
                                    </span>
                                    <span class="font-medium text-text">{{ $facility->capacity }} pax</span>
                                </div>
                                <div class="flex items-center justify-between pb-4 border-b border-border">
                                    <span class="text-muted-foreground flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                        Maximum Capacity
                                    </span>
                                    <span class="font-medium text-text">{{ $facility->effectiveMaxCapacity() }} pax</span>
                                </div>
                            </div>

                            <div class="mb-8 bg-background p-4 rounded-xl border border-border">
                                <p class="text-sm text-muted-foreground uppercase tracking-wide font-medium mb-1">Starting from</p>
                                @php
                                    $baseRate = $facility->rates->firstWhere('type', \App\Enums\RateType::Base);
                                @endphp
                                @if($baseRate)
                                    <div class="flex items-baseline gap-1.5 flex-wrap">
                                        <x-price-display :amount="$baseRate->amount" class="text-3xl font-extrabold text-primary" />
                                        <span class="text-muted-foreground font-medium">/ {{ $baseRate->unit }}</span>
                                    </div>
                                    <p class="text-xs text-muted-foreground mt-2 italic">Note: Final price may vary based on selected dates and seasonal rates.</p>
                                @else
                                    <span class="text-lg font-semibold text-text">Rate unavailable</span>
                                @endif
                            </div>

                            {{-- Booking Flow is incomplete, so we provide a disabled/informational CTA per requirements --}}
                            <div class="space-y-3">
                                <button type="button" disabled class="w-full inline-flex justify-center items-center px-4 py-3 bg-muted border border-transparent rounded-lg font-bold text-muted-foreground uppercase tracking-widest cursor-not-allowed">
                                    Online Booking Coming Soon
                                </button>
                                <p class="text-xs text-center text-muted-foreground">
                                    For manual reservations, please contact our support desk.
                                </p>
                            </div>
                        </div>
                    </x-card>
                </div>
            </div>

        </div>
    </div>
</x-customer-layout>

