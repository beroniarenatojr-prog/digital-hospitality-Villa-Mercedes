@php
    $pendingReservationCount = \App\Support\LayoutBadges::pendingReservationCount();
    $admin = auth()->user();
    $initials = collect(preg_split('/\s+/', trim((string) $admin?->name)))
        ->filter()
        ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');
@endphp

<aside
    class="admin-sidebar"
    :class="{ 'is-collapsed': !sidebarOpen }"
>
    <div class="admin-sidebar-inner">
        <div class="h-16 flex items-center justify-between px-4 border-b border-gray-200 bg-primary">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 text-white font-bold tracking-wider">
                <img src="{{ asset('images/logo-villa-mercedes.png') }}" alt="" class="h-10 w-auto object-contain">
                <span class="text-sm">ADMIN</span>
            </a>
            <button @click="sidebarOpen = false" type="button" class="p-1.5 text-white/80 hover:text-white hover:bg-white/10 rounded-md focus:outline-none focus:ring-2 focus:ring-white/40">
                <span class="sr-only">Close sidebar</span>
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                </svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto py-4">
            <nav class="px-4 space-y-1">
                <x-admin.nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                    </x-slot:icon>
                    Dashboard
                </x-admin.nav-link>

                <x-admin.nav-link :href="route('admin.decision-board')" :active="request()->routeIs('admin.decision-board')">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    </x-slot:icon>
                    Decision Board
                </x-admin.nav-link>

                <div class="pt-4 pb-2">
                    <p class="px-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Operations</p>
                </div>

                <x-admin.nav-link :href="route('admin.reservations.index')" :active="request()->routeIs('admin.reservations.*')" :badge="$pendingReservationCount">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    </x-slot:icon>
                    Reservations
                </x-admin.nav-link>

                <x-admin.nav-link :href="route('admin.payments.index')" :active="request()->routeIs('admin.payments.*')">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                    </x-slot:icon>
                    Payment review
                </x-admin.nav-link>

                <x-admin.nav-link :href="route('admin.payment-destinations.index')" :active="request()->routeIs('admin.payment-destinations.*')">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h2v2h-2zM19 14h2v2h-2zM14 19h2v2h-2zM19 19h2v2h-2z" /></svg>
                    </x-slot:icon>
                    Resort QR accounts
                </x-admin.nav-link>
                <x-admin.nav-link :href="route('admin.reviews.index')" :active="request()->routeIs('admin.reviews.*')">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-2.9-5.6 2.9 1.1-6.2L3 9.6l6.2-.9L12 3z" /></svg>
                    </x-slot:icon>
                    Guest reviews
                </x-admin.nav-link>

                <div class="pt-4 pb-2">
                    <p class="px-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Resort Management</p>
                </div>

                <x-admin.nav-link :href="route('admin.resort-services.edit')" :active="request()->routeIs('admin.resort-services.*')">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 15c1.5-1 2.5-1 4 0s2.5 1 4 0 2.5-1 4 0 2.5 1 4 0M4 19c1.5-1 2.5-1 4 0s2.5 1 4 0 2.5-1 4 0 2.5 1 4 0M12 11V4"/></svg>
                    </x-slot:icon>
                    Resort Services
                </x-admin.nav-link>

                <x-admin.nav-link :href="route('admin.facilities.index')" :active="request()->routeIs('admin.facilities.*')">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                    </x-slot:icon>
                    Facilities
                </x-admin.nav-link>

                <x-admin.nav-link :href="route('admin.rates.index')" :active="request()->routeIs('admin.rates.*')">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" /></svg>
                    </x-slot:icon>
                    Rates
                </x-admin.nav-link>

                <x-admin.nav-link :href="route('admin.photos.index')" :active="request()->routeIs('admin.photos.*')">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    </x-slot:icon>
                    Photos
                </x-admin.nav-link>

                <div class="pt-4 pb-2">
                    <p class="px-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Support</p>
                </div>

                <x-admin.nav-link :href="route('admin.inquiries.index')" :active="request()->routeIs('admin.inquiries.*')">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    </x-slot:icon>
                    Inquiries
                </x-admin.nav-link>

                <x-admin.nav-link :href="route('admin.faqs.index')" :active="request()->routeIs('admin.faqs.*') || request()->routeIs('admin.faq-categories.*')">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </x-slot:icon>
                    FAQs
                </x-admin.nav-link>

                <div class="pt-4 pb-2">
                    <p class="px-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Reports</p>
                </div>

                <x-admin.nav-link :href="route('admin.reports.index')" :active="request()->routeIs('admin.reports.*')">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    </x-slot:icon>
                    Reports
                </x-admin.nav-link>

                <div class="pt-4 pb-2">
                    <p class="px-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">System</p>
                </div>

                <x-admin.nav-link :href="route('admin.activity-log.index')" :active="request()->routeIs('admin.activity-log.*')">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </x-slot:icon>
                    Activity Log
                </x-admin.nav-link>
            </nav>
        </div>

        @if($admin)
            <div class="border-t border-gray-200 p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">
                        {{ $initials ?: 'A' }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-900">{{ $admin->name }}</p>
                        <p class="truncate text-xs text-gray-500">{{ $admin->email }}</p>
                    </div>
                </div>
                <div class="mt-3 flex gap-2">
                    <a href="{{ route('profile.edit') }}" class="flex-1 rounded-md border border-gray-200 px-2 py-1.5 text-center text-xs font-medium text-gray-600 hover:bg-gray-50">Profile</a>
                    <form method="POST" action="{{ route('logout') }}" class="flex-1">
                        @csrf
                        <button type="submit" class="w-full rounded-md border border-gray-200 px-2 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                            Log out
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</aside>
