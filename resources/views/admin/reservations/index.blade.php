<x-admin-layout>
    <x-slot name="header">
        Reservation Management
    </x-slot>

    <!-- Filters & Actions Bar -->
    <div class="mb-6 bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <form method="GET" action="{{ route('admin.reservations.index') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <!-- Search -->
            <div>
                <label for="search" class="block text-xs font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Code, Name, Email" 
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
            </div>

            <!-- Status -->
            <div>
                <label for="status" class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                <select name="status" id="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                    <option value="">All Statuses</option>
                    @foreach(\App\Enums\ReservationStatus::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Source -->
            <div>
                <label for="source" class="block text-xs font-medium text-gray-700 mb-1">Source</label>
                <select name="source" id="source" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                    <option value="">All Sources</option>
                    @foreach(\App\Enums\ReservationSource::cases() as $source)
                        <option value="{{ $source->value }}" {{ request('source') === $source->value ? 'selected' : '' }}>{{ $source->label() }}</option>
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
                @if(request()->anyFilled(['search', 'status', 'source', 'facility_type', 'facility_id', 'date_from', 'date_until']))
                    <a href="{{ route('admin.reservations.index') }}" class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
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
                <x-admin.table-cell :isHeader="true">Code</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Customer</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Facility</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Dates</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Status</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Total</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Actions</x-admin.table-cell>
            </x-admin.table-head>
            <x-admin.table-body>
                @forelse($reservations as $reservation)
                    <x-admin.table-row>
                        <x-admin.table-cell>
                            <a href="{{ route('admin.reservations.show', $reservation) }}" class="font-mono text-sm font-semibold text-primary hover:text-primary-dark">
                                {{ $reservation->reservation_code }}
                            </a>
                            <span class="block text-[10px] text-gray-400 mt-0.5">{{ $reservation->reservation_source->label() }}</span>
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            <span class="font-medium text-gray-900">{{ $reservation->user->name }}</span>
                            <span class="block text-xs text-gray-500">{{ $reservation->user->email }}</span>
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            <span class="text-sm font-medium text-gray-900">{{ $reservation->facility->name }}</span>
                            <span class="block text-xs text-gray-500">{{ $reservation->facility->facilityType->name }}</span>
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            <span class="text-sm text-gray-900">{{ $reservation->check_in->format('M d') }} - {{ $reservation->check_out->format('M d, Y') }}</span>
                            <span class="block text-xs text-gray-500">{{ $reservation->guest_count }} guests</span>
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            <x-badge :status="$reservation->status->value" />
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            <span class="font-semibold text-gray-900">₱{{ number_format($reservation->total_price, 2) }}</span>
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            <a href="{{ route('admin.reservations.show', $reservation) }}" class="text-primary hover:text-primary-dark text-sm font-medium">Manage</a>
                        </x-admin.table-cell>
                    </x-admin.table-row>
                @empty
                    <x-admin.table-row>
                        <x-admin.table-cell colspan="7" class="text-center py-8 text-gray-500">
                            No reservations found matching your criteria.
                        </x-admin.table-cell>
                    </x-admin.table-row>
                @endforelse
            </x-admin.table-body>
        </x-admin.table>
    </div>

    <!-- Mobile Cards -->
    <div class="md:hidden space-y-4">
        @forelse($reservations as $reservation)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-start justify-between mb-2">
                    <div>
                        <a href="{{ route('admin.reservations.show', $reservation) }}" class="font-mono text-sm font-semibold text-primary hover:text-primary-dark">
                            {{ $reservation->reservation_code }}
                        </a>
                        <span class="text-[10px] text-gray-400 ml-2 uppercase">{{ $reservation->reservation_source->label() }}</span>
                    </div>
                    <x-badge :status="$reservation->status->value" />
                </div>
                
                <div class="text-sm text-gray-600 space-y-2 mt-3">
                    <p><span class="font-medium text-gray-900">{{ $reservation->user->name }}</span> ({{ $reservation->user->email }})</p>
                    <p><span class="font-medium">Facility:</span> {{ $reservation->facility->name }}</p>
                    <p><span class="font-medium">Dates:</span> {{ $reservation->check_in->format('M d') }} - {{ $reservation->check_out->format('M d, Y') }} ({{ $reservation->guest_count }} pax)</p>
                    <p><span class="font-medium">Total:</span> <span class="font-semibold text-gray-900">₱{{ number_format($reservation->total_price, 2) }}</span></p>
                </div>
                <div class="mt-4 pt-3 border-t border-gray-100 flex gap-3">
                    <a href="{{ route('admin.reservations.show', $reservation) }}" class="w-full text-center px-4 py-2 border border-primary rounded-md text-sm font-medium text-primary hover:bg-primary hover:text-white transition">
                        Manage
                    </a>
                </div>
            </div>
        @empty
            <p class="text-center text-gray-500 py-8">No reservations found.</p>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $reservations->withQueryString()->links() }}
    </div>
</x-admin-layout>
