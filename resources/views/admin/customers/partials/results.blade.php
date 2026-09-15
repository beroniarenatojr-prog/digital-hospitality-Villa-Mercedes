<div class="hidden md:block">
    <x-admin.table>
        <x-admin.table-head>
            <x-admin.table-cell :isHeader="true">Name</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Email</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Phone</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Registered</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Reservations</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Actions</x-admin.table-cell>
        </x-admin.table-head>
        <x-admin.table-body>
            @forelse($customers as $customer)
                <x-admin.table-row>
                    <x-admin.table-cell>
                        <a href="{{ route('admin.customers.show', $customer->id) }}" class="font-semibold text-primary hover:text-primary-dark">
                            {{ $customer->name }}
                        </a>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <span class="text-sm text-gray-700">{{ $customer->email }}</span>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <span class="text-sm text-gray-700">{{ $customer->phone ?: '—' }}</span>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <span class="text-sm text-gray-700">{{ $customer->created_at->format('M d, Y') }}</span>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <span class="text-sm font-medium text-gray-900">{{ $customer->reservations_count }}</span>
                        @if($customer->reservations_max_check_in)
                            <span class="block text-xs text-gray-400">Latest: {{ \Carbon\Carbon::parse($customer->reservations_max_check_in)->format('M d, Y') }}</span>
                        @endif
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <a href="{{ route('admin.customers.show', $customer->id) }}" class="text-primary hover:text-primary-dark text-sm font-medium">View</a>
                    </x-admin.table-cell>
                </x-admin.table-row>
            @empty
                <x-admin.table-row>
                    <x-admin.table-cell colspan="6" class="text-center py-8 text-gray-500">
                        No customers found matching your criteria.
                    </x-admin.table-cell>
                </x-admin.table-row>
            @endforelse
        </x-admin.table-body>
    </x-admin.table>
</div>

<div class="md:hidden space-y-4">
    @forelse($customers as $customer)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="flex items-start justify-between mb-2">
                <a href="{{ route('admin.customers.show', $customer->id) }}" class="font-semibold text-primary hover:text-primary-dark">
                    {{ $customer->name }}
                </a>
                <span class="text-xs text-gray-400">{{ $customer->reservations_count }} reservations</span>
            </div>
            <div class="text-sm text-gray-600 space-y-1">
                <p>{{ $customer->email }}</p>
                @if($customer->phone)
                    <p>{{ $customer->phone }}</p>
                @endif
                <p class="text-xs text-gray-400">Registered {{ $customer->created_at->format('M d, Y') }}</p>
            </div>
            <div class="mt-3 pt-3 border-t border-gray-100">
                <a href="{{ route('admin.customers.show', $customer->id) }}" class="w-full text-center block px-4 py-2 border border-primary rounded-md text-sm font-medium text-primary hover:bg-primary hover:text-white transition">
                    View Customer
                </a>
            </div>
        </div>
    @empty
        <p class="text-center text-gray-500 py-8">No customers found.</p>
    @endforelse
</div>

<div class="mt-6">
    {{ $customers->withQueryString()->links() }}
</div>
