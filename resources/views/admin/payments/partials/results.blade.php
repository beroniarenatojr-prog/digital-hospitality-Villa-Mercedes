<div class="hidden md:block">
    <x-admin.table>
        <x-admin.table-head>
            <x-admin.table-cell :isHeader="true">Reservation</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Guest</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Amount</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Method</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Submitted</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Status</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Actions</x-admin.table-cell>
        </x-admin.table-head>
        <x-admin.table-body>
            @forelse($payments as $payment)
                <x-admin.table-row class="{{ $payment->status === \App\Enums\PaymentStatus::Pending ? 'bg-amber-50/50' : '' }}">
                    <x-admin.table-cell>
                        <a href="{{ route('admin.reservations.show', $payment->reservation) }}" class="font-mono text-sm font-semibold text-primary hover:text-primary-dark">
                            {{ $payment->reservation->reservation_code }}
                        </a>
                        <span class="block text-xs text-gray-500">{{ $payment->reservation->facility->name }}</span>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <span class="font-medium text-gray-900">{{ $payment->reservation->guestName() }}</span>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <span class="font-semibold text-gray-900">₱{{ number_format($payment->amount, 2) }}</span>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <span class="text-sm text-gray-700">{{ $payment->method->label() }}</span>
                        @if($payment->reference_number)
                            <span class="block text-xs text-gray-400 font-mono">{{ $payment->reference_number }}</span>
                        @endif
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <span class="text-sm text-gray-700">{{ $payment->payment_date->format('M d, Y') }}</span>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <x-badge :status="$payment->status->value" />
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <a href="{{ route('admin.payments.show', $payment) }}" class="text-primary hover:text-primary-dark text-sm font-medium">
                            {{ $payment->status === \App\Enums\PaymentStatus::Pending ? 'Review' : 'View' }}
                        </a>
                    </x-admin.table-cell>
                </x-admin.table-row>
            @empty
                <x-admin.table-row>
                    <x-admin.table-cell colspan="7" class="text-center py-8 text-gray-500">
                        No payments found matching your criteria.
                    </x-admin.table-cell>
                </x-admin.table-row>
            @endforelse
        </x-admin.table-body>
    </x-admin.table>
</div>

<div class="md:hidden space-y-4">
    @forelse($payments as $payment)
        <div class="bg-white rounded-lg shadow-sm border {{ $payment->status === \App\Enums\PaymentStatus::Pending ? 'border-amber-300' : 'border-gray-200' }} p-4">
            <div class="flex items-start justify-between mb-2">
                <div>
                    <a href="{{ route('admin.payments.show', $payment) }}" class="font-mono text-sm font-semibold text-primary hover:text-primary-dark">
                        {{ $payment->reservation->reservation_code }}
                    </a>
                    <span class="block text-xs text-gray-500">{{ $payment->reservation->facility->name }}</span>
                </div>
                <x-badge :status="$payment->status->value" />
            </div>
            <div class="text-sm text-gray-600 space-y-1 mt-3">
                <p><span class="font-medium text-gray-900">{{ $payment->reservation->guestName() }}</span></p>
                <p><span class="font-medium">Amount:</span> <span class="font-bold text-gray-900">₱{{ number_format($payment->amount, 2) }}</span></p>
                <p><span class="font-medium">Method:</span> {{ $payment->method->label() }}
                    @if($payment->reference_number)
                        <span class="font-mono text-xs text-gray-400">({{ $payment->reference_number }})</span>
                    @endif
                </p>
                <p><span class="font-medium">Submitted:</span> {{ $payment->payment_date->format('M d, Y') }}</p>
            </div>
            <div class="mt-4 pt-3 border-t border-gray-100">
                <a href="{{ route('admin.payments.show', $payment) }}" class="w-full text-center block px-4 py-2 border border-primary rounded-md text-sm font-medium text-primary hover:bg-primary hover:text-white transition">
                    {{ $payment->status === \App\Enums\PaymentStatus::Pending ? 'Review Payment' : 'View Details' }}
                </a>
            </div>
        </div>
    @empty
        <p class="text-center text-gray-500 py-8">No payments found.</p>
    @endforelse
</div>

<div class="mt-6">
    {{ $payments->withQueryString()->links() }}
</div>
