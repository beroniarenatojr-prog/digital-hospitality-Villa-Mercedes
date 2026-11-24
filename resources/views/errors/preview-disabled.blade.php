<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Unavailable in this demonstration · {{ config('resort.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background font-sans text-text antialiased">
    <main class="mx-auto flex min-h-[75vh] max-w-xl items-center px-6 py-16 text-center">
        <div class="w-full rounded-2xl border border-border bg-white p-8 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-accent">Preview safeguard</p>
            <h1 class="mt-3 font-heading text-3xl font-semibold">This action is unavailable</h1>
            <p class="mt-4 text-text-muted">{{ $message }}</p>
            <a href="{{ route('home') }}" class="mt-7 inline-flex rounded-full bg-primary px-5 py-2.5 font-semibold text-white hover:bg-primary-dark">
                Return to the resort preview
            </a>
        </div>
    </main>
</body>
</html>
