<x-guest-layout>
    <div class="mb-4 text-sm text-text-muted">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-success">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </div>
        </form>

        <x-confirm-sign-out>
            <x-slot:trigger>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button
                        type="submit"
                        @click.prevent="ask()"
                        aria-haspopup="dialog"
                        x-bind:aria-expanded="open ? 'true' : 'false'"
                        class="rounded-md text-sm text-text-muted underline hover:text-text focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                    >
                        {{ __('Log Out') }}
                    </button>
                </form>
            </x-slot:trigger>
        </x-confirm-sign-out>
    </div>
</x-guest-layout>
