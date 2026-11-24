<x-admin-layout>
    <x-slot name="header">Payment review</x-slot>

    @php
        $hasPaymentFilters = request()->anyFilled(['search', 'status', 'method', 'facility_type', 'facility_id', 'date_from', 'date_until']);
        $hasPaymentSecondaryFilters = request()->anyFilled(['method', 'facility_type', 'facility_id', 'date_from', 'date_until']);
    @endphp

    <x-admin.page-header
        title="Payment review"
    >
        <x-slot:actions>
            <x-admin.button variant="secondary" :href="route('admin.payment-destinations.index')">Resort QR accounts</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    @if (($overdueReviews ?? 0) > 0)
        <div class="mb-6">
            <x-admin.notice variant="warning" title="{{ $overdueReviews }} {{ \Illuminate\Support\Str::plural('proof', $overdueReviews) }} waited more than 24 hours">
                Review these first. The space stays held for the guest until the payment is verified or rejected.
            </x-admin.notice>
        </div>
    @endif

    <x-admin.live-filter :action="route('admin.payments.index')">
        <x-admin.filter-form
            :action="route('admin.payments.index')"
            :active="$hasPaymentFilters"
            label="Search payments"
        >
            <label for="search" class="admin-label">Find a payment</label>
            <p class="admin-help mb-2">Find a group or reservation by booking code, guest name, phone number, or email.</p>

            <div class="flex flex-col gap-3 sm:flex-row">
                <input
                    type="search"
                    name="search"
                    id="search"
                    value="{{ request('search') }}"
                    placeholder="Booking code, guest name, phone, or email"
                    autocomplete="off"
                    enterkeyhint="search"
                    class="admin-input sm:flex-1"
                >

                <div class="sm:w-52">
                    <label for="status" class="sr-only">Payment status</label>
                    <select name="status" id="status" class="admin-input">
                        <option value="">Any status</option>
                        @foreach (\App\Enums\PaymentStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <x-slot:more>
                <section class="group" >
                    <div class="px-4 py-3 text-sm font-semibold text-text">
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-primary" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M3 4a1 1 0 011-1h16a1 1 0 01.8 1.6L14 13.333V19a1 1 0 01-1.447.894l-3-1.5A1 1 0 019 17.5v-4.167L3.2 4.6A1 1 0 013 4z" />
                            </svg>
                            Narrow by method, space, or dates
                        </span>
                        <span
                            class="text-xs font-medium text-text-muted"
                            data-live-filter-hint
                            data-live-filter-hint-fields="method,facility_type,facility_id,date_from,date_until"
                            data-live-filter-hint-idle="Not applied"
                            data-live-filter-hint-active="Applied"
                        >{{ $hasPaymentSecondaryFilters ? 'Applied' : 'Not applied' }}</span>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label for="method" class="admin-label">Method</label>
                            <select name="method" id="method" class="admin-input mt-1">
                                <option value="">Any method</option>
                                @foreach (\App\Enums\PaymentMethod::cases() as $method)
                                    <option value="{{ $method->value }}" @selected(request('method') === $method->value)>{{ $method->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="facility_type" class="admin-label">Space type</label>
                            <select name="facility_type" id="facility_type" class="admin-input mt-1">
                                <option value="">Any type</option>
                                @foreach ($facilityTypes as $type)
                                    <option value="{{ $type->slug }}" @selected(request('facility_type') === $type->slug)>{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="facility_id" class="admin-label">Space</label>
                            <select name="facility_id" id="facility_id" class="admin-input mt-1">
                                <option value="">Any space</option>
                                @foreach ($facilities as $facility)
                                    <option value="{{ $facility->id }}" @selected((string) request('facility_id') === (string) $facility->id)>{{ $facility->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label for="date_from" class="admin-label">From</label>
                                <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}" class="admin-input mt-1">
                            </div>
                            <div>
                                <label for="date_until" class="admin-label">To</label>
                                <input type="date" name="date_until" id="date_until" value="{{ request('date_until') }}" class="admin-input mt-1">
                            </div>
                        </div>
                    </div>
                </section>
            </x-slot:more>
        </x-admin.filter-form>

        <x-admin.live-filter-results>
            @include('admin.payments.partials.results')
        </x-admin.live-filter-results>
    </x-admin.live-filter>
</x-admin-layout>
