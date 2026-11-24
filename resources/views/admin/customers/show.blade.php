@php
    $reservations = $customer->reservations->sortByDesc('created_at');
    $allPayments = $customer->reservations->flatMap->payments->sortByDesc('created_at');
@endphp

<x-admin-layout>
    <x-slot name="header">{{ $customer->name }}</x-slot>

    <x-admin.page-header
        title="{{ $customer->name }}"
        :breadcrumbs="[
            ['label' => 'Customers', 'url' => route('admin.customers.index')],
            ['label' => $customer->name],
        ]"
    >
        <x-slot:actions>
            <x-admin.button variant="secondary" :href="route('admin.reservations.index', ['search' => $customer->email])">Their reservations</x-admin.button>
            <x-admin.button variant="secondary" :href="route('admin.payments.index', ['search' => $customer->email])">Their payments</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-admin.panel>
            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Reservations</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-text">{{ $stats['total_reservations'] }}</p>
        </x-admin.panel>

        <x-admin.panel>
            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Upcoming stays</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-primary">{{ $stats['upcoming'] }}</p>
        </x-admin.panel>

        <x-admin.panel>
            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Verified paid</p>
            <p class="mt-1 text-2xl font-semibold text-success"><x-amount :value="$paymentStats['verified_total']" /></p>
        </x-admin.panel>

        <x-admin.panel>
            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Proofs waiting</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-warning">{{ $paymentStats['pending_count'] }}</p>
        </x-admin.panel>
    </div>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-admin.panel title="Customer information">
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Full name</dt>
                        <dd class="mt-1 text-sm font-semibold text-text">{{ $customer->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Email address</dt>
                        <dd class="mt-1 text-sm">
                            <a href="mailto:{{ $customer->email }}" class="rounded font-medium text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                {{ $customer->email }}
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Phone</dt>
                        <dd class="mt-1 text-sm tabular-nums text-text">{{ $customer->phone ?: 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Registered</dt>
                        <dd class="mt-1"><x-admin.date-time :value="$customer->created_at" format="date" /></dd>
                    </div>
                </dl>
            </x-admin.panel>

            <x-admin.panel
                title="Reservation history"
                :badge="$reservations->count()"
                :padding="false"
                description="Newest first. Manage a reservation to approve or decline it, review payment, reschedule, check in, or prepare its guest sheet."
            >
                @if ($reservations->isNotEmpty())
                    <x-admin.table plain>
                        <x-admin.table-head>
                            <x-admin.table-cell :isHeader="true">Reservation</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true">Stay</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true" numeric>Total</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true">Status</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true" class="text-right">Action</x-admin.table-cell>
                        </x-admin.table-head>
                        <x-admin.table-body>
                            @foreach ($reservations as $reservation)
                                <x-admin.table-row>
                                    <x-admin.table-cell>
                                        <a href="{{ route('admin.reservations.show', $reservation) }}" class="rounded font-mono text-xs font-semibold text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                            {{ $reservation->reservation_code }}
                                        </a>
                                        <span class="mt-0.5 block text-xs text-text-muted">{{ $reservation->facility->name }} · {{ $reservation->guest_count }} pax</span>
                                    </x-admin.table-cell>

                                    <x-admin.table-cell>
                                        <x-admin.date-range :from="$reservation->check_in" :to="$reservation->check_out" :time="false" />
                                    </x-admin.table-cell>

                                    <x-admin.table-cell numeric>
                                        <x-amount :value="$reservation->total_price" />
                                    </x-admin.table-cell>

                                    <x-admin.table-cell nowrap>
                                        <x-badge :status="$reservation->status" />
                                    </x-admin.table-cell>

                                    <x-admin.table-cell class="text-right" nowrap>
                                        <x-admin.button variant="secondary" size="sm" :href="route('admin.reservations.show', $reservation)">
                                            Manage reservation
                                        </x-admin.button>
                                    </x-admin.table-cell>
                                </x-admin.table-row>
                            @endforeach
                        </x-admin.table-body>
                    </x-admin.table>
                @else
                    <x-admin.empty
                        icon="reservations"
                        title="No reservations yet"
                        description="This guest has an account but has not booked a stay. Bookings made with the same email will appear here."
                    />
                @endif
            </x-admin.panel>

            <x-admin.panel
                title="Payment history"
                :badge="$allPayments->count()"
                :padding="false"
                description="Proofs, front-desk receipts, and refunds recorded against these stays."
            >
                @if ($allPayments->isNotEmpty())
                    <x-admin.table plain>
                        <x-admin.table-head>
                            <x-admin.table-cell :isHeader="true">Amount</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true">Method</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true">Date</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true">Reservation</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true">Status</x-admin.table-cell>
                        </x-admin.table-head>
                        <x-admin.table-body>
                            @foreach ($allPayments as $payment)
                                <x-admin.table-row>
                                    <x-admin.table-cell numeric>
                                        <a href="{{ route('admin.payments.show', $payment) }}" class="rounded font-semibold text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                            <x-amount :value="$payment->amount" />
                                        </a>
                                    </x-admin.table-cell>

                                    <x-admin.table-cell nowrap>{{ $payment->method->label() }}</x-admin.table-cell>

                                    <x-admin.table-cell nowrap>
                                        <x-admin.date-time :value="$payment->payment_date" format="date" />
                                    </x-admin.table-cell>

                                    <x-admin.table-cell nowrap>
                                        <span class="font-mono text-xs text-text-muted">{{ $payment->reservation->reservation_code }}</span>
                                    </x-admin.table-cell>

                                    <x-admin.table-cell nowrap>
                                        <x-badge :status="$payment->status" />
                                    </x-admin.table-cell>
                                </x-admin.table-row>
                            @endforeach
                        </x-admin.table-body>
                    </x-admin.table>
                @else
                    <x-admin.empty
                        icon="payments"
                        title="No payments recorded yet"
                        description="Nothing has been paid towards this guest's stays. Proofs appear here as soon as the guest uploads them."
                    />
                @endif
            </x-admin.panel>
        </div>

        <div class="space-y-6 lg:sticky lg:top-24">
            <x-admin.panel title="Reservation summary">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-text-muted">Total reservations</dt>
                        <dd class="font-semibold tabular-nums text-text">{{ $stats['total_reservations'] }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-text-muted">Upcoming</dt>
                        <dd class="font-semibold tabular-nums text-primary">{{ $stats['upcoming'] }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-text-muted">Completed</dt>
                        <dd class="font-semibold tabular-nums text-success">{{ $stats['completed'] }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-text-muted">Cancelled or rejected</dt>
                        <dd class="font-semibold tabular-nums text-text-muted">{{ $stats['cancelled'] }}</dd>
                    </div>
                </dl>
            </x-admin.panel>

            <x-admin.panel title="Payment summary">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-text-muted">Total payments</dt>
                        <dd class="font-semibold tabular-nums text-text">{{ $paymentStats['total_payments'] }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-text-muted">Verified amount</dt>
                        <dd class="font-semibold text-success"><x-amount :value="$paymentStats['verified_total']" /></dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-text-muted">Waiting for verification</dt>
                        <dd class="font-semibold tabular-nums text-warning">{{ $paymentStats['pending_count'] }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-text-muted">Rejected</dt>
                        <dd class="font-semibold tabular-nums text-danger">{{ $paymentStats['rejected_count'] }}</dd>
                    </div>
                </dl>
            </x-admin.panel>

            <x-admin.panel title="Actions">
                <div class="space-y-2">
                    <x-admin.button variant="secondary" :href="route('admin.customers.index')" class="w-full">All customers</x-admin.button>
                    <x-admin.button variant="ghost" :href="route('admin.reservations.index')" class="w-full">All reservations</x-admin.button>
                </div>
            </x-admin.panel>
        </div>
    </div>
</x-admin-layout>
