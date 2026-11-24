@php
    // One panel shape for the three cards, so the two sides of the desk do not
    // drift: the guest's account page and the administrator's account page are
    // the same page with a different shell around it.
    $panel = 'rounded-2xl border border-border bg-surface p-4 shadow-sm sm:p-8';
@endphp

@if(Auth::user()->hasRole('Administrator'))
<x-admin-layout>
    <div class="space-y-6">
        <h1 class="text-2xl font-semibold text-text">Profile</h1>

        <div class="{{ $panel }}">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="{{ $panel }}">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="{{ $panel }}">
            <div class="max-w-xl">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-admin-layout>
@else
<x-customer-layout>
    <div class="py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- The fields here are the same ones the reservation form reads
                 back, so the page says so: a wrong mobile number or ID type
                 becomes a wrong guest sheet at the front desk. --}}
            <x-customer.page-header
                eyebrow="Your account"
                title="Profile"
                description="These details prefill your guest information sheet when you reserve, so keep them current. Changing your password or email is in the sections below."
            />

            <div class="{{ $panel }}">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="{{ $panel }}">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="{{ $panel }}">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-customer-layout>
@endif
