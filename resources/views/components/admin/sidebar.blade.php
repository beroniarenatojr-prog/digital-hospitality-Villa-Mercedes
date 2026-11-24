@php
    $admin = auth()->user();
    $initials = collect(preg_split('/\s+/', trim((string) $admin?->name)))
        ->filter()
        ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');

    // The destinations themselves live in App\Support\Admin\AdminNav, where the
    // test that holds them against the router can read them.
    $groups = \App\Support\Admin\AdminNav::groups();
@endphp

<aside
    id="admin-sidebar"
    x-ref="sidebar"
    :class="{ 'is-open': drawer, 'is-rail': rail }"
    :inert="mobile && !drawer"
    class="admin-sidebar"
    aria-label="Back office"
>
    <div class="flex h-16 shrink-0 items-center gap-2.5 border-b border-border bg-primary px-3">
        <a
            href="{{ route('admin.dashboard') }}"
            class="admin-nav-link min-w-0 flex-1 gap-2.5 px-1 py-1 text-white hover:bg-white/10 hover:text-white focus-visible:ring-white focus-visible:ring-offset-primary"
        >
            <img src="{{ asset('images/logo-villa-mercedes.png') }}" alt="" class="h-9 w-auto shrink-0 object-contain">
            <span class="rail-hide min-w-0">
                <span class="block truncate text-xs font-semibold uppercase tracking-wide text-white/90">Villa Mercedes</span>
                <span class="block truncate text-[11px] font-medium text-white/70">Back office</span>
            </span>
        </a>
        <button
            type="button"
            data-drawer-close
            @click="closeDrawer()"
            class="rail-hide shrink-0 rounded-lg p-2 text-white/80 hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-primary lg:hidden"
        >
            <span class="sr-only">Close navigation</span>
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
            </svg>
        </button>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Back office sections">
        @foreach ($groups as $group)
            @if ($group['heading'])
                <p class="rail-hide px-2.5 pb-1.5 pt-4 text-xs font-bold uppercase tracking-wider text-text-muted">
                    {{ $group['heading'] }}
                </p>
                <span class="rail-separator mx-2 mb-2 border-t border-border" aria-hidden="true"></span>
            @endif

            @foreach ($group['items'] as $item)
                @php
                    $patterns = explode('||', $item['pattern']);
                    $isActive = collect($patterns)->contains(fn (string $pattern) => request()->routeIs($pattern));
                @endphp

                <x-admin.nav-link
                    :href="route($item['route'])"
                    :active="$isActive"
                    :icon="$item['icon']"
                    :badge="$item['badge'] ?? null"
                    :badge-tone="$item['badge_tone'] ?? null"
                >
                    {{ $item['label'] }}
                </x-admin.nav-link>
            @endforeach
        @endforeach
    </nav>

    @if ($admin)
        <div class="shrink-0 border-t border-border p-3">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-light text-sm font-semibold text-primary" aria-hidden="true">
                    {{ $initials ?: 'A' }}
                </span>
                <span class="rail-hide min-w-0">
                    <span class="block truncate text-sm font-medium text-text">{{ $admin->name }}</span>
                    <span class="block truncate text-xs text-text-muted">{{ $admin->email }}</span>
                </span>
            </div>

            <div class="rail-hide mt-3 flex gap-2">
                <a
                    href="{{ route('profile.edit') }}"
                    class="flex-1 rounded-lg border border-border px-2 py-1.5 text-center text-xs font-medium text-text-muted transition-colors duration-150 hover:bg-muted hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                >
                    Profile
                </a>
                <x-admin.sign-out />
            </div>

            {{-- Rail mode keeps the same two destinations as icon-only controls so
                 they never simply disappear. --}}
            <div class="rail-only mt-3 flex-col items-center gap-1">
                <a
                    href="{{ route('profile.edit') }}"
                    class="rounded-lg p-2 text-text-muted hover:bg-muted hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    aria-label="Your profile"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </a>
                <x-admin.sign-out variant="rail" />
            </div>
        </div>
    @endif
</aside>
