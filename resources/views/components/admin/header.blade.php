<header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
    <div class="flex h-16 items-center gap-3 px-4">
        <button x-show="!sidebarOpen" @click="sidebarOpen = true" type="button" class="shrink-0 text-gray-500 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary" style="display: none;">
            <span class="sr-only">Open sidebar</span>
            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        <div class="hidden min-w-0 shrink-0 md:block">
            @if(isset($header))
                <h1 class="truncate text-xl font-semibold text-gray-900">{{ $header }}</h1>
            @endif
        </div>

        <form method="GET" action="{{ route('admin.reservations.index') }}" class="mx-auto hidden min-w-0 max-w-xl flex-1 sm:block">
            <label for="admin-header-search" class="sr-only">Search reservations</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center text-gray-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" /></svg>
                </span>
                <input
                    id="admin-header-search"
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search reservation ID or guest..."
                    class="h-10 w-full rounded-lg border-gray-200 bg-gray-50 py-0 ps-11 pe-3 text-sm text-gray-900 shadow-sm focus:border-primary focus:bg-white focus:ring-primary"
                >
            </div>
        </form>

        <div class="ml-auto flex items-center gap-3">
            @php
                $unread = \App\Support\LayoutBadges::unreadNotifications(auth()->user());
                $unreadNotificationCount = $unread['count'];
                $unreadNotifications = $unread['items'];
            @endphp
            <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                <button @click="open = !open" type="button" class="relative rounded-full bg-white p-1 text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                    <span class="sr-only">View notifications</span>
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    @if($unreadNotificationCount > 0)
                        <span class="absolute top-0 right-0 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white transform translate-x-1/4 -translate-y-1/4 bg-danger rounded-full">
                            {{ $unreadNotificationCount }}
                        </span>
                    @endif
                </button>

                <div x-show="open" x-transition.opacity style="display: none;" class="origin-top-right absolute right-0 mt-2 w-80 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
                    <div class="py-1">
                        <div class="px-4 py-2 border-b border-gray-100 flex justify-between items-center gap-3">
                            <span class="font-semibold text-gray-700 text-sm">Notifications</span>
                            @if($unreadNotificationCount > 0)
                                <form action="{{ route('admin.notifications.mark-all-as-read') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="text-xs font-medium text-primary hover:text-primary-dark">Mark all read</button>
                                </form>
                            @endif
                        </div>
                        <div class="max-h-64 overflow-y-auto">
                            @forelse($unreadNotifications as $notification)
                                <a href="{{ route('admin.notifications.open', $notification->id) }}" class="block px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 border-b border-gray-50">
                                    <div class="font-medium">{{ $notification->data['message'] ?? 'New notification' }}</div>
                                    <div class="text-xs text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</div>
                                </a>
                            @empty
                                <div class="px-4 py-6 text-sm text-center text-gray-500">
                                    No new notifications
                                </div>
                            @endforelse
                        </div>
                        <div class="px-4 py-2 border-t border-gray-100 text-center">
                            <a href="{{ route('admin.notifications.index') }}" class="text-xs font-medium text-primary hover:text-primary-dark">View all notifications</a>
                        </div>
                    </div>
                </div>
            </div>

            <a href="{{ route('admin.reservations.index', ['walk_in' => 1]) }}" class="inline-flex min-h-[40px] items-center rounded-lg bg-primary px-3 text-sm font-semibold text-white hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                + Walk-in
            </a>
        </div>
    </div>
</header>
