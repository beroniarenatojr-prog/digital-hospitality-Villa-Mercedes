<x-admin-layout>
    <x-slot name="header">Customers</x-slot>

    @php
        $hasCustomerFilters = request()->anyFilled(['search', 'has_reservations', 'registered_from', 'registered_until']);
    @endphp

    <x-admin.page-header title="Customers" />

    <x-admin.live-filter :action="route('admin.customers.index')">
        <x-admin.filter-form
            :action="route('admin.customers.index')"
            :active="$hasCustomerFilters"
            label="Search customers"
        >
            <label for="search" class="admin-label">Search customers</label>
            <p class="admin-help mb-2">Matches a name, email address, or phone number.</p>
            <input
                type="search"
                name="search"
                id="search"
                value="{{ request('search') }}"
                placeholder="Maria Santos, maria@example.com, or 0917 555 0100"
                autocomplete="off"
                enterkeyhint="search"
                class="admin-input"
            >

            <x-slot:more>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="has_reservations" class="admin-label">Bookings</label>
                        <select name="has_reservations" id="has_reservations" class="admin-input mt-1">
                            <option value="">Any guest</option>
                            <option value="1" @selected(request('has_reservations') === '1')>Has booked at least once</option>
                            <option value="0" @selected(request('has_reservations') === '0')>Has never booked</option>
                        </select>
                    </div>

                    <div>
                        <label for="registered_from" class="admin-label">Registered from</label>
                        <input type="date" name="registered_from" id="registered_from" value="{{ request('registered_from') }}" class="admin-input mt-1">
                    </div>

                    <div>
                        <label for="registered_until" class="admin-label">Registered until</label>
                        <input type="date" name="registered_until" id="registered_until" value="{{ request('registered_until') }}" class="admin-input mt-1">
                    </div>
                </div>
            </x-slot:more>
        </x-admin.filter-form>

        <x-admin.live-filter-results>
            @include('admin.customers.partials.results')
        </x-admin.live-filter-results>
    </x-admin.live-filter>
</x-admin-layout>
