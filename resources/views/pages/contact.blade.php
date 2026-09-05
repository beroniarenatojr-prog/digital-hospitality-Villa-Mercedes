<x-customer-layout>
    @include('pages.partials.page-intro', [
        'eyebrow' => 'Contact',
        'title' => 'Visit or get in touch',
        'description' => 'Whether you are booking a stay, planning an event, or asking about TESDA training — we would like to hear from you.',
    ])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            <div class="bg-surface border border-border rounded-2xl p-6">
                <h2 class="font-display font-bold text-text mb-2">Location</h2>
                <p class="text-text-muted">{{ config('resort.location') }}</p>
            </div>
            <div class="bg-surface border border-border rounded-2xl p-6">
                <h2 class="font-display font-bold text-text mb-2">Phone</h2>
                <a href="{{ config('resort.phone_href') }}" class="text-primary hover:underline">{{ config('resort.phone') }}</a>
                <p class="mt-4 font-display font-bold text-text mb-2">Email</p>
                <a href="mailto:{{ config('resort.email') }}" class="text-primary hover:underline break-all">{{ config('resort.email') }}</a>
            </div>
            <div class="bg-surface border border-border rounded-2xl p-6">
                <h2 class="font-display font-bold text-text mb-2">Hours</h2>
                <p class="text-text-muted">{{ config('resort.hours') }}</p>
                <a href="{{ config('resort.facebook_url') }}" class="mt-4 inline-block text-primary font-medium hover:underline" target="_blank" rel="noopener noreferrer">
                    {{ config('resort.facebook_label') }}
                </a>
            </div>
        </div>

        <div class="mt-12 flex flex-wrap gap-4">
            <x-primary-button tag="a" href="{{ route('rooms') }}">
                Book a stay
            </x-primary-button>
            <x-secondary-button tag="a" href="{{ route('faq.index') }}">
                FAQ & support
            </x-secondary-button>
            @auth
                @if(!Auth::user()->hasRole('Administrator'))
                    <x-secondary-button tag="a" href="{{ route('customer.inquiries.index') }}">
                        Send an inquiry
                    </x-secondary-button>
                @endif
            @endauth
        </div>
    </div>
</x-customer-layout>
