<x-customer-layout>
    {{-- Hero Section --}}
    <div class="relative bg-surface border-b border-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 lg:py-32 flex flex-col items-center text-center">
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold text-primary tracking-tight mb-6">
                Welcome to Villa Mercedes
            </h1>
            <p class="text-lg md:text-xl text-muted-foreground max-w-3xl mb-10">
                Experience nature, comfort, and relaxation at our premium farm and resort. Whether you're looking for a peaceful getaway or planning a grand event, we have the perfect space for you.
            </p>
            <div class="flex gap-4">
                <x-primary-button tag="a" href="{{ route('rooms') }}">
                    Browse Rooms
                </x-primary-button>
                <x-secondary-button tag="a" href="{{ route('packages') }}">
                    View Resort Packages
                </x-secondary-button>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-24">
        
        {{-- Featured Rooms --}}
        <section>
            <div class="flex justify-between items-end mb-8">
                <div>
                    <h2 class="text-3xl font-bold text-text">Featured Rooms</h2>
                    <p class="text-muted-foreground mt-2">Comfortable accommodations for your stay.</p>
                </div>
                <a href="{{ route('rooms') }}" class="text-primary font-medium hover:underline flex items-center gap-1 hidden sm:flex">
                    View all rooms
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </a>
            </div>
            
            @if($featuredRooms->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($featuredRooms as $facility)
                        <x-facility-card :facility="$facility" />
                    @endforeach
                </div>
                <div class="mt-6 sm:hidden">
                    <x-secondary-button tag="a" href="{{ route('rooms') }}" class="w-full justify-center">
                        View all rooms
                    </x-secondary-button>
                </div>
            @else
                <x-empty-state 
                    icon="home" 
                    title="No rooms currently available" 
                    description="Check back later for available room accommodations." 
                />
            @endif
        </section>

        {{-- Featured Halls --}}
        <section>
            <div class="flex justify-between items-end mb-8">
                <div>
                    <h2 class="text-3xl font-bold text-text">Function Halls</h2>
                    <p class="text-muted-foreground mt-2">Elegant spaces for your special events and gatherings.</p>
                </div>
                <a href="{{ route('halls') }}" class="text-primary font-medium hover:underline flex items-center gap-1 hidden sm:flex">
                    View all halls
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </a>
            </div>
            
            @if($featuredHalls->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($featuredHalls as $facility)
                        <x-facility-card :facility="$facility" />
                    @endforeach
                </div>
                <div class="mt-6 sm:hidden">
                    <x-secondary-button tag="a" href="{{ route('halls') }}" class="w-full justify-center">
                        View all halls
                    </x-secondary-button>
                </div>
            @else
                <x-empty-state 
                    icon="sparkles" 
                    title="No halls currently available" 
                    description="Our function halls are currently being updated." 
                />
            @endif
        </section>

        {{-- Featured Packages --}}
        <section>
            <div class="flex justify-between items-end mb-8">
                <div>
                    <h2 class="text-3xl font-bold text-text">Resort Packages</h2>
                    <p class="text-muted-foreground mt-2">Curated experiences combining our best services.</p>
                </div>
                <a href="{{ route('packages') }}" class="text-primary font-medium hover:underline flex items-center gap-1 hidden sm:flex">
                    View all packages
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </a>
            </div>
            
            @if($featuredPackages->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($featuredPackages as $facility)
                        <x-facility-card :facility="$facility" />
                    @endforeach
                </div>
                <div class="mt-6 sm:hidden">
                    <x-secondary-button tag="a" href="{{ route('packages') }}" class="w-full justify-center">
                        View all packages
                    </x-secondary-button>
                </div>
            @else
                <x-empty-state 
                    icon="ticket" 
                    title="No packages currently available" 
                    description="Check back later for exciting new resort packages." 
                />
            @endif
        </section>

        {{-- Support CTA --}}
        <section class="bg-surface border border-border rounded-2xl p-8 md:p-12 text-center shadow-sm">
            <h2 class="text-2xl font-bold text-text mb-4">Have Questions?</h2>
            <p class="text-muted-foreground max-w-2xl mx-auto mb-8">
                Need help planning your visit or understanding our packages? Check out our frequently asked questions or get in touch with our customer support team.
            </p>
            <x-secondary-button tag="a" href="{{ route('faq.index') }}">
                Visit FAQ & Support
            </x-secondary-button>
        </section>

    </div>
</x-customer-layout>
