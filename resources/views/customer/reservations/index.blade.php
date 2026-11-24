<x-customer-layout>
    @php
        $emptyCopy = match ($activeTab) {
            'pending' => [
                'title' => 'No pending reservations',
                'description' => 'Bookings the resort is still reviewing will appear here.',
            ],
            'current' => [
                'title' => 'No current stay',
                'description' => 'When an approved reservation is in progress, it shows up here.',
            ],
            'completed' => [
                'title' => 'No completed stays yet',
                'description' => 'Finished stays will be listed here.',
            ],
            // vocabulary-guard: a booking status the resort sets, not an action a guest takes
            'cancelled' => [
                // vocabulary-guard: the status above, named in the empty-state headline
                'title' => 'No closed bookings',
                // vocabulary-guard: the same status, named in the empty-state line
                'description' => 'Cancelled, declined, expired, or replaced reservations will appear here.',
            ],
            default => [
                'title' => 'No confirmed reservations',
                'description' => 'Upcoming bookings appear here once the resort confirms them.',
            ],
        };
    @endphp

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">

            <x-customer.page-header
                title="My Reservations"
                description="Check booking status, payments, and arrival details."
            />

            {{--
                An unfinished booking is not a reservation: nothing was submitted,
                nothing is held, no deposit is owed. It is still the guest's own
                work, and this is the page they look at to find it — so it sits
                above the status filters, said plainly, rather than among the bookings.
            --}}
            @if($unfinished->isNotEmpty())
                <section aria-labelledby="unfinished-bookings" class="space-y-3">
                    <div>
                        <h2 id="unfinished-bookings" class="font-heading text-xl font-semibold text-text">
                            {{ $unfinished->count() === 1 ? 'Unfinished booking' : 'Unfinished bookings' }}
                        </h2>
                        <p class="mt-1 text-sm text-text-muted">
                            Continue where you left off. Until you submit, nothing is held and no deposit is due.
                        </p>
                    </div>

                    <ul class="space-y-4">
                        @foreach($unfinished as $booking)
                            <li>
                                @include('customer.reservations.partials.unfinished-card', ['booking' => $booking])
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <form method="GET" action="{{ route('customer.reservations.index') }}" class="flex flex-wrap items-end gap-2 sm:hidden">
                <div class="min-w-0 flex-1">
                    <label for="reservation-status" class="block text-sm font-semibold mb-2">Show reservations</label>
                    <select id="reservation-status" name="tab" class="min-h-11 w-full rounded-lg border-border bg-surface text-base focus:border-primary focus:ring-primary">
                        @foreach($tabs as $t)
                            <option value="{{ $t['key'] }}" @selected($activeTab === $t['key'])>{{ $t['label'] }} ({{ $t['count'] }})</option>
                        @endforeach
                    </select>
                </div>
                <x-primary-button type="submit">Show</x-primary-button>
            </form>

            <div class="hidden sm:block" role="navigation" aria-label="Reservation status">
                <ul class="flex flex-wrap gap-2">
                    @foreach($tabs as $t)
                        <li>
                            <a href="{{ route('customer.reservations.index', ['tab' => $t['key']]) }}"
                               @if($activeTab === $t['key']) aria-current="page" @endif
                               @class([
                                   'inline-flex min-h-11 items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2',
                                   'border-primary bg-primary text-white' => $activeTab === $t['key'],
                                   'border-border bg-primary-light/60 text-text hover:border-primary/40 hover:bg-primary-light' => $activeTab !== $t['key'],
                               ])>
                                {{ $t['label'] }}
                                <span @class([
                                    'inline-flex min-w-[1.35rem] items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-bold normal-case tracking-normal',
                                    'bg-white/20 text-white' => $activeTab === $t['key'],
                                    'bg-surface text-text-muted' => $activeTab !== $t['key'],
                                ])>
                                    {{ $t['count'] }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="space-y-4">
                @forelse($reservations as $reservation)
                    @if ($reservation->reservationGroup && $reservation->reservationGroup->reservations->count() > 1)
                        @include('customer.reservations.partials.group-card', ['group' => $reservation->reservationGroup])
                    @else
                        @include('customer.reservations.partials.reservation-card', ['reservation' => $reservation])
                    @endif
                @empty
                    <div class="rounded-2xl border border-border bg-surface px-6 py-16 text-center shadow-sm sm:px-10">
                        <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-primary-light text-primary" aria-hidden="true">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </span>
                        <h2 class="mt-5 font-heading text-2xl font-semibold text-text">{{ $emptyCopy['title'] }}</h2>
                        <p class="mx-auto mt-2 max-w-md text-sm text-text-muted">{{ $emptyCopy['description'] }}</p>
                        <div class="mt-7">
                            <x-primary-button tag="a" href="{{ route('customer.home') }}" class="uppercase tracking-[0.08em]">
                                Explore resort services
                            </x-primary-button>
                        </div>
                    </div>
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
