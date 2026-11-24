<x-admin-layout>
    <x-slot name="header">Facilities</x-slot>

    @php
        $hasFacilityFilters = request()->anyFilled(['search', 'type', 'status', 'active']);
        $hasFacilitySecondaryFilters = request()->anyFilled(['type', 'active']);
    @endphp

    <x-admin.page-header
        title="Facilities"
    >
        <x-slot:actions>
            <x-admin.button :href="route('admin.facilities.create')">Add a facility</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.live-filter :action="route('admin.facilities.index')">
        <x-admin.filter-form
            :action="route('admin.facilities.index')"
            :active="$hasFacilityFilters"
            label="Search facilities"
        >
            <label for="search" class="admin-label">Find a facility</label>
            <p class="admin-help mb-2">Search by name, slug, or type.</p>

            <div class="flex flex-col gap-3 sm:flex-row">
                <input
                    type="search"
                    name="search"
                    id="search"
                    value="{{ request('search') }}"
                    placeholder="Deluxe Room, diamond-hall"
                    autocomplete="off"
                    enterkeyhint="search"
                    class="admin-input sm:flex-1"
                >

                <div class="sm:w-52">
                    <label for="status" class="sr-only">Status</label>
                    <select name="status" id="status" class="admin-input">
                        <option value="">Any status</option>
                        @foreach (\App\Enums\FacilityStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <x-slot:more>
                <section class="group" >
                    <div class="px-4 py-3 text-sm font-semibold text-text">
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-primary" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M3 4a1 1 0 011-1h16a1 1 0 01.8 1.6L14 13.333V19a1 1 0 01-1.447.894l-3-1.5A1 1 0 019 17.5v-4.167L3.2 4.6A1 1 0 013 4z" />
                            </svg>
                            Narrow by type or availability
                        </span>
                        <span
                            class="text-xs font-medium text-text-muted"
                            data-live-filter-hint
                            data-live-filter-hint-fields="type,active"
                            data-live-filter-hint-idle="Not applied"
                            data-live-filter-hint-active="Applied"
                        >{{ $hasFacilitySecondaryFilters ? 'Applied' : 'Not applied' }}</span>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="type" class="admin-label">Space type</label>
                            <select name="type" id="type" class="admin-input mt-1">
                                <option value="">Any type</option>
                                @foreach ($facilityTypes as $type)
                                    <option value="{{ $type->slug }}" @selected(request('type') === $type->slug)>{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="active" class="admin-label">Public listing</label>
                            <select name="active" id="active" class="admin-input mt-1">
                                <option value="">Listed and hidden</option>
                                <option value="1" @selected(request('active') === '1')>Listed on the public site</option>
                                <option value="0" @selected(request('active') === '0')>Hidden from the public site</option>
                            </select>
                        </div>
                    </div>
                </section>
            </x-slot:more>
        </x-admin.filter-form>

        <x-admin.live-filter-results>
            @include('admin.facilities.partials.results')
        </x-admin.live-filter-results>
    </x-admin.live-filter>
</x-admin-layout>
