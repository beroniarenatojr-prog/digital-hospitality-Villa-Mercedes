<x-guest-layout>
    <x-slot name="photo">
        <div class="relative flex flex-1 flex-col justify-between gap-8 p-5 sm:p-6 lg:p-10 xl:p-14 text-white">
            <a href="{{ route('home') }}" class="hidden lg:flex items-center gap-3 w-fit group">
                <img src="{{ asset('images/logo-villa-mercedes-mark.png') }}" alt="" class="h-11 w-11 rounded-xl object-contain bg-white/10 ring-1 ring-white/20">
                <span class="leading-none">
                    <span class="block font-heading text-lg font-semibold">{{ config('resort.short_name') }}</span>
                    <span class="mt-1 block text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-white/70">Staff portal</span>
                </span>
            </a>

            <div class="max-w-md">
                <span class="block h-px w-12 bg-accent mb-6" aria-hidden="true"></span>
                <blockquote class="font-heading text-3xl sm:text-4xl xl:text-[2.6rem] leading-[1.15] font-semibold text-balance">
                    Resort operations sign-in
                </blockquote>
                <p class="mt-5 text-sm sm:text-base text-white/75 leading-relaxed">
                    This page is for Villa Mercedes staff. Guests should use the customer sign-in page.
                </p>
                <p class="mt-4 text-xs text-white/60">{{ config('resort.location') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="mb-8">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-accent">Staff</p>
        <span class="mt-2 block h-px w-10 bg-accent/70" aria-hidden="true"></span>
        <h2 class="mt-4 font-heading text-3xl sm:text-4xl font-semibold text-text">Administrator sign in</h2>
        <p class="mt-3 text-sm text-text-muted">
            Use your staff credentials to open the operations dashboard.
        </p>
    </div>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form method="POST" action="{{ route('admin.login.store') }}" class="space-y-5" x-data="{ isSubmitting: false, show: false }" @submit="isSubmitting = true">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email address')" class="text-xs font-semibold uppercase tracking-[0.16em] text-text-muted" />
            <div class="relative mt-2">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-text-muted">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" /><path stroke-linecap="round" stroke-linejoin="round" d="m4 7 8 6 8-6" /></svg>
                </span>
                <x-text-input id="email" class="block w-full rounded-xl ps-11" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="staff@email.com" />
            </div>
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <div class="flex items-center justify-between gap-3">
                <x-input-label for="password" :value="__('Password')" class="text-xs font-semibold uppercase tracking-[0.16em] text-text-muted" />
                @if (Route::has('password.request'))
                    <a class="text-sm font-medium text-primary hover:underline underline-offset-2" href="{{ route('password.request') }}">
                        {{ __('Forgot password?') }}
                    </a>
                @endif
            </div>
            <div class="relative mt-2">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-text-muted">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2" /><path stroke-linecap="round" d="M8 11V8a4 4 0 018 0v3" /></svg>
                </span>
                <x-text-input id="password" class="block w-full rounded-xl ps-11 pe-12"
                                x-bind:type="show ? 'text' : 'password'"
                                type="password"
                                name="password"
                                required autocomplete="current-password"
                                placeholder="Enter your password" />
                <button type="button"
                        class="absolute inset-y-0 right-0 px-3 text-text-muted hover:text-primary"
                        @click="show = ! show"
                        :aria-pressed="show.toString()"
                        :aria-label="show ? 'Hide password' : 'Show password'">
                    <svg x-show="!show" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    <svg x-show="show" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="inline-flex items-center cursor-pointer select-none">
            <input id="remember_me" type="checkbox" class="size-4 rounded border-border text-primary shadow-sm focus:ring-primary" name="remember" value="1" @checked(old('remember'))>
            <span class="ms-2 text-sm text-text-muted">{{ __('Keep me signed in') }}</span>
        </label>

        <x-primary-button class="w-full !rounded-full !py-3" x-bind:aria-busy="isSubmitting.toString()">
            <span x-show="!isSubmitting">{{ __('Sign in') }}</span>
            <span x-show="isSubmitting" x-cloak>{{ __('Signing in…') }}</span>
        </x-primary-button>
    </form>
</x-guest-layout>
