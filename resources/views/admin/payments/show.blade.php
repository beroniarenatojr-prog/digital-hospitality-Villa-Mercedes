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

            @include('admin.payments.deposit-review')
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
                    @if($payment->source === 'onsite')
                        <div><dt class="text-sm font-medium text-gray-500">Collected at the resort</dt><dd>{{ $payment->collected_at?->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }} Philippine time</dd></div>
                    @endif
                    @if($payment->reconciliation_note)
                        <div><dt class="text-sm font-medium text-gray-500">Reconciliation note</dt><dd>{{ $payment->reconciliation_note }}</dd></div>
                    @endif
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
                            <dd class="mt-1 text-sm text-gray-900">{{ $payment->verifiedBy?->name ?? 'Payment gateway (automatic)' }}</dd>
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
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6" x-data="{ preview: false }">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Proof of Payment</h3>
                @if($payment->proof_path)
                    @php
                        $proofViewUrl = route('admin.payments.view', $payment);
                    @endphp
                    <div class="space-y-4">
                        @if($payment->isImageProof())
                            <button type="button" @click="preview = true" class="block w-full rounded-lg border border-gray-200 bg-gray-50 overflow-hidden focus:outline-none focus:ring-2 focus:ring-primary">
                                <img src="{{ $proofViewUrl }}" alt="Proof of payment" class="max-h-[28rem] w-full object-contain cursor-zoom-in">
                            </button>
                            <p class="text-xs text-gray-500">Click the image to enlarge it.</p>
                        @elseif($payment->isPdfProof())
                            <iframe src="{{ $proofViewUrl }}" title="Proof of payment" class="w-full h-[32rem] rounded-lg border border-gray-200 bg-white"></iframe>
                        @else
                            <p class="text-sm text-gray-600">This file type cannot be previewed here. Open it in a new tab or download it.</p>
                        @endif

                        <div class="flex flex-wrap gap-3">
                            <a href="{{ $proofViewUrl }}" target="_blank" rel="noopener" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-md text-sm font-semibold hover:bg-primary-dark transition">
                                Open proof
                            </a>
                            <a href="{{ route('admin.payments.download', $payment) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition">
                                Download
                            </a>
                        </div>
                    </div>

                    <div x-show="preview" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                        <div class="flex min-h-full items-center justify-center p-4">
                            <div class="fixed inset-0 bg-gray-900/80" @click="preview = false"></div>
                            <div class="relative z-50 max-w-5xl w-full">
                                <button type="button" @click="preview = false" class="mb-3 text-sm font-semibold text-white hover:underline">Close</button>
                                <img src="{{ $proofViewUrl }}" alt="Proof of payment enlarged" class="max-h-[90vh] w-full object-contain rounded-lg bg-black">
                            </div>
                        </div>
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
                        <form method="POST" action="{{ route('admin.payments.verify', $payment) }}" class="flex flex-wrap justify-end gap-3">
                            @csrf
                            @method('PATCH')
                            @if ($payment->source === 'customer_deposit')
                                <label class="w-full text-sm"><input type="checkbox" name="funds_received" value="1" required> I matched this transaction and amount against the resort's receiving account.</label>
                                <label class="w-full text-sm">Duplicate-reference or other reconciliation note
                                    <textarea name="overpayment_note" maxlength="500" rows="3" class="mt-2 block w-full rounded-md border-gray-300">{{ old('overpayment_note') }}</textarea>
                                </label>
                            @elseif ((float) $payment->amount > $payment->reservation->getRemainingBalance())
                                <div class="w-full rounded-md bg-amber-50 p-3 text-sm">
                                    <label for="overpayment_note" class="block font-medium text-amber-900">Overpayment reconciliation note</label>
                                    <p class="mt-1 text-amber-900">This amount exceeds the remaining balance. Verify only after confirming the funds were received. Record the excess and the follow-up needed.</p>
                                    <textarea id="overpayment_note" name="overpayment_note" required maxlength="500" rows="3" class="mt-2 block w-full rounded-md border-gray-300">{{ old('overpayment_note') }}</textarea>
                                </div>
                            @endif
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
                            @if($payment->source === 'customer_deposit')
                                <label class="block mb-4 text-sm"><input type="checkbox" name="funds_not_received" value="1" required> I checked the receiving account and confirmed these funds were not received. If the transfer is uncertain, leave this proof pending and contact the customer.</label>
                            @endif
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
