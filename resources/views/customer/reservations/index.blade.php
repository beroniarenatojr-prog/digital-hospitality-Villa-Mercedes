<x-customer-layout>
    @php
        $emptyCopy = match ($activeTab) {
            'pending' => ['title' => 'No pending reservations', 'description' => 'Bookings awaiting a deposit or staff review will appear here.'],
            'current' => ['title' => 'No current stay', 'description' => 'When an approved reservation is in progress, it shows up here.'],
            'completed' => ['title' => 'No completed stays yet', 'description' => 'Finished stays will be listed here.'],
            'cancelled' => ['title' => 'No cancelled or declined reservations', 'description' => 'Cancelled, declined, or replaced reservations will appear here.'],
            default => ['title' => 'No upcoming reservations', 'description' => 'When a reservation is confirmed and still ahead of you, it shows up here.'],
        };
        $pendingCount = collect($tabs)->firstWhere('key', 'pending')['count'] ?? 0;
    @endphp

    <div class="py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="font-heading text-4xl md:text-5xl font-semibold text-text">My reservations</h1>
                    <p class="text-text-muted mt-2">View reservations, submit a deposit, or request a change. The remaining balance is paid at arrival.</p>
                </div>
                <x-primary-button tag="a" href="{{ route('customer.reservations.new') }}">
                    New Reservation
                </x-primary-button>
            </div>

            @if($pendingCount > 0)
                <x-alert type="info" title="{{ $pendingCount }} reservation(s) awaiting action or review">
                    Open your reservation to submit its deposit or check staff review progress. A proof upload needs verification before confirmation.
                </x-alert>
            @endif

            <div class="border-b border-border">
                <nav class="flex gap-1 overflow-x-auto -mb-px" aria-label="Reservation status">
                    @foreach($tabs as $t)
                        <a href="{{ route('customer.reservations.index', ['tab' => $t['key']]) }}"
                           aria-selected="{{ $activeTab === $t['key'] ? 'true' : 'false' }}"
                           @class([
                               'whitespace-nowrap py-3 px-4 border-b-2 font-semibold text-sm focus:outline-none focus:text-primary transition',
                               'border-primary text-primary' => $activeTab === $t['key'],
                               'border-transparent text-text-muted hover:text-text hover:border-border' => $activeTab !== $t['key'],
                           ])>
                            {{ $t['label'] }}
                            <span @class([
                                'ms-1.5 inline-flex items-center justify-center min-w-[1.5rem] px-1.5 py-0.5 rounded-full text-xs font-bold',
                                'bg-primary text-white' => $activeTab === $t['key'],
                                'bg-muted text-text-muted' => $activeTab !== $t['key'],
                            ])>
                                {{ $t['count'] }}
                            </span>
                        </a>
                    @endforeach
                </nav>
            </div>

            <div class="space-y-4">
                @forelse($reservations as $reservation)
                    @include('customer.reservations.partials.reservation-card', ['reservation' => $reservation])
                @empty
                    <x-empty-state icon="calendar" :title="$emptyCopy['title']" :description="$emptyCopy['description']">
                        @if($activeTab === 'upcoming')
                            <x-slot name="action">
                                <x-primary-button tag="a" href="{{ route('customer.reservations.new') }}">New Reservation</x-primary-button>
                            </x-slot>
                        @endif
                    </x-empty-state>
                @endforelse
            </div>

            @if($reservations->hasPages())
                <div class="pt-2">
                    {{ $reservations->links() }}
                </div>
            @endif

        </div>
    </div>
</x-customer-layout>
