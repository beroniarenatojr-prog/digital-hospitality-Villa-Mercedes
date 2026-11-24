<x-admin-layout>
    <x-slot name="header">Rates and pricing</x-slot>

    @php
        $hasRateFilters = request()->anyFilled(['search', 'facility_type', 'type', 'active']);
        $hasRateSecondaryFilters = request()->anyFilled(['facility_type', 'active']);
    @endphp

    <x-admin.page-header
        title="Rates and pricing"
    >
        <x-slot:actions>
            <x-admin.button :href="route('admin.rates.create')">Add a rate</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.live-filter :action="route('admin.rates.index')">
        <x-admin.filter-form
            :action="route('admin.rates.index')"
            :active="$hasRateFilters"
            label="Search rates"
        >
            <label for="search" class="admin-label">Find a rate</label>
            <p class="admin-help mb-2">Matches the rate name or the facility it belongs to.</p>

            <div class="flex flex-col gap-3 sm:flex-row">
                <input
                    type="search"
                    name="search"
                    id="search"
                    value="{{ request('search') }}"
                    placeholder="Standard Rate, Deluxe Room"
                    autocomplete="off"
                    enterkeyhint="search"
                    class="admin-input sm:flex-1"
                >

                <div class="sm:w-56">
                    <label for="type" class="sr-only">Rate type</label>
                    <select name="type" id="type" class="admin-input">
                        <option value="">Any rate type</option>
                        @foreach (\App\Enums\RateType::cases() as $rateType)
                            <option value="{{ $rateType->value }}" @selected(request('type') === $rateType->value)>{{ $rateType->label() }}</option>
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
                            Narrow by facility type or status
                        </span>
                        <span
                            class="text-xs font-medium text-text-muted"
                            data-live-filter-hint
                            data-live-filter-hint-fields="facility_type,active"
                            data-live-filter-hint-idle="Not applied"
                            data-live-filter-hint-active="Applied"
                        >{{ $hasRateSecondaryFilters ? 'Applied' : 'Not applied' }}</span>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="facility_type" class="admin-label">Facility type</label>
                            <select name="facility_type" id="facility_type" class="admin-input mt-1">
                                <option value="">Any type</option>
                                @foreach ($facilityTypes as $type)
                                    <option value="{{ $type->slug }}" @selected(request('facility_type') === $type->slug)>{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="active" class="admin-label">Rate status</label>
                            <select name="active" id="active" class="admin-input mt-1">
                                <option value="">Active and inactive</option>
                                <option value="1" @selected(request('active') === '1')>Active only — used in pricing</option>
                                <option value="0" @selected(request('active') === '0')>Inactive only — ignored by pricing</option>
                            </select>
                        </div>
                    </div>
                </section>
            </x-slot:more>
        </x-admin.filter-form>

        <x-admin.live-filter-results>
            @include('admin.rates.partials.results')
        </x-admin.live-filter-results>
    </x-admin.live-filter>
</x-admin-layout>
