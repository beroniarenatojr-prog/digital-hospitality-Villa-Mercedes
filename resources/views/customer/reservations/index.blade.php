<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Reservations') }}
        </h2>
    </x-slot>

    @php
        $pending = $reservations->filter(fn($r) => $r->status === \App\Enums\ReservationStatus::Pending);
        $upcoming = $reservations->filter(fn($r) => $r->status === \App\Enums\ReservationStatus::Approved && $r->check_in->isFuture());
        $past = $reservations->filter(fn($r) => $r->status === \App\Enums\ReservationStatus::Approved && $r->check_in->isPast());
        $cancelledCompleted = $reservations->filter(fn($r) => in_array($r->status, [
            \App\Enums\ReservationStatus::Cancelled,
            \App\Enums\ReservationStatus::Completed,
            \App\Enums\ReservationStatus::Rejected
        ]));
    @endphp

    <div class="py-12" x-data="{ tab: 'upcoming' }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- Tabs -->
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <nav class="flex overflow-x-auto border-b border-gray-200" aria-label="Tabs">
                    <button @click="tab = 'upcoming'" 
                            :class="tab === 'upcoming' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm focus:outline-none">
                        Upcoming ({{ $upcoming->count() }})
                    </button>
                    <button @click="tab = 'pending'" 
                            :class="tab === 'pending' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm focus:outline-none">
                        Pending ({{ $pending->count() }})
                    </button>
                    <button @click="tab = 'past'" 
                            :class="tab === 'past' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm focus:outline-none">
                        Past ({{ $past->count() }})
                    </button>
                    <button @click="tab = 'cancelled'" 
                            :class="tab === 'cancelled' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm focus:outline-none">
                        Cancelled / Other ({{ $cancelledCompleted->count() }})
                    </button>
                </nav>
            </div>

            <!-- Content -->
            <div>
                <!-- Upcoming Tab -->
                <div x-show="tab === 'upcoming'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-y-4" x-transition:enter-end="opacity-100 transform translate-y-0" style="display: none;">
                    @forelse($upcoming as $reservation)
                        @include('customer.reservations.partials.reservation-card', ['reservation' => $reservation])
                    @empty
                        <div class="bg-white shadow sm:rounded-lg p-6">
                            <x-empty-state icon="calendar" title="No upcoming reservations" description="You don't have any upcoming approved reservations." />
                        </div>
                    @endforelse
                </div>

                <!-- Pending Tab -->
                <div x-show="tab === 'pending'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-y-4" x-transition:enter-end="opacity-100 transform translate-y-0" style="display: none;">
                    @forelse($pending as $reservation)
                        @include('customer.reservations.partials.reservation-card', ['reservation' => $reservation])
                    @empty
                        <div class="bg-white shadow sm:rounded-lg p-6">
                            <x-empty-state icon="clock" title="No pending reservations" description="You don't have any reservations waiting for approval." />
                        </div>
                    @endforelse
                </div>

                <!-- Past Tab -->
                <div x-show="tab === 'past'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-y-4" x-transition:enter-end="opacity-100 transform translate-y-0" style="display: none;">
                    @forelse($past as $reservation)
                        @include('customer.reservations.partials.reservation-card', ['reservation' => $reservation])
                    @empty
                        <div class="bg-white shadow sm:rounded-lg p-6">
                            <x-empty-state icon="archive" title="No past reservations" description="You don't have any past reservations." />
                        </div>
                    @endforelse
                </div>

                <!-- Cancelled / Other Tab -->
                <div x-show="tab === 'cancelled'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-y-4" x-transition:enter-end="opacity-100 transform translate-y-0" style="display: none;">
                    @forelse($cancelledCompleted as $reservation)
                        @include('customer.reservations.partials.reservation-card', ['reservation' => $reservation])
                    @empty
                        <div class="bg-white shadow sm:rounded-lg p-6">
                            <x-empty-state icon="x-circle" title="No cancelled reservations" description="You don't have any cancelled or rejected reservations." />
                        </div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
