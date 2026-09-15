@if(request()->filled('search'))
    <p class="mb-4 text-sm text-gray-600">
        @if($reservations->isEmpty())
            No reservation matched. Check the code or try another guest detail.
        @elseif($reservations->total() > 1)
            {{ $reservations->total() }} matching reservations. Choose one to manage.
        @else
            1 matching reservation.
        @endif
    </p>
@endif

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
                        <span class="font-medium text-gray-900">{{ $reservation->guestName() }}</span>
                        <span class="block text-xs text-gray-500">{{ $reservation->guestEmail() ?? $reservation->guestPhone() ?? 'No contact on file' }}</span>
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
                <p><span class="font-medium text-gray-900">{{ $reservation->guestName() }}</span> ({{ $reservation->guestEmail() ?? $reservation->guestPhone() ?? 'no contact' }})</p>
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

<div class="mt-6">
    {{ $reservations->withQueryString()->links() }}
</div>
