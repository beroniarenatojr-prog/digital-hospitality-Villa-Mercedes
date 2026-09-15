@php
    // Overlay routes float the header over a hero so the image leads.
    $overlay = request()->routeIs('home', 'rooms', 'halls', 'packages', 'cottages', 'pools', 'resort.*');
    $unreadNotifications = collect();
    $unreadNotificationCount = 0;

    $isAdministrator = Auth::check() && Auth::user()->hasRole('Administrator');
    $isCustomer = \App\Support\CustomerNav::isCustomer();
    $isAccountContext = \App\Support\CustomerNav::isAccountContext();
    $marketingLinks = \App\Support\CustomerNav::marketingLinks();
    $logoHref = $isAccountContext ? route('customer.reservations.index') : route('home');
    $logoLabel = $isAccountContext
        ? config('resort.name').' — My reservations'
        : config('resort.name').' — home';

    if ($isCustomer) {
        $unread = \App\Support\LayoutBadges::unreadNotifications(Auth::user());
        $unreadNotificationCount = $unread['count'];
        $unreadNotifications = $unread['items'];
    }
@endphp

<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-[60] focus:top-3 focus:left-3 focus:px-4 focus:py-2 focus:rounded-lg focus:bg-white focus:text-primary focus:font-semibold">
    Skip to main content
</a>

<header
    class="fixed inset-x-0 top-0 z-50 text-white transition-[background-color,box-shadow] duration-300 ease-out"
    x-data="{
        overlay: {{ $overlay ? 'true' : 'false' }},
        scrolled: false,
        open: false,
    }"
    x-init="scrolled = (window.scrollY || 0) > 16"
    x-bind:class="{
        'bg-primary-dark/45 backdrop-blur-md': overlay && !scrolled && !open,
        'bg-primary/95 backdrop-blur-md shadow-md': !overlay || scrolled || open,
    }"
    @scroll.window.passive="scrolled = (window.scrollY || 0) > 16"
    @keydown.escape.window="open = false"
    @resize.window="if (window.innerWidth >= 1024) open = false"
>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-x-3 sm:gap-x-4 min-h-20 h-20">
            <div class="shrink-0 flex items-center">
                <a href="{{ $logoHref }}" @unless($isAccountContext) data-intent-prefetch @endunless class="flex items-center gap-3" aria-label="{{ $logoLabel }}">
                    <img
                        src="{{ asset('images/logo-villa-mercedes-mark.png') }}"
                        alt=""
                        class="h-9 w-auto max-w-[3.5rem] sm:h-11 sm:max-w-[4.25rem] shrink-0 object-contain drop-shadow-[0_2px_10px_rgba(0,0,0,0.55)]"
                    >
                    <span class="hidden sm:flex flex-col justify-center leading-tight drop-shadow-[0_1px_8px_rgba(0,0,0,0.55)]">
                        <span class="font-display text-lg sm:text-xl font-bold text-white">{{ config('resort.short_name') }}</span>
                        <span class="hidden sm:block text-[11px] uppercase tracking-widest text-white/85">Nature Farm &amp; Resort</span>
                    </span>
                </a>
            </div>

            @if($isAccountContext)
                <nav class="hidden lg:flex flex-1 items-center justify-center gap-6" aria-label="Main">
                    <x-nav-link :href="route('customer.reservations.index')" :active="request()->routeIs('customer.reservations.*', 'customer.payments.*') && ! request()->routeIs('customer.reservations.new')" :on-dark="true">
                        {{ __('My reservations') }}
                    </x-nav-link>
                    <x-nav-link :href="route('customer.reservations.new')" :active="request()->routeIs('customer.reservations.new', 'customer.booking.*')" :on-dark="true">
                        {{ __('New Reservation') }}
                    </x-nav-link>
                    <x-nav-link :href="route('customer.inquiries.index')" :active="request()->routeIs('customer.inquiries.*')" :on-dark="true">My Inquiries</x-nav-link>
                </nav>
            @else
                <nav class="hidden lg:flex flex-1 items-center justify-center gap-5 xl:gap-7" aria-label="Main">
                    @foreach($marketingLinks as $link)
                        <x-nav-link :href="route($link['route'])" :active="$link['active']" :on-dark="true" data-intent-prefetch>
                            {{ $link['label'] }}
                        </x-nav-link>
                    @endforeach
                </nav>
            @endif

            <div class="ml-auto flex items-center gap-1 sm:gap-2.5">
                @auth
                    @if($isCustomer && ! $isAccountContext)
                        <a href="{{ route('customer.reservations.index') }}" class="hidden lg:inline text-sm font-medium {{ request()->routeIs('customer.reservations.*') ? 'text-accent-light' : 'text-white/80 hover:text-white' }} transition">
                            My reservations
                        </a>
                    @endif

                    @if($isCustomer)
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
                                            <a href="{{ route('customer.notifications.open', $notification->id) }}" class="pr-2 min-w-0 flex-1">
                                                <p class="text-sm font-medium text-text">{{ $notification->data['message'] ?? 'New notification' }}</p>
                                                <p class="text-xs text-text-muted mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                                            </a>
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
                                <span class="hidden sm:inline max-w-[9rem] truncate">{{ Auth::user()->name }}</span>
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            @if($isAdministrator)
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
                    <div class="hidden lg:flex items-center gap-1 sm:gap-2.5">
                        <a href="{{ route('login') }}" class="px-2 py-2 text-sm font-medium text-white/90 hover:text-white transition">Login</a>
                        <a href="{{ route('register') }}" class="inline-flex items-center justify-center min-h-[40px] px-5 py-2 rounded-full bg-accent text-sm font-semibold text-white shadow-sm hover:bg-accent/90 focus:outline-none focus:ring-2 focus:ring-accent-light focus:ring-offset-2 focus:ring-offset-primary transition">Register</a>
                    </div>
                @endauth

                <button
                    type="button"
                    class="lg:hidden inline-flex items-center justify-center p-2 rounded-lg text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/40"
                    @click="open = !open"
                    :aria-expanded="open.toString()"
                    aria-controls="mobile-navigation"
                    aria-label="Toggle navigation menu"
                >
                    <svg x-show="!open" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg x-show="open" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <div
            id="mobile-navigation"
            class="lg:hidden pb-5"
            x-show="open"
            x-cloak
            x-transition.origin.top
            role="navigation"
            aria-label="Mobile"
        >
            <div class="flex flex-col pt-2 border-t border-white/15">
                @if($isAccountContext)
                    <a href="{{ route('customer.reservations.index') }}" class="block py-3 text-base font-medium text-white/90 hover:text-white" @click="open = false">
                        {{ __('My reservations') }}
                    </a>
                    <a href="{{ route('customer.reservations.new') }}" class="block py-3 text-base font-medium text-white/90 hover:text-white" @click="open = false">
                        {{ __('New Reservation') }}
                    </a>
                    <a href="{{ route('customer.inquiries.index') }}" class="block py-3 text-base font-medium text-white/90" @click="open = false">My Inquiries</a>
                    <a href="{{ route('home') }}" class="block py-3 text-sm text-white/75">Browse the resort</a>
                @else
                    @foreach($marketingLinks as $link)
                        <a
                            href="{{ route($link['route']) }}"
                            class="block py-3 text-base font-medium {{ $link['active'] ? 'text-white' : 'text-white/90 hover:text-white' }}"
                            @if($link['active']) aria-current="page" @endif
                            data-intent-prefetch
                            @click="open = false"
                        >
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                @endif

                @guest
                    <div class="mt-2 pt-4 border-t border-white/20 flex flex-col gap-2">
                        <a href="{{ route('login') }}" class="block py-3 text-center text-base font-medium text-white/90 hover:text-white" @click="open = false">Login</a>
                        <a href="{{ route('register') }}" class="inline-flex items-center justify-center min-h-[44px] rounded-lg bg-accent px-5 py-3 text-base font-semibold text-white hover:bg-accent/90" @click="open = false">Register</a>
                    </div>
                @else
                    @if($isCustomer && ! $isAccountContext)
                        <a href="{{ route('customer.reservations.index') }}" class="mt-2 pt-4 border-t border-white/20 block py-3 text-base font-medium text-white/90 hover:text-white" @click="open = false">
                            My reservations
                        </a>
                    @endif
                @endguest
            </div>
        </div>
    </div>
</header>
