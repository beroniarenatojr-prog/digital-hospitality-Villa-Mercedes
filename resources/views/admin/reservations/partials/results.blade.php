@php
    $searched = request()->filled('search');
@endphp
@if ($searched)
    <p class="mb-3 text-sm text-text-muted" role="status">
        @if ($reservations->isEmpty())
            No reservation matches <span class="font-medium text-text">{{ request('search') }}</span>. Check the code, or search by guest name or phone number instead.
        @elseif ($reservations->total() > 1)
            <span class="font-medium text-text tabular-nums">{{ $reservations->total() }}</span> reservations match. Open the one you need.
        @else
            1 reservation matches.
        @endif
    </p>
@endif
@if ($reservations->isEmpty() && request()->boolean('arriving_today') && !request()->anyFilled(['search', 'status', 'source', 'facility_type', 'facility_id', 'date_from', 'date_until']))
    <x-admin.empty icon="reservations" title="No arrivals due today" description="Expected guests will appear here until staff record their check-in." />
@elseif ($reservations->isEmpty())
    <div class="rounded-xl border border-border bg-surface">
        <x-admin.empty icon="search" title="{{ $searched || request()->anyFilled(['status', 'source', 'facility_type', 'facility_id', 'date_from', 'date_until']) ? 'No reservations match these filters' : 'No reservations yet' }}" description="{{ $searched || request()->anyFilled(['status', 'source', 'facility_type', 'facility_id', 'date_from', 'date_until']) ? 'Widen the dates or clear a filter to see more. The guest may also be booked under a different name.' : 'Online bookings and walk-ins both land here as soon as they are filed.' }}" hint="Walk-in guests are filed from New walk-in reservation.">
            <x-slot:action><x-admin.button variant="secondary" :href="route('admin.reservations.create')">New walk-in reservation</x-admin.button></x-slot:action>
        </x-admin.empty>
    </div>
@else
    @php
        $bookings = $reservations->getCollection()->map(function ($reservation) {
            $group = $reservation->reservationGroup;
            $pendingDeposit = null;
            if ($group?->isPending()) {
                $pendingDeposit = $group->directPayments->first();
            } elseif (! $group && $reservation->status === \App\Enums\ReservationStatus::Pending) {
                $pendingDeposit = $reservation->payments->where('status', \App\Enums\PaymentStatus::Pending)
                    ->where('source', 'customer_deposit')->sortBy('id')->first();
            }
            return [
                'reviewUrl' => $pendingDeposit && ! isset(($group ?? $reservation)->booking_workflow['refund']) ? route('admin.payments.show', $pendingDeposit) : null,
                'reservation' => $reservation,
                'code' => $group?->group_code ?? $reservation->reservation_code,
                'lines' => $group ? $group->reservations : collect([$reservation]),
                'url' => $group ? route('admin.reservation-groups.show', $group) : route('admin.reservations.show', $reservation),
                'total' => $group ? $group->total_amount : $reservation->total_price,
                'balance' => $group ? $group->outstandingBalance() : $reservation->getRemainingBalance(),
            ];
        });
    @endphp
    <div class="hidden lg:block">
        <x-admin.table>
            <x-admin.table-head>
                <x-admin.table-cell :isHeader="true">Code</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Guest</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Space / Stay / Status</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true" numeric>Total</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true" numeric>Balance</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true"><span class="sr-only">Actions</span></x-admin.table-cell>
            </x-admin.table-head>
            <x-admin.table-body>
                @foreach ($bookings as $booking)
                    <x-admin.table-row>
                        <x-admin.table-cell>
                            <a href="{{ $booking['url'] }}" class="font-mono text-xs font-semibold text-primary hover:underline">{{ $booking['code'] }}</a>
                            <span class="mt-1 block text-xs text-text-muted">{{ $booking['reservation']->reservation_source->label() }}</span>
                            @if ($booking['lines']->count() > 1)<span class="block text-xs text-text-muted">{{ $booking['lines']->count() }} services</span>@endif
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            <span class="font-medium">{{ $booking['reservation']->guestName() }}</span>
                            <span class="mt-1 block text-xs text-text-muted">{{ $booking['reservation']->guestPhone() ?? $booking['reservation']->guestEmail() ?? 'No contact on file' }}</span>
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            @include('admin.reservations.partials.booking-lines', ['lines' => $booking['lines']])
                        </x-admin.table-cell>
                        <x-admin.table-cell numeric><x-amount :value="$booking['total']" /></x-admin.table-cell>
                        <x-admin.table-cell numeric><x-amount :value="$booking['balance']" /></x-admin.table-cell>
                        <x-admin.table-cell nowrap><a href="{{ $booking['reviewUrl'] ?? $booking['url'] }}" class="rounded font-semibold text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">Manage</a></x-admin.table-cell>
                    </x-admin.table-row>
                @endforeach
            </x-admin.table-body>
        </x-admin.table>
    </div>
    <ul class="space-y-3 lg:hidden">
        @foreach ($bookings as $booking)
            <li class="rounded-xl border border-border bg-surface p-4">
                <a href="{{ $booking['url'] }}" class="font-mono text-xs font-semibold text-primary hover:underline">{{ $booking['code'] }}</a>
                <p class="mt-1 text-sm font-semibold">{{ $booking['reservation']->guestName() }}</p>
                <p class="text-xs text-text-muted">{{ $booking['reservation']->guestPhone() ?? $booking['reservation']->guestEmail() ?? 'No contact on file' }}</p>
                <div class="mt-3">@include('admin.reservations.partials.booking-lines', ['lines' => $booking['lines']])</div>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-text-muted">Total</dt><dd><x-amount :value="$booking['total']" /></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-text-muted">Balance</dt><dd class="font-semibold"><x-amount :value="$booking['balance']" /></dd></div>
                </dl>
                <div class="mt-4 flex items-center justify-between gap-3 border-t border-border pt-3">
                    <span class="text-xs text-text-muted">{{ $booking['reservation']->reservation_source->label() }}</span>
                    <x-admin.button variant="secondary" size="sm" :href="$booking['reviewUrl'] ?? $booking['url']">Manage</x-admin.button>
                </div>
            </li>
        @endforeach
    </ul>
    @if ($reservations->hasPages())<div class="mt-6">{{ $reservations->withQueryString()->links() }}</div>@endif
@endif
