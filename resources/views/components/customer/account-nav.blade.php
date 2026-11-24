@php
    $sections = \App\Support\CustomerNav::accountSections();
    $unreadCount = \App\Support\LayoutBadges::unreadNotifications(auth()->user())['count'];
@endphp

<nav id="account-navigation" class="customer-account-navigation" :class="{ 'is-open': open }" aria-label="Account">
    <ul class="flex flex-col xl:flex-row xl:items-center xl:gap-1">
        @foreach($sections as $section)
            @php $isActive = $section['active']; @endphp
            <li class="shrink-0">
                <a href="{{ route($section['route']) }}"
                   @if($isActive) aria-current="page" @endif
                   @class([
                       'flex min-h-11 items-center border-b-2 px-3 py-3 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-white',
                       'border-white text-white' => $isActive,
                       'border-transparent text-white/90 hover:text-white' => ! $isActive,
                   ])>
                    {{ $section['label'] }}
                </a>
            </li>
        @endforeach
        <li class="shrink-0 xl:hidden">
            <a href="{{ route('profile.edit') }}"
               @if(request()->routeIs('profile.*')) aria-current="page" @endif
               @class([
                   'flex min-h-11 items-center px-3 py-3 text-sm font-semibold text-white/90 transition hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-white',
                   'text-white' => request()->routeIs('profile.*'),
               ])>
                Profile
            </a>
        </li>
        <li class="shrink-0 xl:hidden">
            <a href="{{ route('customer.notifications.index') }}"
               @if(request()->routeIs('customer.notifications.*')) aria-current="page" @endif
               @class([
                   'flex min-h-11 items-center gap-2 px-3 py-3 text-sm font-semibold text-white/90 transition hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-white',
                   'text-white' => request()->routeIs('customer.notifications.*'),
               ])>
                Notifications
                @if($unreadCount > 0)
                    <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-danger px-1.5 text-[11px] font-bold leading-none text-white shadow-sm ring-2 ring-white/90">
                        {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                        <span class="sr-only">unread notifications</span>
                    </span>
                @endif
            </a>
        </li>
        <li class="shrink-0 xl:hidden">
            <x-confirm-sign-out>
                <x-slot:trigger>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            @click.prevent="ask()"
                            aria-haspopup="dialog"
                            x-bind:aria-expanded="open ? 'true' : 'false'"
                            class="flex min-h-11 w-full items-center rounded-lg px-3 py-3 text-left text-sm font-semibold text-white/90 transition hover:bg-white/10 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
                        >
                            Log out
                        </button>
                    </form>
                </x-slot:trigger>
            </x-confirm-sign-out>
        </li>
    </ul>
</nav>
