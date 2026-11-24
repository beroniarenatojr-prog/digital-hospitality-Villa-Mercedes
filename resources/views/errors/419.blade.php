@php
    // The request that got here matched a real route, and that route — not a
    // header, and not a session that has just ended — is what still says which
    // side of the desk this was.
    $backOffice = request()->routeIs('admin.*');
@endphp

<x-guest-layout title="Session ended">
    <div class="mb-8">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-accent">Session ended</p>
        <span class="mt-2 block h-px w-10 bg-accent/70" aria-hidden="true"></span>
        <h1 class="mt-4 font-heading text-3xl sm:text-4xl font-semibold text-text">
            {{ $backOffice ? 'Your back office session ended' : 'Your session ended' }}
        </h1>
        <p class="mt-3 text-sm text-text-muted">
            {{-- A 419 is refused before anything runs, so "nothing was changed" is
                 a fact about this refusal, not a reassurance. --}}
            You were away long enough that we signed you out, so that last form was never sent
            and nothing was changed.
            @if ($backOffice)
                Sign in again with your staff credentials to carry on where you left off.
            @else
                Sign in again to pick up where you left off.
            @endif
        </p>
    </div>

    <x-primary-button tag="a" :href="$backOffice ? route('admin.login') : route('login')" class="w-full">
        {{ $backOffice ? 'Sign in to the back office' : 'Sign in again' }}
    </x-primary-button>

    <p class="mt-5 text-sm text-text-muted">
        Or
        <a href="{{ route('home') }}" class="font-medium text-primary hover:underline">return to the resort site</a>.
    </p>
</x-guest-layout>
