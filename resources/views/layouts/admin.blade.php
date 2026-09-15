<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Villa Mercedes') }} - Administrator</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full overflow-hidden font-sans text-text antialiased">
    <div x-data="{ sidebarOpen: window.matchMedia('(min-width: 768px)').matches }" class="admin-shell">
        
        <!-- Mobile sidebar backdrop -->
        <div x-show="sidebarOpen" class="fixed inset-0 z-10 bg-gray-600 bg-opacity-75 transition-opacity md:hidden" 
             x-transition:enter="transition-opacity ease-linear duration-300" 
             x-transition:enter-start="opacity-0" 
             x-transition:enter-end="opacity-100" 
             x-transition:leave="transition-opacity ease-linear duration-300" 
             x-transition:leave-start="opacity-100" 
             x-transition:leave-end="opacity-0" 
             @click="sidebarOpen = false"
             style="display: none;">
        </div>

        <x-admin.sidebar />

        <div class="admin-main">
            <x-admin.header :header="$header ?? null" />

            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        @if (session('success'))
                            <div class="mb-4">
                                <x-alert type="success">{{ session('success') }}</x-alert>
                            </div>
                        @endif
                        @if (session('status'))
                            <div class="mb-4">
                                <x-alert type="success">{{ session('status') }}</x-alert>
                            </div>
                        @endif
                        @if (session('error'))
                            <div class="mb-4">
                                <x-alert type="error">{{ session('error') }}</x-alert>
                            </div>
                        @endif
                        @if ($errors->any())
                            <div class="mb-4">
                                <x-alert type="error" title="Please check the highlighted fields">
                                    <ul class="list-disc list-inside">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </x-alert>
                            </div>
                        @endif

                        {{ $slot }}
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
