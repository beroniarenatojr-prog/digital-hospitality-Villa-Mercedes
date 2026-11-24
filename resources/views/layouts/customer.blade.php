<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        {{-- `viewport-fit=cover` is the only reason the safe-area insets resolve
             to anything but 0. Without it every `env(safe-area-inset-*)` in the
             stylesheet is silently inert, and a thumb-driven CTA ends up under
             the home indicator with no class to blame. --}}
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('resort.name', config('app.name')) }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    {{-- `has-bottom-dock` names the one guest page that pins a bar to the bottom
         of the screen, so the chat launcher, back-to-top and footer get out of
         its way in CSS — before a script runs, and whether or not one fails. --}}
    <body @class([
        'font-sans text-text antialiased bg-background selection:bg-primary selection:text-white flex flex-col min-h-screen',
        'customer-account-surface' => \App\Support\CustomerNav::isAccountContext(),
        'has-bottom-dock' => request()->routeIs('customer.booking.create'),
    ])>
        <x-customer-header />

        @auth
            @php($checkoutReminder = \App\Support\CustomerNav::isCustomer() ? \App\Support\CheckoutReminder::currentFor(Auth::user()) : null)
            @if($checkoutReminder)
                <div class="border-b border-warning/30 bg-warning-light" role="status" aria-live="polite">
                    <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-3 text-sm text-text sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                        <p>
                            <strong>Checkout reminder:</strong>
                            20 minutes or less remain before checkout at {{ $checkoutReminder->occupancyEnd()->format('g:i A') }} for {{ $checkoutReminder->facility->name }}.
                        </p>
                        <a href="{{ route('customer.reservations.show', $checkoutReminder) }}" class="shrink-0 font-semibold text-primary underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                            View reservation
                        </a>
                    </div>
                </div>
            @endif
        @endauth

        <!-- Page Content -->
        <main id="main-content" @class(['flex-grow', 'pt-20' => ! request()->routeIs('home', 'rooms', 'halls', 'packages', 'cottages', 'pools', 'resort.*')])>
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
        <x-reservation-auth-modal />

        @stack('scripts')
    </body>
</html>
