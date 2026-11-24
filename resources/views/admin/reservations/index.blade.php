<x-admin-layout>
    <x-slot name="header">Reservations</x-slot>

    @php
        $hasReservationFilters = request()->anyFilled(['search', 'status', 'source', 'facility_type', 'facility_id', 'date_from', 'date_until']);
        $hasAdvancedFilters = request()->anyFilled(['status', 'source', 'facility_type', 'facility_id', 'date_from', 'date_until']);
    @endphp

    <x-admin.page-header
        title="Reservations"
    >
        <x-slot name="actions">
            <a href="{{ route('admin.reservations.walk-in') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-white font-semibold hover:bg-primary-hover shadow-sm text-sm">
                + Walk-in Workspace
            </a>
        </x-slot>
    </x-admin.page-header>

    <nav class="mb-4 flex flex-wrap gap-2" aria-label="Reservation views">
        <x-admin.button :variant="request()->boolean('arriving_today') ? 'secondary' : 'primary'" :href="route('admin.reservations.index')">All reservations</x-admin.button>
        <x-admin.button :variant="request()->boolean('arriving_today') ? 'primary' : 'secondary'" :href="route('admin.reservations.index', ['arriving_today' => 1])">Arriving today</x-admin.button>
    </nav>

    <x-admin.live-filter :action="route('admin.reservations.index')">
    <x-admin.filter-form
        :action="route('admin.reservations.index')"
        :active="$hasReservationFilters"
        label="Search reservations"
    >
        @if (request()->boolean('arriving_today'))
            <input type="hidden" name="arriving_today" value="1">
        @endif
        <label for="search" class="admin-label">Search reservations</label>
        <p class="admin-help mb-2">Matches a reservation code, guest name, phone number, or email.</p>
        <input
            type="search"
            name="search"
            id="search"
            value="{{ request('search') }}"
            placeholder="VM-20260916-ABC123, Juan Dela Cruz, or 0917 555 0100"
            autocomplete="off"
            enterkeyhint="search"
            class="admin-input"
        >

        <x-slot:more>
            <section class="group" >
                <div class="px-4 py-3 text-sm font-semibold text-text">
                    <span class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-primary" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M3 4a1 1 0 011-1h16a1 1 0 01.8 1.6L14 13.333V19a1 1 0 01-1.447.894l-3-1.5A1 1 0 019 17.5v-4.167L3.2 4.6A1 1 0 013 4z" />
                        </svg>
                        Narrow by status, space, or dates
                    </span>
                    <span
                        class="text-xs font-medium text-text-muted"
                        data-live-filter-hint
                        data-live-filter-hint-fields="status,source,facility_type,facility_id,date_from,date_until"
                        data-live-filter-hint-idle="Not applied"
                        data-live-filter-hint-active="Applied"
                    >{{ $hasAdvancedFilters ? 'Applied' : 'Not applied' }}</span>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label for="status" class="admin-label">Status</label>
                        <select name="status" id="status" class="admin-input mt-1">
                            <option value="">Any status</option>
                            @foreach (\App\Enums\ReservationStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="source" class="admin-label">Booked by</label>
                        <select name="source" id="source" class="admin-input mt-1">
                            <option value="">Any source</option>
                            @foreach (\App\Enums\ReservationSource::cases() as $source)
                                <option value="{{ $source->value }}" @selected(request('source') === $source->value)>{{ $source->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="facility_type" class="admin-label">Space type</label>
                        <select name="facility_type" id="facility_type" class="admin-input mt-1">
                            <option value="">Any type</option>
                            @foreach ($facilityTypes as $type)
                                <option value="{{ $type->slug }}" @selected(request('facility_type') === $type->slug)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="facility_id" class="admin-label">Space</label>
                        <select name="facility_id" id="facility_id" class="admin-input mt-1">
                            <option value="">Any space</option>
                            @foreach ($filterFacilities as $facility)
                                <option value="{{ $facility->id }}" @selected((string) request('facility_id') === (string) $facility->id)>{{ $facility->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="date_from" class="admin-label">Stay overlaps from</label>
                        <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}" class="admin-input mt-1">
                    </div>

                    <div>
                        <label for="date_until" class="admin-label">Stay overlaps until</label>
                        <input type="date" name="date_until" id="date_until" value="{{ request('date_until') }}" class="admin-input mt-1">
                    </div>
                </div>
            </section>
        </x-slot:more>
    </x-admin.filter-form>

    <x-admin.live-filter-results>
        @include('admin.reservations.partials.results')
    </x-admin.live-filter-results>
    </x-admin.live-filter>
</x-admin-layout>
