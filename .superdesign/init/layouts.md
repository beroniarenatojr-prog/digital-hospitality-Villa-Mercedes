# Shared layouts

## Customer layout
- Path: `resources/views/layouts/customer.blade.php`
- Public app shell: Vite, header, main, footer, back-to-top, chat.

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('resort.name', config('app.name')) }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-text antialiased bg-background selection:bg-primary selection:text-white flex flex-col min-h-screen">
        <x-customer-header />

        <!-- Page Content -->
        <main id="main-content" @class(['flex-grow', 'pt-16' => ! request()->routeIs('home', 'rooms', 'halls', 'packages')])>
            @if(session('success') || session('error'))
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 space-y-3">
                    @if(session('success'))
                        <x-alert type="success" title="All set">{{ session('success') }}</x-alert>
                    @endif
                    @if(session('error'))
                        <x-alert type="danger">{{ session('error') }}</x-alert>
                    @endif
                </div>
            @endif

            {{ $slot }}
        </main>

        <x-customer-footer />
        <x-back-to-top />

        <x-support-chat />

        @stack('scripts')
    </body>
</html>
```

## Customer header
- Path: `resources/views/components/customer-header.blade.php`
- Fixed overlay nav over listing/home heroes.

```blade
@php
    $stayActive = request()->routeIs('rooms') || request()->routeIs('packages') || request()->routeIs('halls');
    // On the homepage the header floats over the hero photo so the image leads.
    $overlay = request()->routeIs('home', 'rooms', 'halls', 'packages');
    $unreadNotifications = collect();
    $unreadNotificationCount = 0;

    if (Auth::check() && ! Auth::user()->hasRole('Administrator')) {
        // Query only the five messages shown in the menu, rather than loading
        // every unread notification on each public page request.
        $unreadNotificationCount = Auth::user()->unreadNotifications()->count();
        $unreadNotifications = Auth::user()->unreadNotifications()->latest()->limit(5)->get();
    }
@endphp

<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-[60] focus:top-3 focus:left-3 focus:px-4 focus:py-2 focus:rounded-lg focus:bg-white focus:text-primary focus:font-semibold">
    Skip to main content
</a>

<header
    class="fixed inset-x-0 top-0 z-50 text-white transition-[transform,background-color,box-shadow] duration-300 ease-out"
    x-data="{
        overlay: {{ $overlay ? 'true' : 'false' }},
        open: false,
        stay: false,
        scrolled: false,
        hidden: false,
        lastY: 0,
        onScroll() {
            const y = window.scrollY || 0;
            this.scrolled = y > 16;
            if (this.open) {
                this.hidden = false;
                this.lastY = y;
                return;
            }
            if (y <= 8) {
                this.hidden = false;
            } else if (y > this.lastY + 6) {
                this.hidden = true;
                this.stay = false;
            }
            this.lastY = y;
        }
    }"
    x-init="onScroll()"
    x-bind:class="{
        '-translate-y-full': hidden && !open,
        'bg-transparent': overlay && !scrolled && !open,
        'bg-primary/95 backdrop-blur-md shadow-md': !overlay || scrolled || open,
    }"
    @scroll.window.passive="onScroll()"
    @focusin.window="if ($event.target.closest('header')) hidden = false"
    @keydown.escape.window="open = false; stay = false"
>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('home') }}" data-intent-prefetch class="flex items-center gap-3" aria-label="{{ config('resort.name') }} — home">
                        <img
                            src="{{ asset('images/logo-villa-mercedes-mark.png') }}"
                            alt=""
                            class="h-11 w-auto max-w-[4.25rem] shrink-0 object-contain drop-shadow-[0_2px_10px_rgba(0,0,0,0.55)]"
                        >
                        <span class="flex flex-col justify-center leading-tight drop-shadow-[0_1px_8px_rgba(0,0,0,0.55)]">
                            <span class="font-display text-lg sm:text-xl font-bold text-white">{{ config('resort.short_name') }}</span>
                            <span class="hidden sm:block text-[11px] text-white/85">Nature Farm &amp; Resort</span>
                        </span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <nav class="hidden space-x-6 lg:-my-px lg:ms-10 lg:flex" aria-label="Main">
                    <x-nav-link :href="route('home')" :active="request()->routeIs('home')" :on-dark="true" data-intent-prefetch>
                        {{ __('Home') }}
                    </x-nav-link>
                    {{-- "Stay" groups the three bookable space types so the bar stays short. --}}
                    <div class="relative flex items-center" @click.outside="stay = false">
                        <button type="button" @click="stay = ! stay"
                                :aria-expanded="stay.toString()"
                                class="inline-flex items-center gap-1 px-1 pt-1 border-b-2 text-sm leading-5 focus:outline-none transition duration-150 ease-in-out {{ $stayActive ? 'border-white font-semibold text-white' : 'border-transparent font-medium text-white/80 hover:text-white hover:border-white/50' }}">
                            {{ __('Reserve a Space') }}
                            <svg class="w-4 h-4 transition-transform" :class="stay && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </button>

                        <div x-show="stay" x-cloak x-transition.opacity.duration.150ms
                             class="absolute top-full left-0 mt-1 w-72 rounded-xl bg-surface shadow-lg ring-1 ring-black/5 p-2"
                             @click="stay = false">
                            <a href="{{ route('rooms') }}" data-intent-prefetch class="block rounded-lg px-3 py-2.5 hover:bg-background focus:outline-none focus:bg-background">
                                <span class="block text-sm font-semibold text-text">Rooms &amp; Cottages</span>
                                <span class="block text-xs text-text-muted mt-0.5">Overnight and day-use stays</span>
                            </a>
                            <a href="{{ route('halls') }}" data-intent-prefetch class="block rounded-lg px-3 py-2.5 hover:bg-background focus:outline-none focus:bg-background">
                                <span class="block text-sm font-semibold text-text">Function Halls</span>
                                <span class="block text-xs text-text-muted mt-0.5">Birthdays, meetings, and events</span>
                            </a>
                            <a href="{{ route('packages') }}" data-intent-prefetch class="block rounded-lg px-3 py-2.5 hover:bg-background focus:outline-none focus:bg-background">
                                <span class="block text-sm font-semibold text-text">Resort Packages</span>
                                <span class="block text-xs text-text-muted mt-0.5">Bundled stays at one price</span>
                            </a>
                        </div>
                    </div>

                    <x-nav-link :href="route('gallery')" :active="request()->routeIs('gallery')" :on-dark="true" data-intent-prefetch>
                        {{ __('Gallery') }}
                    </x-nav-link>
                    <x-nav-link :href="route('about')" :active="request()->routeIs('about')" :on-dark="true" data-intent-prefetch>
                        {{ __('About') }}
                    </x-nav-link>
                    <x-nav-link :href="route('faq.index')" :active="request()->routeIs('faq.*')" :on-dark="true" data-intent-prefetch>
                        {{ __('FAQ') }}
                    </x-nav-link>
                    <x-nav-link :href="route('contact')" :active="request()->routeIs('contact')" :on-dark="true" data-intent-prefetch>
                        {{ __('Contact') }}
                    </x-nav-link>
                </nav>
            </div>

            <!-- Settings Dropdown or Login -->
            <div class="hidden lg:flex lg:items-center lg:ms-6 lg:space-x-3">
                @auth
                    @if(!Auth::user()->hasRole('Administrator'))
                        <a href="{{ route('customer.reservations.index') }}" class="text-sm font-medium {{ request()->routeIs('customer.reservations.*') ? 'text-white' : 'text-white/80 hover:text-white' }} transition">
                            My reservations
                        </a>

                        <x-dropdown align="right" width="80">
                            <x-slot name="trigger">
                                <button class="relative p-2 rounded-lg text-white/80 hover:text-white hover:bg-white/10 focus:outline-none transition ease-in-out duration-150" aria-label="Notifications">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                    </svg>
                                    @if($unreadNotificationCount > 0)
                                        <span class="absolute top-0.5 right-0.5 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-danger rounded-full">
                                            {{ $unreadNotificationCount }}
                                            <span class="sr-only">unread notifications</span>
                                        </span>
                                    @endif
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <div class="px-4 py-3 border-b border-border">
                                    <p class="text-sm font-semibold text-text">Notifications</p>
                                </div>
                                <div class="max-h-64 overflow-y-auto">
                                    @forelse($unreadNotifications as $notification)
                                        <div class="px-4 py-3 border-b border-border hover:bg-background flex justify-between items-start">
                                            <div class="pr-2">
                                                <p class="text-sm font-medium text-text">{{ $notification->data['message'] ?? 'New notification' }}</p>
                                                <p class="text-xs text-text-muted mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                                            </div>
                                            <form action="{{ route('customer.notifications.mark-as-read', $notification->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="text-xs text-primary hover:text-primary-dark p-1" title="Mark as read">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                    <span class="sr-only">Mark as read</span>
                                                </button>
                                            </form>
                                        </div>
                                    @empty
                                        <div class="px-4 py-6 text-sm text-center text-text-muted">
                                            You're all caught up.
                                        </div>
                                    @endforelse
                                </div>
                                <div class="px-4 py-2 text-center bg-background">
                                    <a href="{{ route('customer.notifications.index') }}" class="text-xs font-medium text-primary hover:text-primary-dark">View all notifications</a>
                                </div>
                            </x-slot>
                        </x-dropdown>
                    @endif

                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-white hover:bg-white/10 focus:outline-none transition ease-in-out duration-150">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-white/20 text-white text-xs font-bold">
                                    {{ Str::upper(Str::substr(Auth::user()->name, 0, 1)) }}
                                </span>
                                <span class="max-w-[9rem] truncate">{{ Auth::user()->name }}</span>
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            @if(Auth::user()->hasRole('Administrator'))
                                <x-dropdown-link :href="route('admin.dashboard')">
                                    {{ __('Admin Dashboard') }}
                                </x-dropdown-link>
                            @else
                                <x-dropdown-link :href="route('customer.reservations.index')">
                                    {{ __('My reservations') }}
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('customer.inquiries.index')">
                                    {{ __('My Inquiries') }}
                                </x-dropdown-link>
                            @endif

                            <x-dropdown-link :href="route('profile.edit')">
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            <!-- Authentication -->
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf

                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault();
                                                    this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                @else
                    <a href="{{ route('login') }}" class="px-3 py-2 rounded-lg text-sm font-medium text-white/90 hover:text-white hover:bg-white/10 transition">Log in</a>
                @endauth
            </div>

            <!-- Hamburger -->
            <div class="flex items-center gap-2 lg:hidden">
                <button @click="open = ! open"
                        class="inline-flex items-center justify-center p-2 rounded-lg text-white hover:bg-white/10 focus:outline-none focus:bg-white/10 transition duration-150 ease-in-out"
                        :aria-expanded="open.toString()"
                        aria-label="Toggle navigation menu">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div x-show="open" x-cloak class="lg:hidden bg-surface border-t border-border shadow-lg absolute w-full z-40 max-h-[calc(100vh-4rem)] overflow-y-auto">
        <div class="py-3">
            <p class="px-4 pb-1 text-xs font-semibold uppercase tracking-wider text-text-muted">Reserve a space</p>
            <x-responsive-nav-link :href="route('rooms')" :active="request()->routeIs('rooms')" data-intent-prefetch>
                {{ __('Rooms & Cottages') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('halls')" :active="request()->routeIs('halls')" data-intent-prefetch>
                {{ __('Function Halls') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('packages')" :active="request()->routeIs('packages')" data-intent-prefetch>
                {{ __('Resort Packages') }}
            </x-responsive-nav-link>

            <p class="px-4 pt-4 pb-1 text-xs font-semibold uppercase tracking-wider text-text-muted">Explore</p>
            <x-responsive-nav-link :href="route('home')" :active="request()->routeIs('home')" data-intent-prefetch>
                {{ __('Home') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('gallery')" :active="request()->routeIs('gallery')" data-intent-prefetch>
                {{ __('Gallery') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('about')" :active="request()->routeIs('about')" data-intent-prefetch>
                {{ __('About') }}
            </x-responsive-nav-link>

            <p class="px-4 pt-4 pb-1 text-xs font-semibold uppercase tracking-wider text-text-muted">Help</p>
            <x-responsive-nav-link :href="route('faq.index')" :active="request()->routeIs('faq.*')" data-intent-prefetch>
                {{ __('FAQ') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('contact')" :active="request()->routeIs('contact')" data-intent-prefetch>
                {{ __('Contact') }}
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="py-3 border-t border-border">
            @auth
                <div class="px-4 pb-2">
                    <div class="font-semibold text-base text-text">{{ Auth::user()->name }}</div>
                    <div class="text-sm text-text-muted">{{ Auth::user()->email }}</div>
                </div>

                <div class="space-y-1">
                    @if(Auth::user()->hasRole('Administrator'))
                        <x-responsive-nav-link :href="route('admin.dashboard')">
                            {{ __('Admin Dashboard') }}
                        </x-responsive-nav-link>
                    @else
                        <x-responsive-nav-link :href="route('customer.reservations.index')" :active="request()->routeIs('customer.reservations.*')">
                            {{ __('My reservations') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('customer.inquiries.index')">
                            {{ __('My Inquiries') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('customer.notifications.index')" class="justify-between">
                            {{ __('Notifications') }}
                            @if($unreadNotificationCount > 0)
                                <span class="inline-flex items-center justify-center px-2 py-0.5 ms-2 text-xs font-bold text-white bg-danger rounded-full">
                                    {{ $unreadNotificationCount }}
                                </span>
                            @endif
                        </x-responsive-nav-link>
                    @endif

                    <x-responsive-nav-link :href="route('profile.edit')">
                        {{ __('Profile') }}
                    </x-responsive-nav-link>

                    <!-- Authentication -->
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-responsive-nav-link :href="route('logout')"
                                onclick="event.preventDefault();
                                            this.closest('form').submit();">
                            {{ __('Log Out') }}
                        </x-responsive-nav-link>
                    </form>
                </div>
            @else
                <div class="px-4 flex flex-col gap-3">
                    <x-secondary-button tag="a" href="{{ route('login') }}" class="w-full">
                        Log in
                    </x-secondary-button>
                    <p class="text-sm text-text-muted text-center pb-1">
                        New here? <a href="{{ route('register') }}" class="text-primary font-medium hover:underline">Create an account</a>
                    </p>
                </div>
            @endauth
        </div>
    </div>
</header>
```

## Customer footer
- Path: `resources/views/components/customer-footer.blade.php`
- Dark four-column footer.

```blade
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
                    <li><a href="{{ route('home') }}" class="hover:text-white transition">Home</a></li>
                    <li><a href="{{ route('about') }}" class="hover:text-white transition">About us</a></li>
                    <li><a href="{{ route('gallery') }}" class="hover:text-white transition">Gallery</a></li>
                    <li><a href="{{ route('contact') }}" class="hover:text-white transition">Contact</a></li>
                    <li><a href="{{ route('faq.index') }}" class="hover:text-white transition">FAQ</a></li>
                    <li><a href="{{ route('login') }}" class="hover:text-white transition">Log in</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold mb-4">Reserve a space</h4>
                <ul class="space-y-2 text-sm text-white/75">
                    <li><a href="{{ route('rooms') }}" class="hover:text-white transition">Rooms &amp; cottages</a></li>
                    <li><a href="{{ route('halls') }}" class="hover:text-white transition">Event hosting</a></li>
                    <li><a href="{{ route('packages') }}" class="hover:text-white transition">Resort packages</a></li>
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
```

## Availability bar
- Path: `resources/views/components/availability-bar.blade.php`
- Hero/listing booking desk form.

```blade
@props(['variant' => 'hero'])

@php
    // Dates and guests travel as query params so the booking wizard can prefill them.
    // Nothing here searches inventory: the bar only routes to a listing.
    $spaces = [
        ['label' => 'Stay — rooms & cottages', 'url' => route('rooms'), 'route' => 'rooms'],
        ['label' => 'Event — function halls', 'url' => route('halls'), 'route' => 'halls'],
        ['label' => 'Package — day, night, overnight', 'url' => route('packages'), 'route' => 'packages'],
    ];

    $current = collect($spaces)->firstWhere('route', request()->route()?->getName());
    $target = $current['url'] ?? route('rooms');

    $today = now()->format('Y-m-d');
    $checkIn = request('check_in');
    $checkOut = request('check_out');
    $guests = request('guests');

    $onHero = $variant === 'hero';
    $fieldClasses = 'w-full rounded-lg border-border bg-surface text-text text-sm shadow-sm focus:border-primary focus:ring-primary';
    $labelClasses = 'block text-xs font-semibold uppercase tracking-wide text-text-muted mb-1.5';
@endphp

<form
    method="GET"
    action="{{ $target }}"
    x-data="{ target: '{{ $target }}' }"
    x-bind:action="target"
    @class([
        'rounded-2xl',
        'bg-surface/95 backdrop-blur-md p-4 sm:p-5 shadow-2xl ring-1 ring-black/5' => $onHero,
        'bg-surface border border-border p-4 shadow-sm' => ! $onHero,
    ])
>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_auto_auto] gap-3 lg:gap-4 lg:items-end">
        <div>
            <label for="availability-space" class="{{ $labelClasses }}">What do you need?</label>
            {{-- No name attribute: this only picks the destination, it is not a filter. --}}
            <select id="availability-space" x-model="target" class="{{ $fieldClasses }}">
                @foreach($spaces as $space)
                    <option value="{{ $space['url'] }}">{{ $space['label'] }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="availability-check-in" class="{{ $labelClasses }}">Check in</label>
            <input
                type="date"
                id="availability-check-in"
                name="check_in"
                value="{{ $checkIn }}"
                min="{{ $today }}"
                class="{{ $fieldClasses }}"
            >
        </div>

        <div>
            <label for="availability-check-out" class="{{ $labelClasses }}">Check out</label>
            <input
                type="date"
                id="availability-check-out"
                name="check_out"
                value="{{ $checkOut }}"
                min="{{ $today }}"
                class="{{ $fieldClasses }}"
            >
        </div>

        <div class="lg:w-24">
            <label for="availability-guests" class="{{ $labelClasses }}">Guests</label>
            <input
                type="number"
                id="availability-guests"
                name="guests"
                value="{{ $guests }}"
                min="1"
                inputmode="numeric"
                placeholder="2"
                class="{{ $fieldClasses }}"
            >
        </div>

        <x-primary-button class="w-full lg:w-auto !px-6 !py-3 !text-base whitespace-nowrap">
            Check availability
        </x-primary-button>
    </div>

    @if(request()->filled('min_capacity'))
        <input type="hidden" name="min_capacity" value="{{ request('min_capacity') }}">
    @endif
    @if(request()->filled('sort') && request('sort') !== 'recommended')
        <input type="hidden" name="sort" value="{{ request('sort') }}">
    @endif

    @if($onHero)
        <p class="mt-3 text-xs text-text-muted text-center sm:text-left">
            Checking prices and availability is free. You only pay after your reservation is confirmed.
        </p>
    @endif
</form>
```
