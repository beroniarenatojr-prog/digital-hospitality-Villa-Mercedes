<x-admin-layout>
    <x-slot name="header">
        Dashboard Overview
    </x-slot>

    <!-- KPI Cards -->
    <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        
        <!-- Total Reservations -->
        <x-admin.kpi-card 
            title="Total Reservations (This Month)" 
            value="{{ $bookingSummary['total'] ?? 0 }}"
            trend="Active Activity"
            :trendUp="true"
        >
            <x-slot name="icon">
                <svg class="h-6 w-6 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </x-slot>
        </x-admin.kpi-card>

        <!-- Total Revenue -->
        <x-admin.kpi-card 
            title="Verified Payments (This Month)" 
            value="₱{{ number_format(($paymentSummary['by_status']['Verified']['total_amount'] ?? 0), 2) }}"
            trend="Verified Revenue"
            :trendUp="true"
        >
            <x-slot name="icon">
                <svg class="h-6 w-6 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </x-slot>
        </x-admin.kpi-card>

        <!-- Total Customers -->
        <x-admin.kpi-card 
            title="Total Customers" 
            value="{{ $customerCount }}"
            trend="Active Userbase"
            :trendUp="true"
        >
            <x-slot name="icon">
                <svg class="h-6 w-6 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </x-slot>
        </x-admin.kpi-card>

        <!-- Facilities -->
        <x-admin.kpi-card 
            title="Active Facilities" 
            value="{{ $facilityStatuses['Available'] ?? 0 }}"
            trend="{{ ($facilityStatuses['Unavailable'] ?? 0) }} Unavailable / {{ ($facilityStatuses['Maintenance'] ?? 0) }} Maintenance"
            :trendUp="true"
        >
            <x-slot name="icon">
                <svg class="h-6 w-6 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
            </x-slot>
        </x-admin.kpi-card>
    </div>

    <!-- Recent Activity Grids -->
    <div class="mt-8 grid grid-cols-1 gap-5 lg:grid-cols-2">
        
        <!-- Recent Reservations -->
        <div>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg leading-6 font-medium text-gray-900">Recent Reservations</h2>
                <a href="{{ route('admin.reservations.index') }}" class="text-sm font-medium text-primary hover:text-primary-dark">View all</a>
            </div>
            <x-admin.table>
                <x-admin.table-head>
                    <x-admin.table-cell :isHeader="true">Customer</x-admin.table-cell>
                    <x-admin.table-cell :isHeader="true">Facility</x-admin.table-cell>
                    <x-admin.table-cell :isHeader="true">Status</x-admin.table-cell>
                </x-admin.table-head>
                <x-admin.table-body>
                    @forelse($recentReservations as $reservation)
                        <x-admin.table-row>
                            <x-admin.table-cell>
                                <div class="font-medium text-gray-900">{{ $reservation->user->name }}</div>
                                <div class="text-xs text-gray-500">{{ $reservation->reservation_code }}</div>
                            </x-admin.table-cell>
                            <x-admin.table-cell>
                                {{ $reservation->facility->name }}
                            </x-admin.table-cell>
                            <x-admin.table-cell>
                                <x-badge :status="$reservation->status->value" />
                            </x-admin.table-cell>
                        </x-admin.table-row>
                    @empty
                        <x-admin.table-row>
                            <x-admin.table-cell colspan="3" class="text-center text-gray-500">
                                No recent reservations found.
                            </x-admin.table-cell>
                        </x-admin.table-row>
                    @endforelse
                </x-admin.table-body>
            </x-admin.table>
        </div>

        <!-- Recent Payments -->
        <div>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg leading-6 font-medium text-gray-900">Recent Payments</h2>
                <a href="{{ route('admin.payments.index') }}" class="text-sm font-medium text-primary hover:text-primary-dark">View all</a>
            </div>
            <x-admin.table>
                <x-admin.table-head>
                    <x-admin.table-cell :isHeader="true">Reference</x-admin.table-cell>
                    <x-admin.table-cell :isHeader="true">Amount</x-admin.table-cell>
                    <x-admin.table-cell :isHeader="true">Status</x-admin.table-cell>
                </x-admin.table-head>
                <x-admin.table-body>
                    @forelse($recentPayments as $payment)
                        <x-admin.table-row>
                            <x-admin.table-cell>
                                <div class="font-medium text-gray-900">{{ $payment->reference_number }}</div>
                                <div class="text-xs text-gray-500">{{ $payment->reservation->user->name }}</div>
                            </x-admin.table-cell>
                            <x-admin.table-cell>
                                ₱{{ number_format($payment->amount, 2) }}
                            </x-admin.table-cell>
                            <x-admin.table-cell>
                                <x-badge :status="$payment->status->value" />
                            </x-admin.table-cell>
                        </x-admin.table-row>
                    @empty
                        <x-admin.table-row>
                            <x-admin.table-cell colspan="3" class="text-center text-gray-500">
                                No recent payments found.
                            </x-admin.table-cell>
                        </x-admin.table-row>
                    @endforelse
                </x-admin.table-body>
            </x-admin.table>
        </div>

    </div>
</x-admin-layout>
