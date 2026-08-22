<x-admin-layout>
    <x-slot name="header">
        Payment Detail
    </x-slot>

    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm text-gray-500">
        <a href="{{ route('admin.payments.index') }}" class="hover:text-primary">Payments</a>
        <span class="mx-2">/</span>
        <span class="text-gray-900">Payment #{{ $payment->id }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Payment Info Card -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4 border-b pb-2">
                    <h3 class="text-lg font-semibold text-gray-900">Payment Information</h3>
                    <x-badge :status="$payment->status->value" />
                </div>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Payment ID</dt>
                        <dd class="mt-1 text-sm font-semibold text-gray-900">#{{ $payment->id }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Amount</dt>
                        <dd class="mt-1 text-lg font-bold text-gray-900">₱{{ number_format($payment->amount, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Payment Method</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $payment->method->label() }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Reference Number</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $payment->reference_number ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Date Submitted</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $payment->payment_date->format('F j, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1 text-sm font-semibold {{ $payment->status === \App\Enums\PaymentStatus::Verified ? 'text-success' : ($payment->status === \App\Enums\PaymentStatus::Rejected ? 'text-danger' : 'text-amber-600') }}">
                            {{ $payment->status->label() }}
                        </dd>
                    </div>
                    @if($payment->status !== \App\Enums\PaymentStatus::Pending)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ $payment->status === \App\Enums\PaymentStatus::Verified ? 'Verified' : 'Reviewed' }} By</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $payment->verifiedBy?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ $payment->status === \App\Enums\PaymentStatus::Verified ? 'Verified' : 'Reviewed' }} At</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $payment->verified_at?->format('M d, Y h:i A') ?? '—' }}</dd>
                        </div>
                    @endif
                    @if($payment->status === \App\Enums\PaymentStatus::Rejected && $payment->rejection_reason)
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Rejection Reason</dt>
                            <dd class="mt-1 text-sm text-danger bg-red-50 p-3 rounded-md">{{ $payment->rejection_reason }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <!-- Proof of Payment -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Proof of Payment</h3>
                @if($payment->proof_path)
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0">
                            <svg class="h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900">Proof file available</p>
                            <p class="text-xs text-gray-500">File type: {{ pathinfo($payment->proof_path, PATHINFO_EXTENSION) ?: 'Unknown' }}</p>
                        </div>
                        <a href="{{ route('admin.payments.download', $payment) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition" target="_blank">
                            <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Download
                        </a>
                    </div>
                @else
                    <div class="text-center py-6">
                        <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                        </svg>
                        <p class="mt-2 text-sm text-gray-500">No proof of payment was attached.</p>
                    </div>
                @endif
            </div>

            <!-- Reservation Context -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4 border-b pb-2">
                    <h3 class="text-lg font-semibold text-gray-900">Reservation Context</h3>
                    <a href="{{ route('admin.reservations.show', $payment->reservation) }}" class="text-sm font-medium text-primary hover:text-primary-dark">View Reservation</a>
                </div>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Reservation Code</dt>
                        <dd class="mt-1 text-sm font-mono font-semibold text-gray-900">{{ $payment->reservation->reservation_code }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1"><x-badge :status="$payment->reservation->status->value" /></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Facility</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $payment->reservation->facility->name }} ({{ $payment->reservation->facility->facilityType->name }})</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Guest Count</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $payment->reservation->guest_count }} persons</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Check-in</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $payment->reservation->check_in->format('M d, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Check-out</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $payment->reservation->check_out->format('M d, Y') }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Customer -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Customer</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Name</dt>
                        <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $payment->reservation->user->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Email</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <a href="mailto:{{ $payment->reservation->user->email }}" class="text-primary hover:underline">{{ $payment->reservation->user->email }}</a>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6" x-data="{ action: null }">

            <!-- Balance Card -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Balance Summary</h3>
                @php
                    $reservationTotal = $payment->reservation->total_price;
                    $verifiedPaid = $payment->reservation->payments->where('status', \App\Enums\PaymentStatus::Verified)->sum('amount');
                    $balance = $reservationTotal - $verifiedPaid;
                @endphp
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Reservation Total</span>
                        <span class="font-medium text-gray-900">₱{{ number_format($reservationTotal, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm text-success">
                        <span class="font-medium">Verified Paid</span>
                        <span class="font-bold">₱{{ number_format($verifiedPaid, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-base font-bold pt-3 border-t {{ $balance > 0 ? 'text-danger' : 'text-success' }}">
                        <span>Remaining</span>
                        <span>₱{{ number_format(max(0, $balance), 2) }}</span>
                    </div>
                    @if($balance < 0)
                        <p class="text-xs text-amber-600 mt-1">Overpayment of ₱{{ number_format(abs($balance), 2) }} detected.</p>
                    @endif
                </div>
            </div>

            <!-- Other Payments on This Reservation -->
            @php
                $otherPayments = $payment->reservation->payments->where('id', '!=', $payment->id);
            @endphp
            @if($otherPayments->count() > 0)
                <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Other Payments</h3>
                    <div class="space-y-3">
                        @foreach($otherPayments as $otherPayment)
                            <a href="{{ route('admin.payments.show', $otherPayment) }}" class="block p-3 rounded-md border border-gray-100 hover:bg-gray-50 transition">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm font-semibold text-gray-900">₱{{ number_format($otherPayment->amount, 2) }}</span>
                                    <x-badge :status="$otherPayment->status->value" />
                                </div>
                                <p class="text-xs text-gray-500 mt-1">{{ $otherPayment->method->label() }} · {{ $otherPayment->payment_date->format('M d, Y') }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Action Buttons -->
            @if($payment->status === \App\Enums\PaymentStatus::Pending)
                <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 space-y-3">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2 border-b pb-2">Payment Actions</h3>
                    <p class="text-sm text-gray-600 mb-4">This payment is awaiting verification.</p>

                    <button @click="action = 'verify'" class="w-full px-4 py-2 bg-success text-white rounded-md text-sm font-semibold hover:bg-green-700 transition">
                        Verify Payment
                    </button>

                    <button @click="action = 'reject'" class="w-full px-4 py-2 border border-danger text-danger bg-white rounded-md text-sm font-semibold hover:bg-danger hover:text-white transition">
                        Reject Payment
                    </button>
                </div>
            @else
                <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2 border-b pb-2">Payment Actions</h3>
                    <p class="text-sm text-gray-500">This payment has been <strong>{{ strtolower($payment->status->label()) }}</strong>. No further actions are available.</p>
                </div>
            @endif

            <!-- Verify Modal -->
            <div x-show="action === 'verify'" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="action = null"></div>
                    <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6 z-50">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Verify Payment</h3>
                        <p class="text-sm text-gray-500 mb-2">You are about to verify this payment:</p>
                        <div class="bg-gray-50 rounded-md p-3 mb-4 text-sm">
                            <p><strong>Amount:</strong> ₱{{ number_format($payment->amount, 2) }}</p>
                            <p><strong>Method:</strong> {{ $payment->method->label() }}</p>
                            <p><strong>Reservation:</strong> {{ $payment->reservation->reservation_code }}</p>
                        </div>
                        <p class="text-sm text-gray-500 mb-4">This will mark the payment as verified and notify the customer.</p>
                        <form method="POST" action="{{ route('admin.payments.verify', $payment) }}" class="flex justify-end gap-3">
                            @csrf
                            @method('PATCH')
                            <button type="button" @click="action = null" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-success text-white rounded-md text-sm font-semibold hover:bg-green-700">Verify Payment</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Reject Modal -->
            <div x-show="action === 'reject'" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="action = null"></div>
                    <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6 z-50">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Reject Payment</h3>
                        <p class="text-sm text-gray-500 mb-2">You are about to reject this payment:</p>
                        <div class="bg-gray-50 rounded-md p-3 mb-4 text-sm">
                            <p><strong>Amount:</strong> ₱{{ number_format($payment->amount, 2) }}</p>
                            <p><strong>Method:</strong> {{ $payment->method->label() }}</p>
                            <p><strong>Reservation:</strong> {{ $payment->reservation->reservation_code }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.payments.reject', $payment) }}">
                            @csrf
                            @method('PATCH')
                            <div class="mb-4">
                                <label for="reject_reason" class="block text-sm font-medium text-gray-700">Rejection Reason <span class="text-danger">*</span></label>
                                <textarea name="reason" id="reject_reason" rows="3" required
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                                          placeholder="Explain why this payment is being rejected..."></textarea>
                                <p class="mt-1 text-xs text-gray-400">This reason will be visible to the customer.</p>
                            </div>
                            <div class="flex justify-end gap-3">
                                <button type="button" @click="action = null" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</button>
                                <button type="submit" class="px-4 py-2 bg-danger text-white rounded-md text-sm font-semibold hover:bg-red-700">Reject Payment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-admin-layout>
