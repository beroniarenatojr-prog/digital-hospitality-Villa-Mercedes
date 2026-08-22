<x-admin-layout>
    <x-slot name="header">
        Payment Management
    </x-slot>

    <!-- Filters -->
    <div class="mb-6 bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <form method="GET" action="{{ route('admin.payments.index') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <!-- Search -->
            <div>
                <label for="search" class="block text-xs font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Reservation code, Name, Email"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
            </div>

            <!-- Status -->
            <div>
                <label for="status" class="block text-xs font-medium text-gray-700 mb-1">Payment Status</label>
                <select name="status" id="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                    <option value="">All Statuses</option>
                    @foreach(\App\Enums\PaymentStatus::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Method -->
            <div>
                <label for="method" class="block text-xs font-medium text-gray-700 mb-1">Payment Method</label>
                <select name="method" id="method" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                    <option value="">All Methods</option>
                    @foreach(\App\Enums\PaymentMethod::cases() as $method)
                        <option value="{{ $method->value }}" {{ request('method') === $method->value ? 'selected' : '' }}>{{ $method->label() }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Facility Type -->
            <div>
                <label for="facility_type" class="block text-xs font-medium text-gray-700 mb-1">Facility Type</label>
                <select name="facility_type" id="facility_type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                    <option value="">All Types</option>
                    @foreach($facilityTypes as $type)
                        <option value="{{ $type->slug }}" {{ request('facility_type') === $type->slug ? 'selected' : '' }}>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Facility -->
            <div>
                <label for="facility_id" class="block text-xs font-medium text-gray-700 mb-1">Facility</label>
                <select name="facility_id" id="facility_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                    <option value="">All Facilities</option>
                    @foreach($facilities as $facility)
                        <option value="{{ $facility->id }}" {{ request('facility_id') == $facility->id ? 'selected' : '' }}>{{ $facility->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Date Range -->
            <div>
                <label for="date_from" class="block text-xs font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
            </div>
            <div>
                <label for="date_until" class="block text-xs font-medium text-gray-700 mb-1">Date Until</label>
                <input type="date" name="date_until" id="date_until" value="{{ request('date_until') }}"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
            </div>

            <!-- Actions -->
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 inline-flex justify-center items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-dark transition">
                    Filter
                </button>
                @if(request()->anyFilled(['search', 'status', 'method', 'facility_type', 'facility_id', 'date_from', 'date_until']))
                    <a href="{{ route('admin.payments.index') }}" class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                        Clear
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Desktop Table -->
    <div class="hidden md:block">
        <x-admin.table>
            <x-admin.table-head>
                <x-admin.table-cell :isHeader="true">Reservation</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Customer</x-admin.table-cell>
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
                            <span class="font-medium text-gray-900">{{ $payment->reservation->user->name }}</span>
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

    <!-- Mobile Cards -->
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
                    <p><span class="font-medium text-gray-900">{{ $payment->reservation->user->name }}</span></p>
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

    <!-- Pagination -->
    <div class="mt-6">
        {{ $payments->withQueryString()->links() }}
    </div>
</x-admin-layout>
