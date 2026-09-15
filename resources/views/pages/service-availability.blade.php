<x-customer-layout>
    <section class="mx-auto max-w-3xl px-4 py-12 sm:px-6" x-data="serviceAvailability()">
        <a href="{{ route('facilities.show', $facility) }}" class="text-sm font-semibold text-primary hover:underline">← {{ $facility->name }}</a>
        <h1 class="mt-5 font-heading text-4xl font-semibold text-text">Check availability</h1>
        <p class="mt-3 text-text-muted">Choose your visit for {{ $facility->name }}. You can check dates and prices before signing in.</p>
        <form @submit.prevent="check" class="mt-8 rounded-2xl border border-border bg-surface p-6">
            <div class="grid gap-5 sm:grid-cols-2">
                <div><x-input-label for="public-arrival" value="Arrival / event start" /><x-text-input id="public-arrival" type="datetime-local" x-model="arrival" @input="available = false" min="{{ now()->format('Y-m-d\TH:i') }}" required class="mt-2 w-full" /></div>
                <div><x-input-label for="public-departure" value="Departure / event end" /><x-text-input id="public-departure" type="datetime-local" x-model="departure" @input="available = false" min="{{ now()->format('Y-m-d\TH:i') }}" required class="mt-2 w-full" /></div>
                <div><x-input-label for="public-guests" value="Party size" /><x-text-input id="public-guests" type="number" x-model.number="guests" @input="available = false" min="1" :max="$facility->effectiveMaxCapacity()" required class="mt-2 w-full" /></div>
            </div>
            <button type="submit" :disabled="loading" class="mt-6 rounded-lg bg-primary px-5 py-3 font-semibold text-white disabled:opacity-50" x-text="loading ? 'Checking…' : 'Check availability'"></button>
            <p role="alert" x-show="error" x-cloak x-text="error" class="mt-4 text-sm text-red-700"></p>
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
        guests: @js($prefill['guest_count'] ?? 1), loading: false, available: false, error: '', price: '',
        get bookingUrl() {
            const url = new URL(@js(route('customer.booking.create', $facility)));
            url.searchParams.set('arrival', this.arrival.replace('T', ' ').slice(0, 16));
            url.searchParams.set('departure', this.departure.replace('T', ' ').slice(0, 16));
            url.searchParams.set('guests', this.guests);
            url.searchParams.set('availability_checked', '1');
            return url.href;
        },
        async check() {
            this.loading = true; this.available = false; this.error = '';
            const selection = JSON.stringify({check_in: this.arrival, check_out: this.departure, guestCount: this.guests});
            try {
                const response = await fetch(@js(route('facilities.availability.calculate', $facility)), {
                    method: 'POST', headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token())}, body: selection,
                });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Unable to check these dates.');
                if (selection !== JSON.stringify({check_in: this.arrival, check_out: this.departure, guestCount: this.guests})) return;
                this.price = new Intl.NumberFormat('en-PH', {style: 'currency', currency: 'PHP'}).format(data.data.total_amount / 100);
                this.available = true;
            } catch (failure) { this.error = failure.message; } finally { this.loading = false; }
        },
    })));
    </script>
    @endpush
</x-customer-layout>
