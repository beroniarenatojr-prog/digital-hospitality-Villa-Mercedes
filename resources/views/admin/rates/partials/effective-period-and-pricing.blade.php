<x-admin.panel
    title="Effective period"
    description="Only seasonal rates use a date range. Leave the dates blank for base rates, extra-person charges, and additional charges."
    x-show="type === '{{ \App\Enums\RateType::Seasonal->value }}'"
    x-cloak
>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="effective_from" class="admin-label">Effective from</label>
            <input type="date" id="effective_from" name="effective_from" value="{{ old('effective_from', $rate?->effective_from?->format('Y-m-d')) }}"
                   @error('effective_from') aria-invalid="true" @enderror class="admin-input mt-1">
            <x-input-error class="mt-1" :messages="$errors->get('effective_from')" />
        </div>

        <div>
            <label for="effective_until" class="admin-label">Effective until</label>
            <input type="date" id="effective_until" name="effective_until" value="{{ old('effective_until', $rate?->effective_until?->format('Y-m-d')) }}"
                   @error('effective_until') aria-invalid="true" @enderror class="admin-input mt-1">
            <x-input-error class="mt-1" :messages="$errors->get('effective_until')" />
        </div>
    </div>

    <div class="mt-4">
        <x-admin.notice variant="warning" title="Overlapping seasonal rates are rejected" :dismissible="false">
            If two seasonal rates cover the same dates for this facility, the pricing engine refuses the configuration and bookings for those dates will fail.
        </x-admin.notice>
    </div>
</x-admin.panel>

<x-admin.panel title="Pricing" description="Inactive rates stay on file and are ignored when a booking is priced.">
    <div class="flex items-start gap-3">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $rate?->is_active ?? true))
               class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-primary">
        <div>
            <label for="is_active" class="admin-label">Use this rate in pricing calculations</label>
            <p class="admin-help">Existing reservations keep the price they were booked at either way.</p>
        </div>
    </div>
</x-admin.panel>
