<x-customer-layout>
    <section class="mx-auto max-w-3xl px-4 py-12 sm:px-6" x-data="serviceAvailability()">
        <a href="{{ route('facilities.show', $facility) }}" class="text-sm font-semibold text-primary hover:underline">← {{ $facility->name }}</a>
        <h1 class="mt-5 font-heading text-4xl font-semibold text-text">Check availability</h1>
        <p class="mt-3 text-text-muted">Choose your visit for {{ $facility->name }}. You can check dates and prices before signing in.</p>
        <form @submit.prevent="check" class="mt-8 rounded-2xl border border-border bg-surface p-6">
            <div @schedule-changed="arrival = $event.detail.check_in; departure = $event.detail.check_out; earlyHours = $event.detail.early_check_in_hours; available = false; resultLabel = ''; error = ''">
                <x-reservation-schedule :facility="$facility" :initial="$prefill" prefix="public-schedule" error-expression="error" />
            </div>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div><x-input-label for="public-guests" value="Party size" /><x-text-input id="public-guests" type="number" x-model.number="guests" @input="available = false; resultLabel = ''" min="1" :max="$facility->effectiveMaxCapacity()" required class="mt-2 w-full" /></div>
                @if(count($facility->paxTiers()) > 1)
                    <div><x-input-label for="public-tier" value="Package guest tier" /><select id="public-tier" x-model.number="paxTier" @change="available = false; resultLabel = ''" class="mt-2 block w-full rounded-lg border-border bg-surface text-text">
                        @foreach($facility->paxTiers() as $tier)<option value="{{ $tier }}">{{ $tier }} guests</option>@endforeach
                    </select></div>
                @endif
            </div>
            <button type="submit" :disabled="loading" class="mt-6 rounded-lg bg-primary px-5 py-3 font-semibold text-white disabled:opacity-50" x-text="loading ? 'Checking…' : 'Check availability'"></button>
            <p role="status" x-show="resultLabel" x-cloak x-text="resultLabel + ' for your selected visit'" class="mt-4 font-semibold text-text"></p>
            <p role="alert" x-show="error" x-cloak x-text="error" class="mt-4 text-sm text-danger"></p>
            <div x-show="available" x-cloak class="mt-6 border-t border-border pt-5" role="status">
                <p class="font-semibold text-text">Available for your selected visit</p>
                <p class="mt-2 text-text-muted">Estimated reservation total: <strong x-text="price"></strong></p>
                <a :href="bookingUrl" class="mt-4 inline-flex rounded-lg bg-primary px-5 py-3 font-semibold text-white">Reserve {{ $facility->name }}</a>
                @guest<p class="mt-2 text-xs text-text-muted">Sign in or create an account to continue. Your visit details will be kept.</p>@endguest
            </div>
        </form>
    </section>
    @push('scripts')
    <script>
    document.addEventListener('alpine:init', () => Alpine.data('serviceAvailability', () => ({
        arrival: @js(str_replace(' ', 'T', $prefill['check_in'] ?? now()->addDay()->setTimeFromTimeString(config('resort.stay.overnight_hours.check_in'))->format('Y-m-d H:i'))),
        departure: @js(str_replace(' ', 'T', $prefill['check_out'] ?? now()->addDays(2)->setTimeFromTimeString(config('resort.stay.overnight_hours.check_out'))->format('Y-m-d H:i'))),
        earlyHours: 0, paxTier: @js($prefill['pax_tier'] ?? ($facility->paxTiers()[0] ?? null)),
        guests: @js($prefill['guest_count'] ?? 1), loading: false, available: false, resultLabel: '', error: '', price: '',
        get bookingUrl() {
            const url = new URL(@js(route('customer.booking.legacy', $facility)));
            url.searchParams.set('check_in', this.arrival.replace('T', ' ').slice(0, 16));
            url.searchParams.set('check_out', this.departure.replace('T', ' ').slice(0, 16));
            url.searchParams.set('guests', this.guests);
            url.searchParams.set('early_check_in_hours', this.earlyHours);
            if (this.paxTier) url.searchParams.set('pax_tier', this.paxTier);
            return url.href;
        },
        async check() {
            this.loading = true; this.available = false; this.error = '';
            const selection = JSON.stringify({check_in: this.arrival, check_out: this.departure, guestCount: this.guests, early_check_in_hours: this.earlyHours, pax_tier: this.paxTier});
            try {
                const response = await fetch(@js(route('facilities.availability.calculate', $facility)), {
                    method: 'POST', headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token())}, body: selection,
                });
                const data = await response.json();
                if (selection !== JSON.stringify({check_in: this.arrival, check_out: this.departure, guestCount: this.guests, early_check_in_hours: this.earlyHours, pax_tier: this.paxTier})) return;
                this.resultLabel = data.availability?.status_label || '';
                if (!response.ok || !data.success) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Unable to check these dates.');
                if (selection !== JSON.stringify({check_in: this.arrival, check_out: this.departure, guestCount: this.guests, early_check_in_hours: this.earlyHours, pax_tier: this.paxTier})) return;
                // The API quotes cents; window.pesosFromCents is the shared
                // browser money format (resources/js/money.js).
                this.price = window.pesosFromCents(data.data.total_amount);
                this.available = true;
            } catch (failure) { this.error = failure.message; } finally { this.loading = false; }
        },
    })));
    </script>
    @endpush
</x-customer-layout>
