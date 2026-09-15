@php
    $isAccountContext = \App\Support\CustomerNav::isAccountContext();
    $isCustomer = \App\Support\CustomerNav::isCustomer();
@endphp

@if($isAccountContext)
<footer class="bg-primary-dark text-white">
    <div class="max-w-7xl mx-auto px-4 py-8 flex flex-wrap gap-x-8 gap-y-3 text-sm">
        <a href="{{ route('customer.reservations.index') }}">My Reservations</a>
        <a href="{{ route('customer.inquiries.index') }}">Contact staff</a>
        <a href="{{ route('profile.edit') }}">My Account</a>
        <a href="{{ route('home') }}">Browse the resort</a>
        <a href="{{ config('resort.phone_href') }}">{{ config('resort.phone') }}</a>
        <span>© {{ date('Y') }} {{ config('resort.short_name') }}</span>
    </div>
</footer>
@else
<footer class="bg-primary-dark text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10">
            <div>
                <a href="{{ route('home') }}" class="inline-flex items-center gap-3">
                    <img src="{{ asset('images/logo-villa-mercedes.png') }}" alt="" class="h-12 w-auto rounded-md">
                    <span class="text-lg font-semibold leading-tight">{{ config('resort.short_name') }}</span>
                </a>
                <p class="mt-4 text-sm text-white/75 leading-relaxed">
                    {{ config('resort.tagline') }} {{ config('resort.location') }}.
                </p>
                <x-social-links size="sm" class="mt-4" />
            </div>
            <div>
                <h4 class="font-semibold mb-4">Quick Links</h4>
                <ul class="space-y-2 text-sm text-white/75">
                    <li>
                        <a href="{{ route('home') }}" class="hover:text-white transition">
                            {{ $isAccountContext ? 'Browse the resort' : 'Home' }}
                        </a>
                    </li>
                    <li><a href="{{ route('gallery') }}" class="hover:text-white transition">Gallery</a></li>
                    <li><a href="{{ route('faq.index') }}" class="hover:text-white transition">FAQs</a></li>
                    <li><a href="{{ route('reviews') }}" class="hover:text-white transition">Reviews</a></li>
                    <li><a href="{{ route('about') }}" class="hover:text-white transition">About</a></li>
                    <li><a href="{{ route('contact') }}" class="hover:text-white transition">Contact</a></li>
                    <li><a href="{{ route('terms') }}" class="hover:text-white transition">Terms of Use</a></li>
                    <li><a href="{{ route('privacy') }}" class="hover:text-white transition">Privacy Notice</a></li>
                    @if($isCustomer)
                        <li><a href="{{ route('customer.reservations.index') }}" class="hover:text-white transition">My reservations</a></li>
                    @elseif(! Auth::check())
                        <li><a href="{{ route('login') }}" class="hover:text-white transition">Log in</a></li>
                    @endif
                </ul>
            </div>
            <div>
                <h4 class="font-semibold mb-4">Services</h4>
                <ul class="space-y-2 text-sm text-white/75">
                    <li><a href="{{ route('rooms') }}" class="hover:text-white transition">Services</a></li>
                    <li><a href="{{ route('rooms') }}" class="hover:text-white transition">Rooms</a></li>
                    <li><a href="{{ route('resort.show') }}" class="hover:text-white transition">Resort, pools &amp; cottages</a></li>
                    <li><a href="{{ route('halls') }}" class="hover:text-white transition">Event hosting</a></li>
                    <li><a href="{{ route('packages') }}" class="hover:text-white transition">Packages</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold mb-4">Contact Info</h4>
                <ul class="space-y-4 text-sm text-white/75">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-full bg-primary text-white">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        </span>
                        <span>{{ config('resort.location') }}</span>
                    </li>
                    <li class="flex gap-3 items-center">
                        <span class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-full bg-primary text-white">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                        </span>
                        <a href="{{ config('resort.phone_href') }}" class="hover:text-white">{{ config('resort.phone') }}</a>
                    </li>
                    <li class="flex gap-3 items-center">
                        <span class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-full bg-primary text-white">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                        </span>
                        <a href="mailto:{{ config('resort.email') }}" class="hover:text-white break-all">{{ config('resort.email') }}</a>
                    </li>
                </ul>
            </div>
        </div>
        <p class="mt-12 pt-6 border-t border-white/15 text-sm text-white/60">
            &copy; {{ date('Y') }} {{ config('resort.name') }}. All rights reserved.
        </p>
    </div>
</footer>
@endif
