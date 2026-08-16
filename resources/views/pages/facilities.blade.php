<x-customer-layout>
    <div class="bg-surface border-b border-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
            <h1 class="text-3xl md:text-4xl font-bold text-text">{{ $title }}</h1>
            @if(isset($description))
                <p class="mt-4 text-lg text-muted-foreground max-w-3xl">
                    {{ $description }}
                </p>
            @endif
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 pb-24">
        @if($facilities->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                @foreach($facilities as $facility)
                    <x-facility-card :facility="$facility" />
                @endforeach
            </div>
        @else
            <div class="py-12">
                <x-empty-state 
                    icon="home" 
                    title="No {{ strtolower($title) }} currently available" 
                    description="Check back later for available {{ strtolower($title) }}." 
                />
            </div>
        @endif
    </div>
</x-customer-layout>
