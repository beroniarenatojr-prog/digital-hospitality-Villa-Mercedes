<x-admin-layout>
    <x-slot name="header">
        {{ $customer->name }}
    </x-slot>

    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm text-gray-500">
        <a href="{{ route('admin.customers.index') }}" class="hover:text-primary">Customers</a>
        <span class="mx-2">/</span>
        <span class="text-gray-900">{{ $customer->name }}</span>
    </nav>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Reservations</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['total_reservations'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Upcoming</p>
            <p class="text-2xl font-bold text-primary mt-1">{{ $stats['upcoming'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Verified Paid</p>
            <p class="text-2xl font-bold text-success mt-1">₱{{ number_format($paymentStats['verified_total'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Pending Payments</p>
            <p class="text-2xl font-bold text-amber-600 mt-1">{{ $paymentStats['pending_count'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Customer Information -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Customer Information</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Full Name</dt>
                        <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $customer->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Email Address</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <a href="mailto:{{ $customer->email }}" class="text-primary hover:underline">{{ $customer->email }}</a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Phone</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $customer->phone ?: 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Registered</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $customer->created_at->format('F j, Y') }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Reservation History -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Reservation History</h3>
                @if($customer->reservations->count() > 0)
                    <div class="space-y-3">
                        @foreach($customer->reservations->sortByDesc('created_at') as $reservation)
                            <div class="flex items-center justify-between p-3 rounded-md border border-gray-100 hover:bg-gray-50 transition">
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('admin.reservations.show', $reservation) }}" class="font-mono text-sm font-semibold text-primary hover:text-primary-dark">
                                        {{ $reservation->reservation_code }}
                                    </a>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        {{ $reservation->facility->name }} · {{ $reservation->check_in->format('M d') }}–{{ $reservation->check_out->format('M d, Y') }} · {{ $reservation->guest_count }} pax
                                    </p>
                                </div>
                                <div class="flex items-center gap-3 ml-4 flex-shrink-0">
                                    <span class="text-sm font-semibold text-gray-900">₱{{ number_format($reservation->total_price, 2) }}</span>
                                    <x-badge :status="$reservation->status->value" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-center text-gray-500 py-6">This customer has no reservations yet.</p>
                @endif
            </div>

            <!-- Payment History -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Payment History</h3>
                @php
                    $allPayments = $customer->reservations->flatMap->payments->sortByDesc('created_at');
                @endphp
                @if($allPayments->count() > 0)
                    <div class="space-y-3">
                        @foreach($allPayments as $payment)
                            <div class="flex items-center justify-between p-3 rounded-md border border-gray-100 hover:bg-gray-50 transition">
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('admin.payments.show', $payment) }}" class="text-sm font-semibold text-primary hover:text-primary-dark">
                                        ₱{{ number_format($payment->amount, 2) }}
                                    </a>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        {{ $payment->method->label() }} · {{ $payment->payment_date->format('M d, Y') }} · {{ $payment->reservation->reservation_code }}
                                    </p>
                                </div>
                                <div class="flex-shrink-0 ml-4">
                                    <x-badge :status="$payment->status->value" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-center text-gray-500 py-6">This customer has no payment records yet.</p>
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Reservation Summary -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Reservation Summary</h3>
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Total Reservations</span>
                        <span class="font-semibold text-gray-900">{{ $stats['total_reservations'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Upcoming</span>
                        <span class="font-semibold text-primary">{{ $stats['upcoming'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Completed</span>
                        <span class="font-semibold text-success">{{ $stats['completed'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Cancelled / Rejected</span>
                        <span class="font-semibold text-gray-500">{{ $stats['cancelled'] }}</span>
                    </div>
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Payment Summary</h3>
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Total Payments</span>
                        <span class="font-semibold text-gray-900">{{ $paymentStats['total_payments'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Verified Amount</span>
                        <span class="font-bold text-success">₱{{ number_format($paymentStats['verified_total'], 2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Pending</span>
                        <span class="font-semibold text-amber-600">{{ $paymentStats['pending_count'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Rejected</span>
                        <span class="font-semibold text-danger">{{ $paymentStats['rejected_count'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
