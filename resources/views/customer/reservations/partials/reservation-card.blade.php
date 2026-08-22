<div class="bg-white shadow sm:rounded-lg overflow-hidden mb-4">
    <div class="px-4 py-5 sm:px-6 flex justify-between items-center border-b border-gray-200">
        <div>
            <h3 class="text-lg leading-6 font-medium text-gray-900">
                Code: {{ $reservation->reservation_code }}
            </h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">
                {{ $reservation->facility->name }}
            </p>
        </div>
        <div>
            <x-badge :status="$reservation->status->label()" />
        </div>
    </div>
    <div class="border-t border-gray-200 px-4 py-5 sm:p-0">
        <dl class="sm:divide-y sm:divide-gray-200">
            <div class="py-3 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-gray-500">Dates</dt>
                <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $reservation->check_in->format('M d, Y h:i A') }} - {{ $reservation->check_out->format('M d, Y h:i A') }}
                </dd>
            </div>
            <div class="py-3 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-gray-500">Guests</dt>
                <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $reservation->guest_count }}
                </dd>
            </div>
            <div class="py-3 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-gray-500">Payment</dt>
                <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 flex flex-col">
                    <span>Total: ₱{{ number_format($reservation->total_price, 2) }}</span>
                    <span>Paid: ₱{{ number_format($reservation->getVerifiedTotalPaid(), 2) }}</span>
                    @if($reservation->getRemainingBalance() > 0)
                        <span class="text-red-600">Remaining: ₱{{ number_format($reservation->getRemainingBalance(), 2) }}</span>
                    @else
                        <span class="text-green-600 font-semibold">Fully Paid</span>
                    @endif
                </dd>
            </div>
        </dl>
    </div>
    <div class="px-4 py-4 sm:px-6 border-t border-gray-200 bg-gray-50 text-right flex justify-end gap-3">
        <a href="{{ route('customer.reservations.show', $reservation) }}" class="inline-flex justify-center rounded-md border border-transparent bg-primary-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
            View Details
        </a>
    </div>
</div>
