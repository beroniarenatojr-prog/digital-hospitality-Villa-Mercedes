<x-guest-layout>
    <div class="mb-8">
        <h2 class="font-display text-3xl font-bold text-text">Choose a new password</h2>
        <p class="mt-2 text-text-muted">Enter your email and the password you want to use from now on.</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-2 w-full" type="email" name="email" :value="old('email', $email ?? '')" :aria-invalid="$errors->has('email') ? 'true' : null" :aria-describedby="$errors->has('email') ? 'email-error' : null" required :autofocus="! $errors->has('password') || $errors->has('email')" autocomplete="username" />
            <x-input-error id="email-error" :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" :autofocus="$errors->has('password') && ! $errors->has('email')" class="block mt-2 w-full" type="password" name="password" :aria-invalid="$errors->has('password') ? 'true' : null" :aria-describedby="$errors->has('password') ? 'password-error' : null" required autocomplete="new-password" />
            <x-input-error id="password-error" :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm password')" />
            <x-text-input id="password_confirmation" class="block mt-2 w-full" type="password" name="password_confirmation" :aria-invalid="$errors->has('password_confirmation') ? 'true' : null" :aria-describedby="$errors->has('password_confirmation') ? 'password_confirmation-error' : null" required autocomplete="new-password" />
            <x-input-error id="password_confirmation-error" :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">
            {{ __('Reset password') }}
        </x-primary-button>
    </form>
</x-guest-layout>
