<x-admin-layout>
    <x-slot name="header">
        Customer Management
    </x-slot>

    @php
        $hasCustomerFilters = request()->anyFilled(['search', 'has_reservations', 'registered_from', 'registered_until']);
    @endphp
    <div data-live-filter data-live-filter-url="{{ route('admin.customers.index') }}">
    <!-- Filters -->
    <div class="mb-6 bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <form method="GET" action="{{ route('admin.customers.index') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

            <!-- Search -->
            <div>
                <label for="search" class="block text-xs font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Name, Email, Phone"
                       autocomplete="off"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
            </div>

            <!-- Has Reservations -->
            <div>
                <label for="has_reservations" class="block text-xs font-medium text-gray-700 mb-1">Reservations</label>
                <select name="has_reservations" id="has_reservations" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                    <option value="">All Customers</option>
                    <option value="1" {{ request('has_reservations') === '1' ? 'selected' : '' }}>With Reservations</option>
                    <option value="0" {{ request('has_reservations') === '0' ? 'selected' : '' }}>Without Reservations</option>
                </select>
            </div>

            <!-- Registration From -->
            <div>
                <label for="registered_from" class="block text-xs font-medium text-gray-700 mb-1">Registered From</label>
                <input type="date" name="registered_from" id="registered_from" value="{{ request('registered_from') }}"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
            </div>

            <!-- Registration Until -->
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                <div class="min-w-0 flex-1">
                    <label for="registered_until" class="block text-xs font-medium text-gray-700 mb-1">Registered Until</label>
                    <input type="date" name="registered_until" id="registered_until" value="{{ request('registered_until') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                </div>
                <x-admin.live-filter-reset :visible="$hasCustomerFilters" />
            </div>
        </form>
    </div>

    <x-admin.live-filter-results>
        @include('admin.customers.partials.results')
    </x-admin.live-filter-results>
    </div>
</x-admin-layout>
