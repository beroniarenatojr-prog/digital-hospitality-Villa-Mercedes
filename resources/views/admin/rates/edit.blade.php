<x-admin-layout>
    <x-slot name="header">
        Edit Rate — {{ $rate->name }}
    </x-slot>

    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm text-gray-500">
        <a href="{{ route('admin.rates.index') }}" class="hover:text-primary">Rates</a>
        <span class="mx-2">/</span>
        <a href="{{ route('admin.rates.show', $rate) }}" class="hover:text-primary">{{ $rate->name }}</a>
        <span class="mx-2">/</span>
        <span class="text-gray-900">Edit</span>
    </nav>

    <div class="max-w-2xl">
        <div class="bg-white shadow-sm rounded-lg border border-gray-200">
            <form method="POST" action="{{ route('admin.rates.update', $rate) }}" class="p-6 space-y-6"
                  x-data="{
                      selectedType: '{{ old('type', $rate->type->value) }}',
                      get showDates() { return this.selectedType === 'seasonal'; },
                      get showTypeHint() {
                          const hints = {
                              'base': 'The standard rate applied when no seasonal rate matches.',
                              'seasonal': 'A rate applied during a specific date range. Requires effective dates.',
                              'additional_charge': 'An extra charge (e.g. early check-in, late check-out). Applied per hour or event.',
                              'extra_person': 'Charged when guest count exceeds the facility\'s standard capacity but remains within maximum capacity.'
                          };
                          return hints[this.selectedType] || '';
                      }
                  }">
                @csrf
                @method('PUT')

                <!-- Facility (read-only) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Facility</label>
                    <p class="mt-1 text-sm text-gray-900">{{ $rate->facility->name }} ({{ $rate->facility->facilityType->name }})</p>
                </div>

                <!-- Rate Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Rate Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $rate->name) }}" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                    @error('name')
                        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Rate Type -->
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700">Rate Type <span class="text-danger">*</span></label>
                    <select id="type" name="type" x-model="selectedType" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                        @foreach(\App\Enums\RateType::cases() as $rateType)
                            <option value="{{ $rateType->value }}" {{ old('type', $rate->type->value) === $rateType->value ? 'selected' : '' }}>{{ $rateType->label() }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500" x-show="showTypeHint" x-text="showTypeHint" x-transition></p>
                    @error('type')
                        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Amount & Unit -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700">Amount (₱) <span class="text-danger">*</span></label>
                        <input type="number" id="amount" name="amount" value="{{ old('amount', $rate->amount) }}" required min="0.01" step="0.01"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                        <p class="mt-1 text-xs text-gray-500">Philippine Peso. Must be greater than zero.</p>
                        @error('amount')
                            <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="unit" class="block text-sm font-medium text-gray-700">Unit <span class="text-danger">*</span></label>
                        <select id="unit" name="unit" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                            @foreach (\App\Enums\RateUnit::cases() as $unit)
                                <option value="{{ $unit->value }}" @selected(old('unit', $rate->unit) === $unit->value)>{{ $unit->value }}</option>
                            @endforeach
                        </select>
                        @error('unit')
                            <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Effective Dates -->
                <div x-show="showDates" x-transition class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="effective_from" class="block text-sm font-medium text-gray-700">Effective From</label>
                        <input type="date" id="effective_from" name="effective_from" value="{{ old('effective_from', $rate->effective_from?->format('Y-m-d')) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                        @error('effective_from')
                            <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="effective_until" class="block text-sm font-medium text-gray-700">Effective Until</label>
                        <input type="date" id="effective_until" name="effective_until" value="{{ old('effective_until', $rate->effective_until?->format('Y-m-d')) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                        @error('effective_until')
                            <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Active State -->
                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $rate->is_active) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring-primary">
                    <label for="is_active" class="text-sm font-medium text-gray-700">Active (used in pricing calculations)</label>
                </div>

                <!-- Submit -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                    <a href="{{ route('admin.rates.show', $rate) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-dark transition">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
