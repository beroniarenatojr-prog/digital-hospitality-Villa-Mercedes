<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('resort.name', config('app.name')) }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-text antialiased bg-background selection:bg-primary selection:text-white flex flex-col min-h-screen">
        <x-customer-header />

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
