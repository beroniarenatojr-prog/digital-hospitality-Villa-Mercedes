@php
    $hasFilters = request()->anyFilled(['search', 'status', 'method', 'facility_type', 'facility_id', 'date_from', 'date_until']);
@endphp

@if ($payments->isEmpty())
    <x-admin.empty icon="payments" title="No payments found" description="Try another booking code or adjust the filters." />
@else
    <p class="mb-3 text-sm text-text-muted">{{ $payments->total() }} {{ \Illuminate\Support\Str::plural('booking', $payments->total()) }}{{ $hasFilters ? ' matching these filters' : ' with recorded payments' }} · Awaiting review first</p>
    <x-admin.table sticky tabindex="0" role="region" aria-label="Booking payments" class="max-h-[70vh] focus-visible:outline-primary">
        <caption class="sr-only">Payments grouped by booking. Amounts reflect the selected filters.</caption>
        <x-admin.table-head>
            <x-admin.table-cell isHeader>Booking and guest</x-admin.table-cell>
            <x-admin.table-cell isHeader numeric>Verified</x-admin.table-cell>
            <x-admin.table-cell isHeader numeric>Awaiting review</x-admin.table-cell>
            <x-admin.table-cell isHeader>Payment methods</x-admin.table-cell>
            <x-admin.table-cell isHeader>Latest payment</x-admin.table-cell>
            <x-admin.table-cell isHeader><span class="sr-only">Actions</span></x-admin.table-cell>
        </x-admin.table-head>
        <x-admin.table-body>
            @foreach ($payments as $bookingPayments)
                @php
                    $first = $bookingPayments->first();
                    $reservation = $first->reservation;
                    $group = $first->reservationGroup ?? $reservation?->reservationGroup;
                    $pending = $bookingPayments->where('status', \App\Enums\PaymentStatus::Pending);
                    $verified = $bookingPayments->where('status', \App\Enums\PaymentStatus::Verified)->sum('amount');
                    $bookingCode = $group?->group_code ?? $reservation?->reservation_code ?? 'Booking unavailable';
                    $guestName = $group?->guest_name ?? $reservation?->guestName() ?? 'Guest unavailable';
                    $bookingUrl = $group ? route('admin.reservation-groups.show', $group).'#group-payments'
                        : ($reservation ? route('admin.reservations.show', $reservation) : route('admin.payments.show', $first));
                    $review = $pending->first();
                @endphp
                <x-admin.table-row class="{{ $review ? 'bg-warning-light/30' : '' }}">
                    <th scope="row" class="px-4 py-4 text-left font-normal min-w-56">
                        <a href="{{ $bookingUrl }}" class="font-semibold text-primary hover:underline">{{ $bookingCode }}</a>
                        <p class="mt-1 text-sm text-text">{{ $guestName }}</p>
                        @if($review)
                            <p class="mt-1 text-xs font-semibold text-warning-dark">Awaiting verification</p>
                        @endif
                    </th>
                    <x-admin.table-cell numeric nowrap><x-amount :value="$verified" /></x-admin.table-cell>
                    <x-admin.table-cell numeric nowrap><x-amount :value="$pending->sum('amount')" /></x-admin.table-cell>
                    <x-admin.table-cell>{{ $bookingPayments->map(fn ($payment) => $payment->method?->label())->filter()->unique()->join(', ') }}</x-admin.table-cell>
                    <x-admin.table-cell nowrap><x-admin.date-time :value="$bookingPayments->max('payment_date')" format="date" /></x-admin.table-cell>
                    <x-admin.table-cell nowrap>
                        <x-admin.button :variant="$review ? 'primary' : 'secondary'" size="sm" :href="$review ? route('admin.payments.show', $review) : $bookingUrl">
                            {{ $review ? 'Review payment' : 'View booking payments' }}
                        </x-admin.button>
                        <x-content-panel class="mt-2">
                            <x-slot:heading>Payment records</x-slot:heading>
                            <ul class="mt-2 space-y-3">
                                @foreach($bookingPayments as $payment)
                                    <li class="text-xs">
                                        <a href="{{ route('admin.payments.show', $payment) }}" class="inline-flex min-h-11 items-center gap-2 text-primary underline">
                                            {{ $payment->method?->label() }} · <x-amount :value="$payment->amount" /> · {{ $payment->status->label() }}
                                        </a>
                                        @if($payment->reference_number)
                                            <span class="block text-text-muted">{{ $payment->reference_number }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </x-content-panel>
                    </x-admin.table-cell>
                </x-admin.table-row>
            @endforeach
        </x-admin.table-body>
    </x-admin.table>
    @if($hasFilters)
        <p class="mt-2 text-xs text-text-muted">Amounts include payments matching your filters. Open the booking for its full balance.</p>
    @endif
    @if ($payments->hasPages())
        <div class="mt-6">{{ $payments->withQueryString()->links() }}</div>
    @endif
@endif
