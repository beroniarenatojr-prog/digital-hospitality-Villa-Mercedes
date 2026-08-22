<x-admin-layout>
    <x-slot name="header">
        Reservation {{ $reservation->reservation_code }}
    </x-slot>

    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm text-gray-500">
        <a href="{{ route('admin.reservations.index') }}" class="hover:text-primary">Reservations</a>
        <span class="mx-2">/</span>
        <span class="text-gray-900">{{ $reservation->reservation_code }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Header Card -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 font-mono">{{ $reservation->reservation_code }}</h2>
                    <p class="text-sm text-gray-500 mt-1">Created {{ $reservation->created_at->format('M d, Y h:i A') }} via {{ $reservation->reservation_source->label() }}</p>
                </div>
                <x-badge :status="$reservation->status->value" />
            </div>

            <!-- Customer Details -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Customer Information</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Name</dt>
                        <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $reservation->user->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Email Address</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <a href="mailto:{{ $reservation->user->email }}" class="text-primary hover:underline">{{ $reservation->user->email }}</a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Phone</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $reservation->user->phone ?? 'Not provided' }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Reservation Details -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Reservation Details</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4">
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Facility</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-medium">
                            <a href="{{ route('admin.facilities.show', $reservation->facility) }}" class="text-primary hover:underline">{{ $reservation->facility->name }}</a>
                            <span class="text-gray-500">({{ $reservation->facility->facilityType->name }})</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Check-in</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $reservation->check_in->format('l, F j, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Check-out</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $reservation->check_out->format('l, F j, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Guest Count</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $reservation->guest_count }} persons</dd>
                    </div>
                    @if($reservation->special_requests)
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Special Requests</dt>
                        <dd class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded-md">{{ $reservation->special_requests }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

            <!-- Pricing Snapshot -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Pricing Snapshot</h3>
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Base Price</span>
                        <span class="font-medium text-gray-900">₱{{ number_format($reservation->base_price, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Additional Charges (Early/Late/Extra Person)</span>
                        <span class="font-medium text-gray-900">₱{{ number_format($reservation->additional_charges, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-base font-bold pt-3 border-t">
                        <span class="text-gray-900">Total Price</span>
                        <span class="text-gray-900">₱{{ number_format($reservation->total_price, 2) }}</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">Historical pricing snapshot. Not recalculated on display.</p>
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4 border-b pb-2">
                    <h3 class="text-lg font-semibold text-gray-900">Payment Summary</h3>
                    <a href="{{ route('admin.payments.index', ['reservation_code' => $reservation->reservation_code]) }}" class="text-sm font-medium text-primary hover:text-primary-dark">View Payments</a>
                </div>
                @php
                    $verifiedPaid = $reservation->payments->where('status', \App\Enums\PaymentStatus::Verified)->sum('amount');
                    $balance = $reservation->total_price - $verifiedPaid;
                @endphp
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Total Reservation Value</span>
                        <span class="font-medium text-gray-900">₱{{ number_format($reservation->total_price, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm text-success">
                        <span class="font-medium">Verified Paid</span>
                        <span class="font-bold">₱{{ number_format($verifiedPaid, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-base font-bold pt-3 border-t {{ $balance > 0 ? 'text-danger' : 'text-success' }}">
                        <span>Remaining Balance</span>
                        <span>₱{{ number_format(max(0, $balance), 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Activity Logs (Simplified) -->
            @if($reservation->activities && $reservation->activities->count() > 0)
                <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Recent Activity</h3>
                    <ul class="space-y-4">
                        @foreach($reservation->activities->take(5) as $activity)
                            <li class="flex gap-4">
                                <div class="flex-shrink-0 mt-1">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-900">{{ $activity->description }}</p>
                                    <p class="text-xs text-gray-500">{{ $activity->created_at->format('M d, Y h:i A') }} by {{ $activity->causer->name ?? 'System' }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <!-- Sidebar Actions -->
        <div class="space-y-6" x-data="{ action: null }">
            
            @if($reservation->status === \App\Enums\ReservationStatus::Pending)
                <!-- Pending Actions -->
                <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 space-y-3">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2 border-b pb-2">Status Actions</h3>
                    <p class="text-sm text-gray-600 mb-4">This reservation is pending approval.</p>
                    
                    <button @click="action = 'approve'" class="w-full px-4 py-2 bg-success text-white rounded-md text-sm font-semibold hover:bg-green-700 transition">
                        Approve Reservation
                    </button>
                    
                    <button @click="action = 'reject'" class="w-full px-4 py-2 border border-danger text-danger bg-white rounded-md text-sm font-semibold hover:bg-danger hover:text-white transition">
                        Reject Reservation
                    </button>
                </div>
            @endif

            @if(in_array($reservation->status, [\App\Enums\ReservationStatus::Pending, \App\Enums\ReservationStatus::Approved]))
                <!-- Modify Actions -->
                <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 space-y-3">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2 border-b pb-2">Modify Reservation</h3>
                    
                    <button @click="action = 'reschedule'" class="w-full px-4 py-2 border border-primary text-primary bg-white rounded-md text-sm font-semibold hover:bg-primary-50 transition">
                        Reschedule
                    </button>
                    
                    @if($reservation->status === \App\Enums\ReservationStatus::Approved)
                        <button @click="action = 'cancel'" class="w-full px-4 py-2 border border-danger text-danger bg-white rounded-md text-sm font-semibold hover:bg-danger hover:text-white transition mt-2">
                            Cancel Reservation
                        </button>
                    @endif
                </div>
            @endif

            <!-- Modals -->

            <!-- Approve Modal -->
            <div x-show="action === 'approve'" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="action = null"></div>
                    <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6 z-50">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Approve Reservation</h3>
                        <p class="text-sm text-gray-500 mb-6">Are you sure you want to approve this reservation? The customer will be notified.</p>
                        <form method="POST" action="{{ route('admin.reservations.approve', $reservation) }}" class="flex justify-end gap-3">
                            @csrf
                            @method('PATCH')
                            <button type="button" @click="action = null" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-success text-white rounded-md text-sm font-semibold hover:bg-green-700">Yes, Approve</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Reject Modal -->
            <div x-show="action === 'reject'" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="action = null"></div>
                    <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6 z-50">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Reject Reservation</h3>
                        <form method="POST" action="{{ route('admin.reservations.reject', $reservation) }}">
                            @csrf
                            @method('PATCH')
                            <div class="mb-4">
                                <label for="reject_reason" class="block text-sm font-medium text-gray-700">Reason for Rejection <span class="text-danger">*</span></label>
                                <textarea name="reason" id="reject_reason" rows="3" required
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                                          placeholder="Explain why the reservation is rejected..."></textarea>
                            </div>
                            <div class="flex justify-end gap-3">
                                <button type="button" @click="action = null" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</button>
                                <button type="submit" class="px-4 py-2 bg-danger text-white rounded-md text-sm font-semibold hover:bg-red-700">Reject Reservation</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Cancel Modal -->
            <div x-show="action === 'cancel'" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="action = null"></div>
                    <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6 z-50">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Cancel Reservation</h3>
                        <form method="POST" action="{{ route('admin.reservations.cancel', $reservation) }}">
                            @csrf
                            @method('PATCH')
                            <div class="mb-4">
                                <label for="cancel_reason" class="block text-sm font-medium text-gray-700">Reason for Cancellation (Optional)</label>
                                <textarea name="reason" id="cancel_reason" rows="3"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                                          placeholder="Provide a reason..."></textarea>
                            </div>
                            <div class="flex justify-end gap-3">
                                <button type="button" @click="action = null" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Keep Reservation</button>
                                <button type="submit" class="px-4 py-2 bg-danger text-white rounded-md text-sm font-semibold hover:bg-red-700">Cancel Reservation</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Reschedule Modal -->
            <div x-show="action === 'reschedule'" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;"
                 x-data="{
                    step: 1,
                    loading: false,
                    error: null,
                    previewData: null,
                    form: {
                        facility_id: '{{ $reservation->facility_id }}',
                        check_in: '{{ $reservation->check_in->format('Y-m-d') }}',
                        check_out: '{{ $reservation->check_out->format('Y-m-d') }}',
                        guestCount: {{ $reservation->guest_count }}
                    },
                    async calculate() {
                        this.loading = true;
                        this.error = null;
                        try {
                            const res = await fetch('{{ route('admin.reservations.calculate-reschedule', $reservation) }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify(this.form)
                            });
                            const data = await res.json();
                            if (!res.ok) throw new Error(data.message || 'Validation failed');
                            
                            this.previewData = data;
                            this.step = 2;
                        } catch (e) {
                            this.error = e.message;
                        } finally {
                            this.loading = false;
                        }
                    }
                 }">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="action = null"></div>
                    <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full p-6 z-50">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2">Reschedule Reservation</h3>
                        
                        <!-- Step 1: Input -->
                        <div x-show="step === 1">
                            <div x-show="error" class="mb-4 p-3 bg-red-50 text-danger text-sm rounded-md" x-text="error" x-transition></div>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Facility</label>
                                    <select x-model="form.facility_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                                        @foreach($facilities as $fac)
                                            <option value="{{ $fac->id }}">{{ $fac->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Check-in</label>
                                        <input type="date" x-model="form.check_in" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Check-out</label>
                                        <input type="date" x-model="form.check_out" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Guests (Pax)</label>
                                    <input type="number" x-model="form.guestCount" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                                </div>
                            </div>
                            <div class="flex justify-end gap-3 mt-6">
                                <button type="button" @click="action = null" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</button>
                                <button type="button" @click="calculate()" :disabled="loading" class="px-4 py-2 bg-primary text-white rounded-md text-sm font-semibold hover:bg-primary-dark disabled:opacity-50 inline-flex items-center">
                                    <svg x-show="loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Preview Changes
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Preview -->
                        <div x-show="step === 2" style="display: none;">
                            <div class="bg-blue-50 border border-blue-100 p-4 rounded-md mb-4">
                                <h4 class="text-sm font-semibold text-blue-800 mb-2">New Reservation Preview</h4>
                                <dl class="grid grid-cols-2 gap-2 text-sm text-blue-900">
                                    <dt class="font-medium">Facility:</dt>
                                    <dd x-text="previewData?.facility_name"></dd>
                                    <dt class="font-medium">Dates:</dt>
                                    <dd x-text="previewData?.dates"></dd>
                                    <dt class="font-medium">Guests:</dt>
                                    <dd x-text="previewData?.pax + ' persons'"></dd>
                                    <dt class="font-medium mt-2">New Total:</dt>
                                    <dd class="mt-2 font-bold" x-text="'₱' + previewData?.breakdown.total_amount.toLocaleString(undefined, {minimumFractionDigits: 2})"></dd>
                                </dl>
                            </div>
                            <x-alert type="warning">
                                Finalizing this reschedule will permanently change the reservation details. Availability and pricing will be revalidated on submission.
                            </x-alert>
                            
                            <form method="POST" action="{{ route('admin.reservations.reschedule', $reservation) }}" class="flex justify-end gap-3 mt-6">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="facility_id" :value="form.facility_id">
                                <input type="hidden" name="check_in" :value="form.check_in">
                                <input type="hidden" name="check_out" :value="form.check_out">
                                <input type="hidden" name="guestCount" :value="form.guestCount">
                                
                                <button type="button" @click="step = 1" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Back</button>
                                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-md text-sm font-semibold hover:bg-primary-dark">Confirm Reschedule</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-admin-layout>
