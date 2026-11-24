<x-admin-layout>
    <x-slot name="header">Edit {{ $rate->name }}</x-slot>

    <x-admin.page-header
        title="Edit {{ $rate->name }}"
        :breadcrumbs="[
            ['label' => 'Rates', 'url' => route('admin.rates.index')],
            ['label' => $rate->name, 'url' => route('admin.rates.show', $rate)],
            ['label' => 'Edit'],
        ]"
    >
        <x-slot:actions>
            <x-admin.button variant="secondary" :href="route('admin.rates.show', $rate)">Back to rate</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <form
        method="POST"
        action="{{ route('admin.rates.update', $rate) }}"
        x-data="{
            type: @js((string) old('type', $rate->type->value)),
            hints: @js(\App\Enums\RateType::hints()),
            get hint() { return this.hints[this.type] ?? '' },
        }"
        class="max-w-3xl space-y-6"
    >
        @csrf
        @method('PUT')

        <x-admin.panel title="Which space and what kind of rate" description="A rate cannot be moved to another facility. Create a new rate for a different space instead.">
            <div class="space-y-4">
                <div>
                    <p class="admin-label">Facility</p>
                    <p class="mt-1 text-sm text-text">
                        <a href="{{ route('admin.facilities.show', $rate->facility) }}" class="rounded font-medium text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                            {{ $rate->facility->name }}
                        </a>
                        <span class="text-text-muted">({{ $rate->facility->facilityType->name }})</span>
                    </p>
                </div>

                <div>
                    <label for="type" class="admin-label">Rate type <span class="text-danger" aria-hidden="true">*</span></label>
                    <select id="type" name="type" x-model="type" required aria-required="true"
                            @error('type') aria-invalid="true" @enderror class="admin-input mt-1">
                        @foreach (\App\Enums\RateType::cases() as $rateType)
                            <option value="{{ $rateType->value }}" @selected(old('type', $rate->type->value) === $rateType->value)>{{ $rateType->label() }}</option>
                        @endforeach
                    </select>
                    <p class="admin-help" x-show="hint" x-text="hint"></p>
                    <x-input-error class="mt-1" :messages="$errors->get('type')" />
                </div>

                <div>
                    <label for="name" class="admin-label">Rate name <span class="text-danger" aria-hidden="true">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $rate->name) }}" required aria-required="true" maxlength="255"
                           @error('name') aria-invalid="true" @enderror class="admin-input mt-1">
                    <x-input-error class="mt-1" :messages="$errors->get('name')" />
                </div>
            </div>
        </x-admin.panel>

        <x-admin.panel title="Price" description="Philippine pesos. The unit is what the amount is multiplied by when a guest books.">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="amount" class="admin-label">Amount (₱) <span class="text-danger" aria-hidden="true">*</span></label>
                    <input type="number" id="amount" name="amount" value="{{ old('amount', $rate->amount) }}" required aria-required="true" min="0.01" step="0.01"
                           @error('amount') aria-invalid="true" @enderror class="admin-input mt-1">
                    <p class="admin-help">Must be greater than zero.</p>
                    <x-input-error class="mt-1" :messages="$errors->get('amount')" />
                </div>

                <div>
                    <label for="unit" class="admin-label">Charged per <span class="text-danger" aria-hidden="true">*</span></label>
                    <select id="unit" name="unit" required aria-required="true"
                            @error('unit') aria-invalid="true" @enderror class="admin-input mt-1">
                        @foreach (\App\Enums\RateUnit::cases() as $unit)
                            <option value="{{ $unit->value }}" @selected(old('unit', $rate->unit) === $unit->value)>{{ str_replace('_', ' ', $unit->value) }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('unit')" />
                </div>
            </div>
        </x-admin.panel>

        @include('admin.rates.partials.effective-period-and-pricing', ['rate' => $rate])

        <div class="flex flex-wrap items-center justify-end gap-2">
            <x-admin.button variant="secondary" :href="route('admin.rates.show', $rate)">Cancel</x-admin.button>
            <x-admin.button type="submit">Save changes</x-admin.button>
        </div>
    </form>
</x-admin-layout>
