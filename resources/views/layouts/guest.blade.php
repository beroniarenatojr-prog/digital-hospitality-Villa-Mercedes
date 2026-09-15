<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>
            @if(request()->routeIs('login')) Sign in
            @elseif(request()->routeIs('admin.login')) Staff sign in
            @elseif(request()->routeIs('register')) Create account
            @elseif(request()->routeIs('password.*')) Reset password
            @else Account
            @endif
            · {{ config('resort.name', config('app.name')) }}
        </title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-text antialiased bg-background">
        <div class="min-h-screen grid lg:grid-cols-2">
            {{-- Photo panel: compact hero on mobile, full split panel on desktop --}}
            <aside class="relative flex flex-col min-h-[14rem] sm:min-h-[16rem] lg:min-h-screen overflow-hidden bg-primary">
                <img
                    src="{{ asset('images/hero-villa-mercedes.jpg') }}"
                    alt="Villa Mercedes Nature Farm &amp; Resort"
                    fetchpriority="high"
                    decoding="async"
                    class="absolute inset-0 w-full h-full object-cover object-[center_35%]"
                >
                <div class="absolute inset-0 bg-gradient-to-br from-primary-dark/90 via-primary/70 to-black/55"></div>

                @isset($photo)
                    {{ $photo }}
                @else
                    <div class="relative mt-auto p-5 sm:p-6 lg:p-10 xl:p-14 text-white max-w-lg">
                        <p class="font-script text-2xl sm:text-3xl text-accent-light">Nature Farm &amp; Resort</p>
                        <h1 class="mt-1 lg:mt-2 font-display text-2xl sm:text-3xl lg:text-4xl font-bold leading-tight">{{ config('resort.short_name') }}</h1>
                        <p class="mt-2 lg:mt-4 text-sm lg:text-base text-white/90 leading-relaxed hidden sm:block">{{ config('resort.tagline') }}</p>
                        <p class="mt-2 lg:mt-6 text-xs lg:text-sm text-white/75">{{ config('resort.location') }}</p>
                    </div>
                @endisset
            </aside>

            {{-- Form panel --}}
            <main class="flex flex-col lg:min-h-screen">
                <div class="px-6 sm:px-10 pt-6">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-sm font-medium text-text-muted hover:text-primary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                        Back to the resort
                    </a>
                </div>

                <div class="flex-1 flex flex-col justify-center px-6 sm:px-10 py-10">
                    <div class="w-full max-w-md mx-auto">
                        <a href="{{ route('home') }}" @class(['flex items-center gap-3 mb-8', 'lg:hidden' => isset($photo)])>
                            <img src="{{ asset('images/logo-villa-mercedes.png') }}" alt="" class="h-14 w-auto rounded-md shadow-sm">
                            <span class="flex flex-col leading-tight">
                                <span class="font-display text-xl font-bold text-primary">{{ config('resort.short_name') }}</span>
                                <span class="text-xs text-text-muted">Nature Farm &amp; Resort</span>
                            </span>
                        </a>

                        {{ $slot }}
                    </div>
                </div>

                <p class="px-6 sm:px-10 pb-6 text-center text-sm text-text-muted">
                    Need help?
                    <a href="{{ config('resort.phone_href') }}" class="text-primary font-medium hover:underline">{{ config('resort.phone') }}</a>
                    ·
                    <a href="{{ route('contact') }}" class="text-primary font-medium hover:underline">Contact us</a>
                </p>
            </main>
        </div>
    </body>
</html>
