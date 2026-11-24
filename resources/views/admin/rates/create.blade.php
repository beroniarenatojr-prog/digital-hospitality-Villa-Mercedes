<x-admin-layout>
    <x-slot name="header">Create Rate</x-slot>

    <x-admin.page-header
        title="Create Rate"
        :breadcrumbs="[
            ['label' => 'Rates', 'url' => route('admin.rates.index')],
            ['label' => 'New rate'],
        ]"
    />

    <form
        method="POST"
        action=""
        x-data="{
            facilityId: @js((string) old('facility_id', $selectedFacilityId ?? '')),
            type: @js((string) old('type', '')),
            hints: @js(\App\Enums\RateType::hints()),
            get hint() { return this.hints[this.type] ?? '' },
        }"
        x-bind:action="'{{ url('admin/facilities') }}/' + facilityId + '/rates'"
        class="max-w-3xl space-y-6"
    >
        @csrf

        <x-admin.panel title="Which space and what kind of rate" description="The facility cannot be changed after saving.">
            <div class="space-y-4">
                <div>
                    <label for="facility_id" class="admin-label">Facility <span class="text-danger" aria-hidden="true">*</span></label>
                    <select id="facility_id" name="facility_id" x-model="facilityId" required aria-required="true"
                            @error('facility_id') aria-invalid="true" @enderror class="admin-input mt-1">
                        <option value="">Select a facility</option>
                        @foreach ($facilities as $facility)
                            <option value="{{ $facility->id }}" @selected((string) old('facility_id', $selectedFacilityId ?? '') === (string) $facility->id)>
                                {{ $facility->name }} ({{ $facility->facilityType->name }})
                            </option>
                        @endforeach
                    </select>
                    <p class="admin-help" x-show="! facilityId">Choose a facility to unlock the save button.</p>
                    <x-input-error class="mt-1" :messages="$errors->get('facility_id')" />
                </div>

                <div>
                    <label for="type" class="admin-label">Rate type <span class="text-danger" aria-hidden="true">*</span></label>
                    <select id="type" name="type" x-model="type" required aria-required="true"
                            @error('type') aria-invalid="true" @enderror class="admin-input mt-1">
                        <option value="">Select a type</option>
                        @foreach (\App\Enums\RateType::cases() as $rateType)
                            <option value="{{ $rateType->value }}" @selected(old('type') === $rateType->value)>{{ $rateType->label() }}</option>
                        @endforeach
                    </select>
                    <p class="admin-help" x-show="hint" x-text="hint"></p>
                    <x-input-error class="mt-1" :messages="$errors->get('type')" />
                </div>

                <div>
                    <label for="name" class="admin-label">Rate name <span class="text-danger" aria-hidden="true">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required aria-required="true" maxlength="255"
                           placeholder="e.g. Standard Rate, Summer Promo, Early Check-in"
                           @error('name') aria-invalid="true" @enderror class="admin-input mt-1">
                    <x-input-error class="mt-1" :messages="$errors->get('name')" />
                </div>
            </div>
        </x-admin.panel>

        <x-admin.panel title="Price" description="Philippine pesos. The unit is what the amount is multiplied by when a guest books.">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="amount" class="admin-label">Amount (₱) <span class="text-danger" aria-hidden="true">*</span></label>
                    <input type="number" id="amount" name="amount" value="{{ old('amount') }}" required aria-required="true" min="0.01" step="0.01"
                           placeholder="0.00"
                           @error('amount') aria-invalid="true" @enderror class="admin-input mt-1">
                    <p class="admin-help">Must be greater than zero.</p>
                    <x-input-error class="mt-1" :messages="$errors->get('amount')" />
                </div>

                <div>
                    <label for="unit" class="admin-label">Charged per <span class="text-danger" aria-hidden="true">*</span></label>
                    <select id="unit" name="unit" required aria-required="true"
                            @error('unit') aria-invalid="true" @enderror class="admin-input mt-1">
                        @foreach (\App\Enums\RateUnit::cases() as $unit)
                            <option value="{{ $unit->value }}" @selected(old('unit', 'per night') === $unit->value)>{{ str_replace('_', ' ', $unit->value) }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('unit')" />
                </div>
            </div>
        </x-admin.panel>

        @include('admin.rates.partials.effective-period-and-pricing', ['rate' => null])

        <div class="flex flex-wrap items-center justify-end gap-2">
            <x-admin.button variant="secondary" :href="route('admin.rates.index')">Cancel</x-admin.button>
            <x-admin.button type="submit" x-bind:disabled="! facilityId">Create Rate</x-admin.button>
        </div>
    </form>
</x-admin-layout>
