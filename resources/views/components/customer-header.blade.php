@php
    // Overlay routes float the header over a hero so the image leads.
    $overlay = request()->routeIs('home', 'rooms', 'halls', 'packages', 'cottages', 'pools', 'resort.*');
    $unreadNotificationCount = 0;

    $isAdministrator = Auth::check() && Auth::user()->hasRole('Administrator');
    $isCustomer = \App\Support\CustomerNav::isCustomer();
    $isAccountContext = \App\Support\CustomerNav::isAccountContext();
    $marketingLinks = \App\Support\CustomerNav::marketingLinks();
    $logoHref = $isAccountContext ? route('customer.home') : route('home');
    $logoLabel = $isAccountContext
        ? config('resort.name').' — My reservations'
        : config('resort.name').' — home';

    if ($isCustomer) {
        $unreadNotificationCount = \App\Support\LayoutBadges::unreadNotifications(Auth::user())['count'];
    }
@endphp

<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-[60] focus:top-3 focus:left-3 focus:px-4 focus:py-2 focus:rounded-lg focus:bg-white focus:text-primary focus:font-semibold">
    Skip to main content
</a>

<header
    data-customer-header
    class="fixed inset-x-0 top-0 z-50 text-white transition-[background-color,box-shadow] duration-300 ease-out"
    x-data="{
        overlay: {{ $overlay ? 'true' : 'false' }},
        scrolled: false,
        open: false,
    }"
    @keydown.escape.window="open = false"
    @click.outside="open = false"
    x-init="scrolled = (window.scrollY || 0) > 16"
    x-bind:class="{
        'bg-primary/45 backdrop-blur-md': overlay && !scrolled && !open,
        'bg-primary/95 backdrop-blur-md shadow-md': !overlay || scrolled || open,
    }"
    @scroll.window.passive="scrolled = (window.scrollY || 0) > 16"
    @resize.window="if (window.innerWidth >= 1024) open = false"
>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 xl:px-8">
        <div class="relative flex items-center gap-x-3 sm:gap-x-4 min-h-20 h-20">
            <div class="flex min-w-0 shrink items-center">
                <a href="{{ $logoHref }}" @unless($isAccountContext) data-intent-prefetch @endunless class="flex min-w-0 items-center gap-2 sm:gap-3" aria-label="{{ $logoLabel }}">
                    <img
                        src="{{ asset('images/logo-villa-mercedes-mark.png') }}"
                        alt=""
                        class="h-8 w-auto max-w-[3rem] sm:h-11 sm:max-w-[4.25rem] shrink-0 object-contain drop-shadow-[0_2px_10px_rgba(0,0,0,0.55)]"
                    >
                    <span class="flex min-w-0 flex-col justify-center leading-tight drop-shadow-[0_1px_8px_rgba(0,0,0,0.55)]">
                        <span class="whitespace-nowrap font-display text-sm font-bold text-white sm:text-xl">{{ config('resort.short_name') }}</span>
                        <span class="whitespace-nowrap text-[8px] uppercase tracking-[0.12em] text-white/85 sm:text-[11px] sm:tracking-widest">Nature Farm &amp; Resort</span>
                    </span>
                </a>
            </div>

            @if(\App\Support\CustomerNav::showsAccountNav())
                <x-customer.account-nav />
            @elseif(! $isAccountContext)
                <nav class="customer-marketing-navigation absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 items-center gap-4" aria-label="Main">
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
                        <a href="{{ route('customer.reservations.index') }}" class="customer-desktop-utility text-sm font-medium {{ request()->routeIs('customer.reservations.*') ? 'text-accent-light' : 'text-white/80 hover:text-white' }} transition">
                            My reservations
                        </a>
                    @endif

                    <div @class(['customer-desktop-account' => $isCustomer])>
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button aria-label="Account menu" :aria-expanded="open.toString()" aria-haspopup="true" class="inline-flex min-h-11 items-center gap-2.5 px-2.5 py-2 text-sm font-semibold text-white hover:text-white/80 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60 transition ease-in-out duration-150">
                                <span class="relative inline-flex items-center justify-center w-9 h-9 shrink-0 rounded-full bg-white/20 text-white text-sm font-semibold">
                                    {{ Str::upper(Str::substr(Auth::user()->name, 0, 1)) }}
                                    @if($isCustomer && $unreadNotificationCount > 0)
                                        <span class="absolute -right-2 -top-2 inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-danger px-1.5 text-[11px] font-bold leading-none text-white shadow-lg ring-2 ring-white">
                                            {{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}
                                            <span class="sr-only">unread notifications</span>
                                        </span>
                                    @endif
                                </span>
                                <span class="hidden sm:inline max-w-[9rem] truncate">{{ Auth::user()->name }}</span>
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            @if($isAdministrator)
                                <x-dropdown-link :href="route('admin.dashboard')">
                                    {{ __('Admin Dashboard') }}
                                </x-dropdown-link>
                            @endif

                            <x-dropdown-link :href="route('profile.edit')">
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            @if($isCustomer)
                                <a href="{{ route('customer.notifications.index') }}" class="flex w-full items-center justify-between gap-3 px-4 py-2 text-start text-sm leading-5 text-text transition duration-150 ease-in-out hover:bg-muted focus:bg-muted focus:outline-none">
                                    <span class="inline-flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                        </svg>
                                        {{ __('Notifications') }}
                                    </span>
                                    @if($unreadNotificationCount > 0)
                                        <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-danger px-1.5 text-[11px] font-bold leading-none text-white shadow-sm ring-2 ring-surface">
                                            {{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}
                                            <span class="sr-only">unread</span>
                                        </span>
                                    @endif
                                </a>
                            @endif

                            <x-confirm-sign-out>
                                <x-slot:trigger>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button
                                            type="submit"
                                            @click.prevent="ask()"
                                            aria-haspopup="dialog"
                                            x-bind:aria-expanded="open ? 'true' : 'false'"
                                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-text transition duration-150 ease-in-out hover:bg-muted focus:bg-muted focus:outline-none"
                                        >
                                            {{ __('Log Out') }}
                                        </button>
                                    </form>
                                </x-slot:trigger>
                            </x-confirm-sign-out>
                        </x-slot>
                    </x-dropdown>
                    </div>
                @else
                    <div class="customer-guest-utility items-center gap-1 sm:gap-2.5">
                        <a href="{{ route('login') }}" class="px-2 py-2 text-sm font-medium text-white/90 hover:text-white transition">Login</a>
                        <a href="{{ route('register') }}" class="inline-flex min-h-[40px] items-center justify-center rounded-lg bg-background px-5 py-2 text-sm font-semibold text-primary shadow-sm transition hover:bg-primary-light focus:outline-none focus:ring-2 focus:ring-background focus:ring-offset-2 focus:ring-offset-primary">Register</a>
                    </div>
                @endauth

                @if(\App\Support\CustomerNav::showsAccountNav() || ! $isAccountContext)
                    <button
                        type="button"
                        @class([
                            'inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg p-2 text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/40',
                            'xl:hidden' => \App\Support\CustomerNav::showsAccountNav(),
                            'customer-marketing-menu-toggle' => ! \App\Support\CustomerNav::showsAccountNav(),
                        ])
                        @click="open = !open"
                        :aria-expanded="open.toString()"
                        aria-controls="{{ \App\Support\CustomerNav::showsAccountNav() ? 'account-navigation' : 'mobile-navigation' }}"
                        aria-label="Toggle navigation menu"
                    >
                        <svg x-show="!open" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg x-show="open" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        @unless($isAccountContext)
            <div
                id="mobile-navigation"
                @class([
                    'pb-5 max-h-[calc(100dvh-5rem)] overflow-y-auto',
                    'xl:hidden' => \App\Support\CustomerNav::showsAccountNav(),
                    'customer-mobile-navigation' => ! \App\Support\CustomerNav::showsAccountNav(),
                ])
                x-show="open"
                x-cloak
                x-transition.origin.top
                role="navigation"
                aria-label="Mobile"
            >
                <div class="flex flex-col pt-2 border-t border-white/15">
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

                    @guest
                        <div class="mt-2 pt-4 border-t border-white/20 flex flex-col gap-2">
                            <a href="{{ route('login') }}" class="block py-3 text-center text-base font-medium text-white/90 hover:text-white" @click="open = false">Login</a>
                            <a href="{{ route('register') }}" class="inline-flex items-center justify-center min-h-[44px] rounded-lg bg-background px-5 py-3 text-base font-semibold text-primary transition hover:bg-primary-light focus:outline-none focus:ring-2 focus:ring-background focus:ring-offset-2 focus:ring-offset-primary" @click="open = false">Register</a>
                        </div>
                    @else
                        @if($isCustomer && ! $isAccountContext)
                            <div class="mt-2 border-t border-white/20 pt-2">
                                <a href="{{ route('customer.reservations.index') }}" class="block py-3 text-base font-medium text-white/90 hover:text-white" @click="open = false">
                                    My reservations
                                </a>
                                <a href="{{ route('customer.notifications.index') }}" class="flex min-h-11 items-center gap-2 py-3 text-base font-medium text-white/90 hover:text-white" @click="open = false">
                                    Notifications
                                    @if($unreadNotificationCount > 0)
                                        <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-danger px-1.5 text-[11px] font-bold leading-none text-white shadow-sm ring-2 ring-white/90">
                                            {{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}
                                            <span class="sr-only">unread notifications</span>
                                        </span>
                                    @endif
                                </a>
                                <a href="{{ route('profile.edit') }}" class="block py-3 text-base font-medium text-white/90 hover:text-white" @click="open = false">
                                    Profile
                                </a>
                                <x-confirm-sign-out>
                                    <x-slot:trigger>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button
                                                type="submit"
                                                @click.prevent="ask()"
                                                aria-haspopup="dialog"
                                                x-bind:aria-expanded="open ? 'true' : 'false'"
                                                class="flex min-h-11 w-full items-center py-3 text-left text-base font-medium text-white/90 hover:text-white"
                                            >
                                                Log out
                                            </button>
                                        </form>
                                    </x-slot:trigger>
                                </x-confirm-sign-out>
                            </div>
                        @endif
                    @endguest
                </div>
            </div>
        @endunless
    </div>
</header>
