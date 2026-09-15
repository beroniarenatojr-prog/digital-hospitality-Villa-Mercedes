<x-admin-layout>
    <x-slot name="header">
        Reservation Management
    </x-slot>

    @php
        $walkInFacilities = $facilities->map(fn ($facility) => [
            'id' => $facility->id,
            'name' => $facility->name,
            'type' => $facility->facilityType?->slug,
            'capacity' => $facility->capacity !== null ? (int) $facility->capacity : null,
            'max_capacity' => $facility->effectiveMaxCapacity(),
            'has_capacity_limit' => $facility->effectiveMaxCapacity() !== null,
        ])->values();
        $walkInFacilitySections = collect([
            ['slug' => 'room', 'label' => 'Room', 'placeholder' => 'Select a room'],
            ['slug' => 'function_hall', 'label' => 'Function Hall', 'placeholder' => 'Select a function hall'],
            ['slug' => 'resort_package', 'label' => 'Package', 'placeholder' => 'Select a package'],
            ['slug' => 'cottage', 'label' => 'Cottage', 'placeholder' => 'Select a cottage'],
        ])->map(function (array $section) use ($facilities) {
            $section['facilities'] = $facilities
                ->filter(fn ($facility) => ($facility->facilityType?->slug ?? null) === $section['slug'])
                ->sortBy('name', SORT_NATURAL)
                ->values();

            return $section;
        })->filter(fn (array $section) => $section['facilities']->isNotEmpty())->values();
        $walkInDefaultFacilityId = old('facility_id');
        $walkInOtherNames = array_values((array) old('other_guest_names', []));
    @endphp

    <div x-data="walkInReservation({
        open: {{ (request()->boolean('walk_in') || $errors->any()) ? 'true' : 'false' }},
        facilities: {{ \Illuminate\Support\Js::from($walkInFacilities) }},
        facilityId: {{ \Illuminate\Support\Js::from($walkInDefaultFacilityId) }},
        guestCount: {{ (int) old('guestCount', 1) }},
        otherGuestNames: {{ \Illuminate\Support\Js::from($walkInOtherNames) }}
    })">
    <!-- Walk-in Modal -->
    <div x-show="walkIn" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="admin-dialog-frame">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="walkIn = false"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-3xl w-full p-6 z-50 max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-medium text-gray-900 mb-1">Guest Information Sheet</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Same fields as the front-desk form. Walk-ins are approved immediately. Guest details are stored on the reservation, not on your staff account.
                </p>

                <form method="POST" action="{{ route('admin.reservations.store') }}" class="space-y-5">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="walkin_guest_name" class="block text-sm font-medium text-gray-700">Name <span class="text-danger">*</span></label>
                            <input type="text" name="guest_name" id="walkin_guest_name" value="{{ old('guest_name') }}" required maxlength="255"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                            <x-input-error class="mt-1" :messages="$errors->get('guest_name')" />
                        </div>
                        <div>
                            <label for="walkin_guest_gender" class="block text-sm font-medium text-gray-700">Gender <span class="text-danger">*</span></label>
                            <select name="guest_gender" id="walkin_guest_gender" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                                <option value="">Select</option>
                                @foreach(\App\Enums\GuestGender::cases() as $gender)
                                    <option value="{{ $gender->value }}" @selected(old('guest_gender') === $gender->value)>{{ $gender->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-1" :messages="$errors->get('guest_gender')" />
                        </div>
                        <div>
                            <label for="walkin_guest_origin" class="block text-sm font-medium text-gray-700">Address <span class="text-danger">*</span></label>
                            <input type="text" name="guest_place_of_origin" id="walkin_guest_origin" value="{{ old('guest_place_of_origin') }}" required maxlength="255" placeholder="Street, barangay, city, province"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                            <x-input-error class="mt-1" :messages="$errors->get('guest_place_of_origin')" />
                        </div>
                        <div>
                            <label for="walkin_guest_nationality" class="block text-sm font-medium text-gray-700">Nationality <span class="text-danger">*</span></label>
                            <input type="text" name="guest_nationality" id="walkin_guest_nationality" value="{{ old('guest_nationality') }}" required maxlength="100"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm" placeholder="e.g. Filipino">
                            <x-input-error class="mt-1" :messages="$errors->get('guest_nationality')" />
                        </div>
                        <div>
                            <label for="walkin_guest_company" class="block text-sm font-medium text-gray-700">Company Name/Agency <span class="text-gray-400 font-normal">(optional)</span></label>
                            <input type="text" name="guest_company" id="walkin_guest_company" value="{{ old('guest_company') }}" maxlength="255"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                            <x-input-error class="mt-1" :messages="$errors->get('guest_company')" />
                        </div>
                        <div>
                            <label for="walkin_guest_valid_id" class="block text-sm font-medium text-gray-700">Valid ID type <span class="text-danger">*</span></label>
                            <select name="guest_valid_id" id="walkin_guest_valid_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                                <option value="">Select</option>
                                @foreach(\App\Enums\GuestIdType::cases() as $idType)
                                    <option value="{{ $idType->value }}" @selected(old('guest_valid_id') === $idType->value)>{{ $idType->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-1" :messages="$errors->get('guest_valid_id')" />
                        </div>
                        <div>
                            <label for="walkin_guest_phone" class="block text-sm font-medium text-gray-700">Contact # <span class="text-danger">*</span></label>
                            <input type="text" name="guest_phone" id="walkin_guest_phone" value="{{ old('guest_phone') }}" required maxlength="32" placeholder="0917 123 4567"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                            <x-input-error class="mt-1" :messages="$errors->get('guest_phone')" />
                        </div>
                        <div>
                            <label for="walkin_guest_email" class="block text-sm font-medium text-gray-700">Email Add</label>
                            <input type="email" name="guest_email" id="walkin_guest_email" value="{{ old('guest_email') }}" maxlength="255"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                            <x-input-error class="mt-1" :messages="$errors->get('guest_email')" />
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-gray-700">Facility <span class="text-danger">*</span></p>
                            <p class="text-xs text-gray-500">Choose one option below</p>
                        </div>
                        <input type="hidden" name="facility_id" :value="facilityId">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach($walkInFacilitySections as $section)
                                <div>
                                    <label for="walkin_facility_{{ $section['slug'] }}" class="block text-sm font-medium text-gray-700">{{ $section['label'] }}</label>
                                    <select
                                        id="walkin_facility_{{ $section['slug'] }}"
                                        x-model="selections.{{ $section['slug'] }}"
                                        @change="selectFacility('{{ $section['slug'] }}', $event.target.value)"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                                        :class="facilityId && selections.{{ $section['slug'] }} === String(facilityId) ? 'border-primary ring-1 ring-primary' : ''"
                                    >
                                        <option value="">{{ $section['placeholder'] }}</option>
                                        @foreach($section['facilities'] as $facility)
                                            <option value="{{ $facility->id }}">{{ $facility->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-500" x-show="selectedFacility && hasCapacityLimit">
                            Included <span x-text="includedCapacity"></span> pax · maximum <span x-text="maxCapacity"></span> pax
                        </p>
                        <p class="text-xs text-gray-500" x-show="selectedFacility && !hasCapacityLimit">
                            No fixed guest-capacity limit. Guest count is for staff preparation only.
                        </p>
                        <x-input-error class="mt-1" :messages="$errors->get('facility_id')" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label for="walkin_check_in" class="block text-sm font-medium text-gray-700">Check-in <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="check_in" id="walkin_check_in" value="{{ old('check_in') }}" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                            <x-input-error class="mt-1" :messages="$errors->get('check_in')" />
                        </div>
                        <div>
                            <label for="walkin_check_out" class="block text-sm font-medium text-gray-700">Check-out <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="check_out" id="walkin_check_out" value="{{ old('check_out') }}" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                            <x-input-error class="mt-1" :messages="$errors->get('check_out')" />
                        </div>
                        <div>
                            <label for="walkin_guest_count" class="block text-sm font-medium text-gray-700">Guests <span class="text-danger">*</span></label>
                            <input type="number" name="guestCount" id="walkin_guest_count" min="1" :max="hasCapacityLimit ? maxCapacity : null" x-model.number="guestCount" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                            <x-input-error class="mt-1" :messages="$errors->get('guestCount')" />
                        </div>
                    </div>

                    <div>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="block text-sm font-medium text-gray-700">Other Guest Names</p>
                                <p class="text-xs text-gray-500 mt-1" x-show="hasCapacityLimit">
                                    Add companions up to this space's maximum of <span x-text="maxCapacity"></span> pax, including the primary guest.
                                </p>
                                <p class="text-xs text-gray-500 mt-1" x-show="!hasCapacityLimit">
                                    Optional companion names for staff preparation. No fixed capacity limit.
                                </p>
                            </div>
                            <button type="button" @click="addOtherGuest()" x-show="canAddOtherGuest"
                                    class="shrink-0 inline-flex items-center px-3 py-1.5 rounded-md border border-gray-300 text-xs font-semibold text-gray-700 bg-white hover:bg-gray-50">
                                Add guest name
                            </button>
                        </div>
                        <div class="mt-3 space-y-2" x-show="otherGuestNames.length > 0">
                            <template x-for="(name, index) in otherGuestNames" :key="index">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-gray-500 w-5" x-text="(index + 1) + '.'"></span>
                                    <input type="text" name="other_guest_names[]" x-model="otherGuestNames[index]" maxlength="255"
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                                    <button type="button" @click="removeOtherGuest(index)" class="text-xs font-semibold text-gray-500 hover:text-danger shrink-0">
                                        Remove
                                    </button>
                                </div>
                            </template>
                        </div>
                        <p class="mt-2 text-xs text-gray-400" x-show="hasCapacityLimit && !canAddOtherGuest && maxCompanions === 0">This space has no extra guest-name slots.</p>
                        <p class="mt-2 text-xs text-gray-400" x-show="hasCapacityLimit && !canAddOtherGuest && maxCompanions > 0 && otherGuestNames.length >= maxCompanions">Maximum other guest names for this space reached.</p>
                        <x-input-error class="mt-1" :messages="$errors->get('other_guest_names')" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="walkin_early" class="block text-sm font-medium text-gray-700">Early check-in (hours)</label>
                            <input type="number" name="early_check_in_hours" id="walkin_early" min="0" max="{{ config('resort.stay.max_additional_hours', 12) }}" value="{{ old('early_check_in_hours', 0) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                        </div>
                        <div>
                            <label for="walkin_late" class="block text-sm font-medium text-gray-700">Late check-out (hours)</label>
                            <input type="number" name="late_check_out_hours" id="walkin_late" min="0" max="{{ config('resort.stay.max_additional_hours', 12) }}" value="{{ old('late_check_out_hours', 0) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                        </div>
                    </div>

                    <div>
                        <label for="walkin_notes" class="block text-sm font-medium text-gray-700">Internal notes</label>
                        <textarea name="admin_notes" id="walkin_notes" rows="2"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">{{ old('admin_notes') }}</textarea>
                    </div>

                    <p class="text-xs text-gray-500">This information form is for the hotel's record — filing and security purpose. Thank you!</p>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="walkIn = false" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-primary text-white rounded-md text-sm font-semibold hover:bg-primary-dark">Create Walk-in</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </div>

    <div data-live-filter data-live-filter-url="{{ route('admin.reservations.index') }}">
    @php
        $hasReservationFilters = request()->anyFilled(['search', 'status', 'source', 'facility_type', 'facility_id', 'date_from', 'date_until']);
    @endphp
    <form method="GET" action="{{ route('admin.reservations.index') }}" class="mb-6 bg-white p-4 sm:p-5 rounded-lg shadow-sm border border-gray-200">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1">
                <label for="search" class="block text-sm font-semibold text-gray-900">Search reservations</label>
                <p class="mt-1 text-sm text-gray-500">Matches reservation code, guest name, or email.</p>
                <input type="text" name="search" id="search" value="{{ request('search') }}"
                       placeholder="Code, name, or email"
                       autocomplete="off"
                       class="mt-3 w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-base py-2.5">
            </div>
            <x-admin.live-filter-reset :visible="$hasReservationFilters" class="sm:mb-0.5" />
        </div>

        <details class="mt-4 border-t border-gray-100 pt-3" @if(request()->anyFilled(['status', 'source', 'facility_type', 'facility_id', 'date_from', 'date_until'])) open @endif>
            <summary class="cursor-pointer list-none flex items-center justify-between gap-4 text-sm font-semibold text-gray-900 hover:text-primary focus:outline-none">
                <span class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 01.8 1.6L14 13.333V19a1 1 0 01-1.447.894l-3-1.5A1 1 0 019 17.5v-4.167L3.2 4.6A1 1 0 013 4z" /></svg>
                    More filters
                </span>
                <span class="text-xs font-medium text-gray-500" data-live-filter-hint data-live-filter-hint-fields="status,source,facility_type,facility_id,date_from,date_until" data-live-filter-hint-idle="Status, facility, or date" data-live-filter-hint-active="Filters applied">{{ request()->anyFilled(['status', 'source', 'facility_type', 'facility_id', 'date_from', 'date_until']) ? 'Filters applied' : 'Status, facility, or date' }}</span>
            </summary>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="status" class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" id="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                        <option value="">All Statuses</option>
                        @foreach(\App\Enums\ReservationStatus::cases() as $status)
                            <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="source" class="block text-xs font-medium text-gray-700 mb-1">Source</label>
                    <select name="source" id="source" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                        <option value="">All Sources</option>
                        @foreach(\App\Enums\ReservationSource::cases() as $source)
                            <option value="{{ $source->value }}" {{ request('source') === $source->value ? 'selected' : '' }}>{{ $source->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="facility_type" class="block text-xs font-medium text-gray-700 mb-1">Facility Type</label>
                    <select name="facility_type" id="facility_type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                        <option value="">All Types</option>
                        @foreach($facilityTypes as $type)
                            <option value="{{ $type->slug }}" {{ request('facility_type') === $type->slug ? 'selected' : '' }}>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="facility_id" class="block text-xs font-medium text-gray-700 mb-1">Facility</label>
                    <select name="facility_id" id="facility_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                        <option value="">All Facilities</option>
                        @foreach($filterFacilities as $facility)
                            <option value="{{ $facility->id }}" {{ request('facility_id') == $facility->id ? 'selected' : '' }}>{{ $facility->name }}</option>
                        @endforeach
                    </select>
                </div>
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
            </div>
        </details>
    </form>

    <div class="mb-6 bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-4 sm:px-5 py-3 border-b border-gray-200 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Arriving today</h2>
                <p class="text-xs text-gray-500">Approved or pending stays with check-in today, not yet checked in.</p>
            </div>
            <span class="inline-flex items-center justify-center min-w-[1.75rem] h-7 px-2 rounded-full bg-primary-light text-primary text-xs font-semibold">{{ $arrivalsToday->count() }}</span>
        </div>
        @if($arrivalsToday->isEmpty())
            <p class="px-4 sm:px-5 py-6 text-sm text-gray-500">No arrivals scheduled for today.</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($arrivalsToday as $arrival)
                    <li>
                        <a href="{{ route('admin.reservations.show', $arrival) }}" class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4 px-4 sm:px-5 py-3 hover:bg-gray-50 transition">
                            <span class="font-mono text-sm font-semibold text-primary">{{ $arrival->reservation_code }}</span>
                            <span class="flex-1 text-sm font-medium text-gray-900">{{ $arrival->guestName() }}</span>
                            <span class="text-sm text-gray-500">{{ $arrival->facility->name }} · {{ $arrival->check_in->format('g:i A') }}</span>
                            <x-badge :status="$arrival->status->value" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-admin.live-filter-results>
        @include('admin.reservations.partials.results')
    </x-admin.live-filter-results>
    </div>
</x-admin-layout>
