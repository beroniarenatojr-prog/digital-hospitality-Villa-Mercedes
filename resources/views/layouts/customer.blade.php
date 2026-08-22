<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Villa Mercedes Nature Farm & Resort') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-text antialiased bg-background font-sans text-text antialiased selection:bg-primary selection:text-white flex flex-col min-h-screen">
        <x-customer-header />

        <!-- Page Content -->
        <main class="flex-grow">
            {{ $slot }}
        </main>

        <x-customer-footer />

        @stack('scripts')
    </body>
</html>

