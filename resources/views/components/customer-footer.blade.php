@php
    $isAccountContext = \App\Support\CustomerNav::isAccountContext();
    $isCustomer = \App\Support\CustomerNav::isCustomer();
@endphp

<footer class="bg-primary text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-14">
        <div class="grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <a href="{{ $isAccountContext ? route('customer.home') : route('home') }}" class="inline-flex items-center gap-3">
                    <img src="{{ asset('images/logo-villa-mercedes.png') }}" alt="" class="h-12 w-auto rounded-md">
                    <span class="font-heading text-lg font-semibold leading-tight">{{ config('resort.short_name') }}</span>
                </a>
                <p class="mt-4 text-sm leading-relaxed text-white/75">
                    {{ config('resort.tagline') }} {{ config('resort.location') }}.
                </p>
            </div>

            <div>
                <h4 class="text-xs font-semibold uppercase tracking-[0.16em] text-white/90">Reservations</h4>
                <ul class="mt-4 space-y-2 text-sm text-white/75">
                    @if($isAccountContext)
                        <li><a href="{{ route('customer.grouped-booking.workspace') }}" class="transition hover:text-white">New reservation</a></li>
                        <li><a href="{{ route('customer.reservations.index') }}" class="transition hover:text-white">My reservations</a></li>
                        <li><a href="{{ route('customer.grouped-booking.workspace', ['type' => 'cottage']) }}" class="transition hover:text-white">Day-use cottages</a></li>
                    @else
                        <li><a href="{{ route('rooms') }}" class="transition hover:text-white">Rooms &amp; suites</a></li>
                        <li><a href="{{ route('cottages') }}" class="transition hover:text-white">Day-use cottages</a></li>
                        <li><a href="{{ route('packages') }}" class="transition hover:text-white">Resort packages</a></li>
                        <li><a href="{{ route('halls') }}" class="transition hover:text-white">Event booking</a></li>
                    @endif
                </ul>
            </div>

            <div>
                <h4 class="text-xs font-semibold uppercase tracking-[0.16em] text-white/90">Resort info</h4>
                <ul class="mt-4 space-y-2 text-sm text-white/75">
                    <li><a href="{{ route('about') }}" class="transition hover:text-white">About</a></li>
                    <li><a href="{{ route('resort.show') }}" class="transition hover:text-white">Amenities</a></li>
                    <li><a href="{{ route('faq.index') }}" class="transition hover:text-white">FAQs</a></li>
                    <li><a href="{{ route('gallery') }}" class="transition hover:text-white">Gallery</a></li>
                    @unless($isAccountContext)
                        <li><a href="{{ route('reviews') }}" class="transition hover:text-white">Reviews</a></li>
                        <li><a href="{{ route('terms') }}" class="transition hover:text-white">Terms of Use</a></li>
                        <li><a href="{{ route('privacy') }}" class="transition hover:text-white">Privacy Notice</a></li>
                    @endunless
                    @if($isAccountContext)
                        <li><a href="{{ route('home') }}" class="transition hover:text-white">Browse the resort</a></li>
                        <li><a href="{{ route('profile.edit') }}" class="transition hover:text-white">Guest account</a></li>
                    @elseif($isCustomer)
                        <li><a href="{{ route('customer.reservations.index') }}" class="transition hover:text-white">My reservations</a></li>
                    @elseif(! Auth::check())
                        <li><a href="{{ route('login') }}" class="transition hover:text-white">Log in</a></li>
                    @endif
                </ul>
            </div>

            <div>
                <h4 class="text-xs font-semibold uppercase tracking-[0.16em] text-white/90">Connect</h4>
                <ul class="mt-4 space-y-3 text-sm text-white/75">
                    <li>
                        <a href="mailto:{{ config('resort.email') }}" class="break-all transition hover:text-white">{{ config('resort.email') }}</a>
                    </li>
                    <li>
                        <a href="{{ config('resort.phone_href') }}" class="transition hover:text-white">{{ config('resort.phone') }}</a>
                    </li>
                    <li>{{ config('resort.location') }}</li>
                </ul>
                <x-social-links size="sm" class="mt-5" />
            </div>
        </div>

        <div class="mt-10 flex flex-col gap-3 border-t border-white/15 pt-6 text-sm text-white/60 sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ date('Y') }} {{ config('resort.name') }}. All rights reserved.</p>
            <div class="flex flex-wrap gap-x-5 gap-y-2">
                @if(config('resort.instagram_url'))
                    <a href="{{ config('resort.instagram_url') }}" class="transition hover:text-white" target="_blank" rel="noopener noreferrer">Instagram</a>
                @endif
                @if(config('resort.facebook_url'))
                    <a href="{{ config('resort.facebook_url') }}" class="transition hover:text-white" target="_blank" rel="noopener noreferrer">Facebook</a>
                @endif
                <a href="{{ route('contact') }}" class="transition hover:text-white">Contact</a>
            </div>
        </div>
    </div>
</footer>
