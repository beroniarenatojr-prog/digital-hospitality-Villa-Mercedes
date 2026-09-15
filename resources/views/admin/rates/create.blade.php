<x-admin-layout>
    <x-slot name="header">
        Create Rate
    </x-slot>

    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm text-gray-500">
        <a href="{{ route('admin.rates.index') }}" class="hover:text-primary">Rates</a>
        <span class="mx-2">/</span>
        <span class="text-gray-900">Create</span>
    </nav>

    <div class="max-w-2xl">
        <div class="bg-white shadow-sm rounded-lg border border-gray-200">
            <form method="POST" action="" id="rateForm" class="p-6 space-y-6"
                  x-data="{
                      selectedFacilityId: '{{ old('facility_id', $selectedFacilityId ?? '') }}',
                      selectedType: '{{ old('type', '') }}',
                      get showDates() { return this.selectedType === 'seasonal'; },
                      get showTypeHint() {
                          const hints = {
                              'base': 'The standard rate applied when no seasonal rate matches.',
                              'seasonal': 'A rate applied during a specific date range. Requires effective dates.',
                              'additional_charge': 'An extra charge (e.g. early check-in, late check-out). Applied per hour or event.',
                              'extra_person': 'Charged when guest count exceeds the facility\'s standard capacity but remains within maximum capacity.'
                          };
                          return hints[this.selectedType] || '';
                      },
                      submitForm() {
                          if (!this.selectedFacilityId) { alert('Please select a facility.'); return; }
                          const form = document.getElementById('rateForm');
                          form.action = '{{ url('admin/facilities') }}/' + this.selectedFacilityId + '/rates';
                          form.submit();
                      }
                  }">
                @csrf

                <!-- Facility -->
                <div>
                    <label for="facility_id" class="block text-sm font-medium text-gray-700">Facility <span class="text-danger">*</span></label>
                    <select id="facility_id" x-model="selectedFacilityId" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                        <option value="">Select a facility</option>
                        @foreach($facilities as $facility)
                            <option value="{{ $facility->id }}">{{ $facility->name }} ({{ $facility->facilityType->name }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- Rate Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Rate Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                           placeholder="e.g. Standard Rate, Summer Promo, Early Check-in">
                    @error('name')
                        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Rate Type -->
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700">Rate Type <span class="text-danger">*</span></label>
                    <select id="type" name="type" x-model="selectedType" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                        <option value="">Select a type</option>
                        @foreach(\App\Enums\RateType::cases() as $rateType)
                            <option value="{{ $rateType->value }}">{{ $rateType->label() }}</option>
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
                        <input type="number" id="amount" name="amount" value="{{ old('amount') }}" required min="0.01" step="0.01"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                               placeholder="0.00">
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
                                <option value="{{ $unit->value }}" @selected(old('unit', 'per night') === $unit->value)>{{ $unit->value }}</option>
                            @endforeach
                        </select>
                        @error('unit')
                            <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Effective Dates (shown for seasonal) -->
                <div x-show="showDates" x-transition class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="effective_from" class="block text-sm font-medium text-gray-700">Effective From</label>
                        <input type="date" id="effective_from" name="effective_from" value="{{ old('effective_from') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                        @error('effective_from')
                            <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="effective_until" class="block text-sm font-medium text-gray-700">Effective Until</label>
                        <input type="date" id="effective_until" name="effective_until" value="{{ old('effective_until') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                        @error('effective_until')
                            <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div x-show="showDates" x-transition>
                    <x-alert type="info">
                        Seasonal rates apply during the specified date range. If multiple seasonal rates overlap for the same facility, the pricing engine will reject the configuration.
                    </x-alert>
                </div>

                <!-- Active State -->
                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring-primary">
                    <label for="is_active" class="text-sm font-medium text-gray-700">Active (used in pricing calculations)</label>
                </div>

                <!-- Submit -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                    <a href="{{ route('admin.rates.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="button" @click="submitForm()" class="inline-flex items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-dark transition">
                        Create Rate
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
