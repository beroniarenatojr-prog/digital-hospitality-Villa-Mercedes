@props(['variant' => 'full'])

@php
    // The back office's way out: the same confirmation the guest side uses, with
    // the trigger this sidebar needs — a full-width button beside Profile, or an
    // icon when the sidebar is collapsed to a rail.
    //
    // The trigger stays a real submit button in a real form, so a browser that
    // never runs Alpine still signs out the way it always did.
    $isRail = $variant === 'rail';

    $triggerClasses = $isRail
        ? 'rounded-lg p-2 text-text-muted hover:bg-muted hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2'
        : 'w-full rounded-lg border border-border px-2 py-1.5 text-xs font-medium text-text-muted transition-colors duration-150 hover:bg-muted hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2';
@endphp

<x-confirm-sign-out
    :action="route('admin.logout')"
    title="Sign out of the back office?"
    consequence="You go back to the sign-in page. Anything typed on this screen and not saved is lost."
    @class(['flex-1' => ! $isRail])
>
    <x-slot:trigger>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf

            <button
                type="submit"
                @click.prevent="ask()"
                aria-haspopup="dialog"
                x-bind:aria-expanded="open ? 'true' : 'false'"
                @if ($isRail) aria-label="Log out" @endif
                class="{{ $triggerClasses }}"
            >
                @if ($isRail)
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                @else
                    Log out
                @endif
            </button>
        </form>
    </x-slot:trigger>
</x-confirm-sign-out>
