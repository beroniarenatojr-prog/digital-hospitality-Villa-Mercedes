<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Reservation Details') }}
            </h2>
            <a href="{{ route('customer.reservations.index') }}" class="text-sm text-primary-600 hover:text-primary-800">
                &larr; Back to Dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            @if (session('success'))
                <x-alert type="success" :message="session('success')" />
            @endif

            @if (session('error'))
                <x-alert type="error" :message="session('error')" />
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Left Column: Reservation Info & Pricing -->
                <div class="md:col-span-2 space-y-6">
                    <!-- Reservation Information -->
                    <div class="bg-white shadow sm:rounded-lg">
                        <div class="px-4 py-5 border-b border-gray-200 sm:px-6 flex justify-between items-center">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                {{ $reservation->facility->name }}
                            </h3>
                            <x-badge :status="$reservation->status->label()" />
                        </div>
                        <div class="px-4 py-5 sm:p-0">
                            <dl class="sm:divide-y sm:divide-gray-200">
                                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm font-medium text-gray-500">Reservation Code</dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $reservation->reservation_code }}</dd>
                                </div>
                                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm font-medium text-gray-500">Booking Source</dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $reservation->reservation_source->label() }}</dd>
                                </div>
                                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm font-medium text-gray-500">Created On</dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $reservation->created_at->format('M d, Y h:i A') }}</dd>
                                </div>
                                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm font-medium text-gray-500">Check-in</dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $reservation->check_in->format('M d, Y h:i A') }}</dd>
                                </div>
                                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm font-medium text-gray-500">Check-out</dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $reservation->check_out->format('M d, Y h:i A') }}</dd>
                                </div>
                                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm font-medium text-gray-500">Guest Count</dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $reservation->guest_count }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Pricing Snapshot -->
                    <div class="bg-white shadow sm:rounded-lg">
                        <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                Pricing Details
                            </h3>
                        </div>
                        <div class="px-4 py-5 sm:p-0">
                            <dl class="sm:divide-y sm:divide-gray-200">
                                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm font-medium text-gray-500">Base Amount</dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">₱{{ number_format($reservation->base_price, 2) }}</dd>
                                </div>
                                @if($reservation->additional_charges > 0)
                                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm font-medium text-gray-500">Additional Charges</dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">₱{{ number_format($reservation->additional_charges, 2) }}</dd>
                                </div>
                                @endif
                                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 bg-gray-50">
                                    <dt class="text-sm font-bold text-gray-900">Total Amount</dt>
                                    <dd class="mt-1 text-sm font-bold text-primary-600 sm:mt-0 sm:col-span-2">₱{{ number_format($reservation->total_price, 2) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Payments & Actions -->
                <div class="space-y-6">
                    <!-- Payment Status -->
                    <div class="bg-white shadow sm:rounded-lg">
                        <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                Payment Status
                            </h3>
                        </div>
                        <div class="px-4 py-5 sm:p-6 space-y-4">
                            <div class="flex justify-between items-center border-b pb-2">
                                <span class="text-sm text-gray-500">Total Amount</span>
                                <span class="font-medium">₱{{ number_format($reservation->total_price, 2) }}</span>
                            </div>
                            <div class="flex justify-between items-center border-b pb-2">
                                <span class="text-sm text-gray-500">Verified Paid</span>
                                <span class="font-medium text-green-600">₱{{ number_format($reservation->getVerifiedTotalPaid(), 2) }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-500">Remaining Balance</span>
                                @if($reservation->getRemainingBalance() > 0)
                                    <span class="font-bold text-red-600">₱{{ number_format($reservation->getRemainingBalance(), 2) }}</span>
                                @else
                                    <span class="font-bold text-green-600">₱0.00</span>
                                @endif
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-4 sm:px-6 border-t border-gray-200 text-center">
                            @if($reservation->getRemainingBalance() > 0)
                                <a href="{{ route('customer.payments.index', $reservation) }}" class="text-sm font-medium text-primary-600 hover:text-primary-500">
                                    Manage Payments &rarr;
                                </a>
                            @else
                                <span class="text-sm font-medium text-green-600">Fully Paid</span>
                            @endif
                        </div>
                    </div>

                    <!-- Actions -->
                    @inject('reservationService', 'App\Services\Customer\CustomerReservationService')
                    @if($reservationService->canReschedule($reservation) || $reservationService->canCancel($reservation))
                        <div class="bg-white shadow sm:rounded-lg">
                            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">
                                    Actions
                                </h3>
                            </div>
                            <div class="p-6 flex flex-col space-y-3">
                                @if($reservationService->canReschedule($reservation))
                                    @include('customer.reservations.partials.reschedule-modal')
                                @endif

                                @if($reservationService->canCancel($reservation))
                                    @include('customer.reservations.partials.cancel-modal')
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
