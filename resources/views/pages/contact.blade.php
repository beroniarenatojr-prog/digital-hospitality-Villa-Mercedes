<x-customer-layout>
    @php
        $mapsQuery = rawurlencode(config('resort.maps_query'));
    @endphp

    @include('pages.partials.page-intro', [
        'eyebrow' => 'Contact',
        'title' => 'Contact Information',
        'description' => 'Whether you are reserving a stay or planning an event — we would like to hear from you.',
    ])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="grid lg:grid-cols-2 gap-12 items-start">
            <div class="space-y-8">
                <div class="flex gap-4">
                    <span class="shrink-0 inline-flex items-center justify-center w-12 h-12 rounded-full bg-primary text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    </span>
                    <div>
                        <h2 class="font-bold text-text">Location</h2>
                        <p class="mt-1 text-text-muted">{{ config('resort.location') }}</p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <span class="shrink-0 inline-flex items-center justify-center w-12 h-12 rounded-full bg-primary text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                    </span>
                    <div>
                        <h2 class="font-bold text-text">Contact</h2>
                        <a href="{{ config('resort.phone_href') }}" class="mt-1 block text-text-muted hover:text-primary">{{ config('resort.phone') }}</a>
                        <a href="mailto:{{ config('resort.email') }}" class="block text-text-muted hover:text-primary break-all">{{ config('resort.email') }}</a>
                    </div>
                </div>
                <div class="flex gap-4">
                    <span class="shrink-0 inline-flex items-center justify-center w-12 h-12 rounded-full bg-primary text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </span>
                    <div>
                        <h2 class="font-bold text-text">Operating Hours</h2>
                        <p class="mt-1 text-text-muted">{{ config('resort.hours') }}</p>
                    </div>
                </div>
                <div>
                    <h3 class="font-bold text-text">Connect With Us</h3>
                    <x-social-links class="mt-3" />
                </div>
            </div>

            <div class="rounded-3xl overflow-hidden border border-border min-h-[280px] bg-muted">
                <iframe
                    title="Map of {{ config('resort.name') }}"
                    src="https://maps.google.com/maps?q={{ $mapsQuery }}&amp;z=15&amp;output=embed"
                    class="w-full h-[360px] border-0"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                ></iframe>
            </div>
        </div>

        <div class="mt-12 flex flex-wrap gap-4">
            <x-primary-button tag="a" href="{{ route('rooms') }}">
                Book your stay / services
            </x-primary-button>
            <x-secondary-button tag="a" href="{{ route('faq.index') }}">
                FAQ & support
            </x-secondary-button>
            <x-secondary-button tag="a" href="{{ config('resort.messenger_url') ?: config('resort.facebook_url') }}" target="_blank" rel="noopener noreferrer">
                Message us on Facebook
            </x-secondary-button>
            <p class="w-full text-sm text-text-muted">Opens Facebook or Messenger in a new tab. Replies arrive there.</p>
        </div>
    </div>
</x-customer-layout>
