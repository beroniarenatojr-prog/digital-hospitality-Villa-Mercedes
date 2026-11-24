@php
    $unread = \App\Support\LayoutBadges::unreadNotifications(auth()->user());
    $unreadNotificationCount = $unread['count'];
    $unreadNotifications = $unread['items'];
    $pageTitle = trim((string) ($header ?? ''));
@endphp

<header class="sticky top-0 z-20 border-b border-border bg-surface">
    <div class="mx-auto flex h-16 w-full max-w-[88rem] items-center gap-3 px-4 sm:px-6 lg:px-8">
        {{-- Drawer toggle: mobile only. --}}
        <button
            type="button"
            @click="openDrawer()"
            x-ref="drawerToggle"
            class="-ml-2 shrink-0 min-h-11 min-w-11 rounded-lg p-2 text-text-muted hover:bg-muted hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 lg:hidden"
            aria-controls="admin-sidebar"
            :aria-expanded="drawer ? 'true' : 'false'"
        >
            <span class="sr-only">Open navigation</span>
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        {{-- Rail toggle: desktop only. --}}
        <button
            type="button"
            @click="rail = ! rail"
            class="hidden shrink-0 min-h-11 min-w-11 rounded-lg p-2 text-text-muted hover:bg-muted hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 lg:inline-flex"
            aria-controls="admin-sidebar"
            :aria-expanded="rail ? 'false' : 'true'"
        >
            <span class="sr-only" x-text="rail ? 'Expand navigation labels' : 'Collapse navigation to icons'">Collapse navigation to icons</span>
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 6h16M4 12h10M4 18h16" />
            </svg>
        </button>

        <p class="min-w-0 shrink truncate text-base font-semibold text-text">
            {{ $pageTitle !== '' ? $pageTitle : 'Administration' }}
        </p>

        <div class="ml-auto flex items-center gap-2 sm:gap-3">
            {{-- One scoped lookup, named for what it searches. Everything else
                 lives in the filter panel on the list screen it belongs to. --}}
            <form method="GET" action="{{ route('admin.reservations.index') }}" class="hidden min-w-0 lg:block" role="search">
                <label for="admin-header-search" class="sr-only">Search reservations by code, guest name, or email</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex w-10 items-center justify-center text-text-muted">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
                        </svg>
                    </span>
                    <input
                        id="admin-header-search"
                        type="search"
                        name="search"
                        enterkeyhint="search"
                        value="{{ request()->routeIs('admin.reservations.*') ? request('search') : '' }}"
                        placeholder="Search reservations"
                        class="h-10 w-56 rounded-lg border-border bg-muted py-0 pe-3 ps-10 text-sm text-text placeholder:text-text-muted focus:border-primary focus:bg-surface focus:ring-primary xl:w-72"
                    >
                </div>
            </form>

            <div x-data="{ open: false }" @keydown.escape="open = false" @click.outside="open = false" class="relative">
                <button
                    type="button"
                    @click="open = ! open"
                    class="relative min-h-11 min-w-11 rounded-lg p-2 text-text-muted hover:bg-muted hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    aria-haspopup="true"
                    aria-controls="admin-notifications"
                    :aria-expanded="open ? 'true' : 'false'"
                >
                    <span class="sr-only">
                        Notifications{{ $unreadNotificationCount > 0 ? ', '.$unreadNotificationCount.' unread' : '' }}
                    </span>
                    <x-admin.nav-icon name="notifications" class="h-6 w-6" />
                    @if ($unreadNotificationCount > 0)
                        <span class="absolute -right-1 -top-1 inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-danger px-1.5 text-[11px] font-bold leading-none text-white shadow-lg ring-2 ring-surface tabular-nums" aria-hidden="true">
                            {{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}
                        </span>
                    @endif
                </button>

                <div
                    id="admin-notifications"
                    x-cloak
                    x-show="open"
                    x-transition.opacity.duration.150ms
                    class="fixed inset-x-4 top-16 z-50 mt-2 sm:absolute sm:inset-x-auto sm:top-auto sm:right-0 w-80 max-w-[calc(100vw-2rem)] origin-top-right overflow-hidden rounded-xl border border-border bg-surface shadow-xl"
                >
                    <div class="flex items-center justify-between gap-3 border-b border-border px-4 py-2.5">
                        <span class="text-sm font-semibold text-text">Notifications</span>
                        @if ($unreadNotificationCount > 0)
                            <form action="{{ route('admin.notifications.mark-all-as-read') }}" method="POST">
                                @csrf
                                <button type="submit" class="rounded text-xs font-medium text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                    Mark all read
                                </button>
                            </form>
                        @endif
                    </div>

                    <div class="max-h-80 divide-y divide-border overflow-y-auto">
                        @forelse ($unreadNotifications as $notification)
                            <a href="{{ route('admin.notifications.open', $notification->id) }}" class="block px-4 py-3 hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary">
                                <span class="block text-sm font-medium text-text">{{ $notification->data['message'] ?? 'New notification' }}</span>
                                <span class="mt-1 block text-xs text-text-muted">{{ $notification->created_at->diffForHumans() }}</span>
                            </a>
                        @empty
                            <p class="px-4 py-6 text-center text-sm text-text-muted">Nothing needs your attention right now.</p>
                        @endforelse
                    </div>

                    <div class="border-t border-border px-4 py-2.5 text-center">
                        <a href="{{ route('admin.notifications.index') }}" class="text-xs font-medium text-primary hover:text-primary-dark">View all notifications</a>
                    </div>

                    {{-- Front Desk Shift Alert Controls (Audio Chime & Desktop Push) --}}
                    <div x-data="adminAlertSettings" class="border-t border-border bg-muted/40 px-4 py-2.5 text-xs">
                        <p class="font-semibold text-text-muted mb-2">Shift Alerts</p>
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-text">Audio chime</span>
                            <button
                                type="button"
                                @click="toggleSound()"
                                class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                :class="sound ? 'bg-primary' : 'bg-muted'"
                                role="switch"
                                :aria-checked="sound.toString()"
                            >
                                <span class="sr-only">Toggle shift alert audio chime</span>
                                <span
                                    aria-hidden="true"
                                    class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                    :class="sound ? 'translate-x-4' : 'translate-x-0'"
                                ></span>
                            </button>
                        </div>

                        <template x-if="supported">
                            <div class="mt-2 flex items-center justify-between gap-4">
                                <span class="text-text">Desktop notifications</span>
                                <button
                                    type="button"
                                    @click="toggleDesktop()"
                                    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                    :class="desktop ? 'bg-secondary' : 'bg-muted'"
                                    role="switch"
                                    :aria-checked="desktop.toString()"
                                >
                                    <span class="sr-only">Toggle desktop push notifications</span>
                                    <span
                                        aria-hidden="true"
                                        class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                        :class="desktop ? 'translate-x-4' : 'translate-x-0'"
                                    ></span>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Below sm this is icon-only so the label can never wrap, with the
                 name carried for assistive tech and on hover. --}}
            <a
                href="{{ route('admin.reservations.create') }}"
                title="New walk-in reservation"
                class="inline-flex min-h-[44px] shrink-0 items-center gap-1.5 rounded-lg bg-primary px-3 text-sm font-semibold text-white hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 sm:px-4"
            >
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 5v14M5 12h14" />
                </svg>
                <span class="hidden sm:inline">New walk-in</span>
                <span class="sr-only sm:hidden">New walk-in reservation</span>
            </a>
        </div>
    </div>
</header>
