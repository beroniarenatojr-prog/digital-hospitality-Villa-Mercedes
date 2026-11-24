@php
    $facility = $rate->facility;
    $unit = \App\Enums\RateUnit::normalize($rate->unit);
@endphp

<x-admin-layout>
    <x-slot name="header">{{ $rate->name }}</x-slot>

    <x-admin.page-header
        title="{{ $rate->name }}"
        :breadcrumbs="[
            ['label' => 'Rates', 'url' => route('admin.rates.index')],
            ['label' => $rate->name],
        ]"
    >
        <x-slot:actions>
            <x-admin.button variant="secondary" :href="route('admin.rates.edit', $rate)">Edit rate</x-admin.button>
            <x-admin.button variant="secondary" :href="route('admin.rates.create', ['facility_id' => $rate->facility_id])">Add another rate</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-admin.panel title="Rate details">
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Rate name</dt>
                        <dd class="mt-1 text-sm font-semibold text-text">{{ $rate->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Facility</dt>
                        <dd class="mt-1 text-sm">
                            <a href="{{ route('admin.facilities.show', $facility) }}" class="rounded font-medium text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                {{ $facility->name }}
                            </a>
                            <span class="mt-0.5 block text-xs text-text-muted">{{ $facility->facilityType->name ?? '' }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Rate type</dt>
                        <dd class="mt-1 text-sm text-text">{{ $rate->type->label() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Amount</dt>
                        <dd class="mt-1 text-lg font-semibold text-text"><x-amount :value="$rate->amount" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Charged per</dt>
                        <dd class="mt-1 text-sm text-text">{{ $unit }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Pricing</dt>
                        <dd class="mt-1 text-sm {{ $rate->is_active ? 'text-success' : 'text-text-muted' }}">
                            {{ $rate->is_active ? 'Active' : 'Inactive' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Effective from</dt>
                        <dd class="mt-1"><x-admin.date-time :value="$rate->effective_from" format="date" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Effective until</dt>
                        <dd class="mt-1"><x-admin.date-time :value="$rate->effective_until" format="date" /></dd>
                    </div>
                </dl>
            </x-admin.panel>

            {{-- The one thing the facts above cannot say: a change here cannot
                 reach back into a stay that was already priced. --}}
            <p class="max-w-[70ch] text-sm text-text-muted">Changing or deactivating a rate affects future pricing only. Reservations that were already priced keep the amount they were booked at.</p>
        </div>

        <div class="space-y-6 lg:sticky lg:top-24">
            <x-admin.panel
                title="Rate status"
                description="Inactive rates stay on file and are ignored when a booking is priced."
            >
                @if ($rate->is_active)
                    <p class="text-sm text-text-muted">This rate is used whenever a booking for {{ $facility->name }} is priced.</p>
                    <div class="mt-4">
                        <x-admin.confirm-action
                            :action="route('admin.rates.deactivate', $rate)"
                            method="PATCH"
                            label="Deactivate rate"
                            title="Deactivate {{ $rate->name }}?"
                            :consequence="'Bookings for '.$facility->name.' stop using this rate and fall back to the base rate. Reservations already booked keep their price.'"
                            confirm="Deactivate rate"
                        />
                    </div>
                @else
                    <p class="text-sm text-text-muted">Pricing ignores this rate while it is inactive.</p>
                    <div class="mt-4">
                        <x-admin.confirm-action
                            :action="route('admin.rates.activate', $rate)"
                            method="PATCH"
                            label="Activate rate"
                            title="Activate {{ $rate->name }}?"
                            :consequence="'Future bookings for '.$facility->name.' are priced with this rate from the next booking onwards.'"
                            confirm="Activate rate"
                            tone="primary"
                        />
                    </div>
                @endif
            </x-admin.panel>

            <x-admin.panel title="Actions">
                <div class="space-y-2">
                    <x-admin.button variant="secondary" :href="route('admin.rates.edit', $rate)" class="w-full">Edit rate</x-admin.button>
                    <x-admin.button variant="secondary" :href="route('admin.facilities.show', $facility)" class="w-full">Open {{ $facility->name }}</x-admin.button>
                    <x-admin.button variant="ghost" :href="route('admin.rates.index')" class="w-full">All rates</x-admin.button>
                </div>
            </x-admin.panel>
        </div>
    </div>
</x-admin-layout>
