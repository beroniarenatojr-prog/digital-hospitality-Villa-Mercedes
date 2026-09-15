<x-customer-layout>
    @include('pages.partials.page-intro', [
        'title' => 'Customer Reviews',
        'description' => $reviews->isNotEmpty()
            ? 'Notes left by guests after a stay, celebration, or day at the pools.'
            : 'Customer reviews will appear here after customers share a review from their account.',
    ])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        @if($reviews->isNotEmpty())
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($reviews as $review)
                    <x-review-quote :review="$review" />
                @endforeach
            </div>
        @endif

        <div class="mt-14 text-center">
            <p class="text-text-muted">Ready to plan your own stay?</p>
            <div class="mt-5 flex flex-col sm:flex-row justify-center gap-3">
                <x-primary-button tag="a" href="{{ route('home') }}#availability" class="!px-8 !py-3">
                    Browse Availability
                </x-primary-button>
                <a href="{{ route('rooms') }}" class="inline-flex items-center justify-center min-h-[44px] px-8 py-3 rounded-lg border border-primary text-primary font-semibold hover:bg-primary-light">
                    Services
                </a>
            </div>
        </div>
    </div>
</x-customer-layout>
