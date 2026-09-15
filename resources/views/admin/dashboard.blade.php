<x-admin-layout>
    <div class="space-y-6">
        <header class="flex flex-col gap-4 border-b border-gray-200 pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-medium text-primary">{{ now()->format('l, F j') }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-900">Today’s operations</h1>
                <p class="mt-1 text-sm font-normal text-gray-600">Live arrivals and items waiting for review.</p>
            </div>
            <a href="{{ route('admin.reservations.index') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-md bg-primary px-4 text-sm font-semibold text-white hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                Open reservations
            </a>
        </header>

        <section aria-labelledby="attention-heading">
            <h2 id="attention-heading" class="mb-3 text-sm font-semibold text-gray-900">Needs attention</h2>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <a href="{{ route('admin.reservations.index', ['status' => 'pending']) }}" class="group rounded-md border border-gray-200 bg-white p-4 transition-colors hover:border-primary focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                    <p class="text-sm font-semibold text-gray-900">Reservations to approve</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-primary">{{ $pendingReservationCount }}</p>
                    <span class="mt-3 inline-flex text-sm font-semibold text-primary group-hover:underline">Review reservations</span>
                </a>

                <a href="{{ route('admin.payments.index', ['status' => 'pending']) }}" class="group rounded-md border border-gray-200 bg-white p-4 transition-colors hover:border-primary focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                    <p class="text-sm font-semibold text-gray-900">Payments to verify</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-primary">{{ $pendingPaymentCount }}</p>
                    <span class="mt-3 inline-flex text-sm font-semibold text-primary group-hover:underline">Review payments</span>
                </a>

                <a href="{{ route('admin.reservations.index', ['date_from' => now()->toDateString(), 'date_until' => now()->toDateString()]) }}" class="group rounded-md border border-gray-200 bg-white p-4 transition-colors hover:border-primary focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                    <p class="text-sm font-semibold text-gray-900">Arrivals today</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-primary">{{ $arrivalsToday->count() }}</p>
                    <span class="mt-3 inline-flex text-sm font-semibold text-primary group-hover:underline">Open arrival list</span>
                </a>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <div class="rounded-md border border-gray-200 bg-white">
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Arriving today</h2>
                        <p class="text-sm font-normal text-gray-500">Check guests in when they arrive.</p>
                    </div>
                    <a href="{{ route('admin.reservations.index', ['date_from' => now()->toDateString(), 'date_until' => now()->toDateString()]) }}" class="text-sm font-semibold text-primary hover:underline">View all</a>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse($arrivalsToday as $reservation)
                        <a href="{{ route('admin.reservations.show', $reservation) }}" class="flex items-center gap-3 px-5 py-3.5 transition hover:bg-gray-50">
                            <span class="w-16 shrink-0 whitespace-nowrap text-sm font-semibold tabular-nums text-primary">{{ $reservation->check_in->format('g:i A') }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-gray-900">{{ $reservation->guestName() }}</span>
                                <span class="block truncate text-xs font-medium text-gray-500">{{ $reservation->facility->name }} · {{ $reservation->reservation_code }}</span>
                            </span>
                            <x-badge :status="$reservation->status->value" variant="compact" />
                        </a>
                    @empty
                        <div class="px-5 py-10 text-center">
                            <p class="text-sm font-semibold text-gray-700">No arrivals scheduled.</p>
                            <p class="mt-1 text-sm font-normal text-gray-500">The front desk is clear for now.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-md border border-gray-200 bg-white">
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Next 7 days</h2>
                        <p class="text-sm font-normal text-gray-500">Upcoming stays, ordered by arrival.</p>
                    </div>
                    <a href="{{ route('admin.reservations.index') }}" class="text-sm font-semibold text-primary hover:underline">View calendar</a>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse($upcomingReservations as $reservation)
                        <a href="{{ route('admin.reservations.show', $reservation) }}" class="flex items-center gap-3 px-5 py-3.5 transition hover:bg-gray-50">
                            <span class="w-14 shrink-0 whitespace-nowrap text-sm font-semibold tabular-nums text-primary">{{ $reservation->check_in->format('M j') }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-gray-900">{{ $reservation->guestName() }}</span>
                                <span class="block truncate text-xs font-medium text-gray-500">{{ $reservation->facility->name }} · {{ $reservation->check_in->format('g:i A') }}</span>
                            </span>
                            <x-badge :status="$reservation->status->value" variant="compact" />
                        </a>
                    @empty
                        <div class="px-5 py-10 text-center">
                            <p class="text-sm font-semibold text-gray-700">No upcoming stays.</p>
                            <p class="mt-1 text-sm font-normal text-gray-500">New reservations will appear here.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-2" aria-labelledby="insights-heading">
            <div class="rounded-md border border-gray-200 bg-white p-5">
                <div class="mb-5">
                    <h2 id="insights-heading" class="text-sm font-semibold text-gray-900">Occupancy tonight</h2>
                    <p class="text-sm font-normal text-gray-500">Active spaces with a blocking reservation overlapping today.</p>
                </div>
                <div class="space-y-4">
                    @forelse($occupancyByType as $row)
                        <div>
                            <div class="mb-1.5 flex items-baseline justify-between gap-3">
                                <p class="text-sm font-semibold text-gray-900">{{ $row['label'] }}</p>
                                <p class="text-xs font-medium tabular-nums text-gray-500">{{ $row['occupied'] }} / {{ $row['total'] }} · {{ $row['pct'] }}%</p>
                            </div>
                            <div class="h-2 overflow-hidden rounded-sm bg-gray-100" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $row['pct'] }}" aria-label="{{ $row['label'] }} occupancy">
                                <div class="h-full rounded-sm bg-primary" @style(['width: '.$row['pct'].'%'])></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm font-normal text-gray-500">No active facilities to measure.</p>
                    @endforelse
                </div>
            </div>

            <div
                class="rounded-md border border-gray-200 bg-white p-5"
                x-data="{ range: 'weekly' }"
            >
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Verified revenue</h2>
                        <p class="text-sm font-normal text-gray-500">Payments already verified, not reservation value.</p>
                    </div>
                    <div class="flex rounded-md border border-gray-200 p-0.5 text-xs font-medium">
                        <button type="button" class="rounded-sm px-2.5 py-1" :class="range === 'weekly' ? 'bg-primary text-white' : 'text-gray-600'" :aria-pressed="range === 'weekly'" @click="range = 'weekly'">7 days</button>
                        <button type="button" class="rounded-sm px-2.5 py-1" :class="range === 'monthly' ? 'bg-primary text-white' : 'text-gray-600'" :aria-pressed="range === 'monthly'" @click="range = 'monthly'">12 months</button>
                    </div>
                </div>

                <div x-show="range === 'weekly'">
                    <x-admin.column-chart
                        :points="$weeklyRevenue"
                        format="currency"
                        aria-label="Verified revenue for the last 7 days"
                    />
                </div>
                <div x-show="range === 'monthly'" x-cloak>
                    <x-admin.column-chart
                        :points="$monthlyRevenue"
                        format="currency"
                        aria-label="Verified revenue for the last 12 months"
                    />
                </div>
            </div>
        </section>

        <section
            class="grid grid-cols-1 gap-6 xl:grid-cols-2"
            aria-labelledby="recent-heading"
            x-data="{
                q: '',
                status: 'all',
                boxHeight: null,
                rowVisible(el) {
                    const query = this.q.trim().toLowerCase();
                    return (this.status === 'all' || el.dataset.status === this.status) && (!query || (el.dataset.search || '').includes(query));
                },
                syncHeight() {
                    const paymentsBox = this.$refs.paymentsBox;
                    if (! paymentsBox) return;
                    this.boxHeight = paymentsBox.offsetHeight;
                }
            }"
            x-init="
                $nextTick(() => {
                    syncHeight();
                    if ($refs.paymentsBox && window.ResizeObserver) {
                        new ResizeObserver(() => syncHeight()).observe($refs.paymentsBox);
                    }
                })
            "
        >
            <div class="flex min-w-0 flex-col">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 id="recent-heading" class="text-sm font-semibold text-gray-900">Recent Reservations</h2>
                    <a href="{{ route('admin.reservations.index') }}" class="shrink-0 text-sm font-semibold text-primary hover:underline">View all</a>
                </div>
                <div
                    class="flex min-h-0 flex-col overflow-hidden rounded-md border border-gray-200 bg-white"
                    :style="boxHeight ? `height: ${boxHeight}px` : 'max-height: 20rem'"
                >
                    <div class="flex shrink-0 items-center gap-2 border-b border-gray-200 px-3 py-2">
                        <label class="sr-only" for="dashboard-reservation-search">Search reservations</label>
                        <input id="dashboard-reservation-search" type="search" x-model="q" placeholder="Search guest, space, or code" class="w-36 shrink-0 rounded-md border-gray-200 py-1.5 text-sm font-normal focus:border-primary focus:ring-primary sm:w-44">
                        <div class="flex min-w-0 flex-nowrap gap-1.5">
                            @foreach(['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved'] as $value => $label)
                                <button type="button" class="shrink-0 rounded-sm border px-2.5 py-1 text-xs font-medium" :class="status === '{{ $value }}' ? 'border-primary bg-primary text-white' : 'border-gray-200 text-gray-600'" :aria-pressed="status === '{{ $value }}'" @click="status = '{{ $value }}'">{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div class="min-h-0 flex-1 overflow-x-auto overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="sticky top-0 z-10 bg-gray-50">
                                <tr>
                                    <x-admin.table-cell :isHeader="true">Guest</x-admin.table-cell>
                                    <x-admin.table-cell :isHeader="true">Space</x-admin.table-cell>
                                    <x-admin.table-cell :isHeader="true">Status</x-admin.table-cell>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @forelse($recentReservations as $reservation)
                                    <x-admin.table-row
                                        data-status="{{ $reservation->status->value }}"
                                        data-search="{{ strtolower($reservation->guestName().' '.$reservation->facility->name.' '.$reservation->reservation_code) }}"
                                        x-show="rowVisible($el)"
                                    >
                                        <x-admin.table-cell><a href="{{ route('admin.reservations.show', $reservation) }}" class="font-semibold text-gray-900 hover:text-primary">{{ $reservation->guestName() }}</a><span class="block text-xs font-medium text-gray-500">{{ $reservation->reservation_code }}</span></x-admin.table-cell>
                                        <x-admin.table-cell>
                                            <span class="font-medium text-gray-900">{{ $reservation->facility->listingCategory() }}</span>
                                            @if($reservation->facility->unitLabel())
                                                <span class="block text-xs font-medium text-gray-500">{{ $reservation->facility->unitLabel() }}</span>
                                            @endif
                                        </x-admin.table-cell>
                                        <x-admin.table-cell><x-badge :status="$reservation->status->value" variant="compact" /></x-admin.table-cell>
                                    </x-admin.table-row>
                                @empty
                                    <x-admin.table-row><x-admin.table-cell colspan="3" class="py-8 text-center text-gray-500">No recent reservations.</x-admin.table-cell></x-admin.table-row>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="flex min-w-0 flex-col">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-gray-900">Recent Payments</h2>
                    <a href="{{ route('admin.payments.index') }}" class="shrink-0 text-sm font-semibold text-primary hover:underline">View all</a>
                </div>
                <div x-ref="paymentsBox">
                    <x-admin.table variant="flat">
                        <x-admin.table-head>
                            <x-admin.table-cell :isHeader="true">Reference</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true">Amount</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true">Status</x-admin.table-cell>
                        </x-admin.table-head>
                        <x-admin.table-body>
                            @forelse($recentPayments as $payment)
                                <x-admin.table-row>
                                    <x-admin.table-cell><a href="{{ route('admin.payments.show', $payment) }}" class="font-semibold text-gray-900 hover:text-primary">{{ $payment->reference_number ?: 'Payment #'.$payment->id }}</a><span class="block text-xs font-medium text-gray-500">{{ $payment->reservation->guestName() }}</span></x-admin.table-cell>
                                    <x-admin.table-cell>₱{{ number_format($payment->amount, 2) }}</x-admin.table-cell>
                                    <x-admin.table-cell><x-badge :status="$payment->status->value" variant="compact" /></x-admin.table-cell>
                                </x-admin.table-row>
                            @empty
                                <x-admin.table-row><x-admin.table-cell colspan="3" class="py-8 text-center text-gray-500">No recent payments.</x-admin.table-cell></x-admin.table-row>
                            @endforelse
                        </x-admin.table-body>
                    </x-admin.table>
                </div>
            </div>
        </section>
    </div>
</x-admin-layout>
