@if($reservation->facility->isRoom())
    @php
        $departureRequest = $reservation->late_checkout_request;
    @endphp
    @if($departureRequest || $reservation->canRequestLateCheckout())
        <section class="rounded-2xl border border-border bg-surface p-5 space-y-4" x-data="{
            hours: 1, quote: null, loading: false, error: '', attempt: 0,
            async check() {
                const attempt = ++this.attempt;
                this.loading = true; this.quote = null; this.error = '';
                try {
                    const response = await fetch(@js(route('customer.reservations.late-checkout.quote', $reservation)), {
                        method: 'POST', headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token())}, body: JSON.stringify({hours: this.hours})
                    });
                    const data = await response.json();
                    if (attempt !== this.attempt) return;
                    if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message);
                    this.quote = data;
                } catch (failure) { if (attempt === this.attempt) this.error = failure.message; }
                finally { if (attempt === this.attempt) this.loading = false; }
            }
        }">
            <h2 class="font-heading text-2xl font-semibold">Room late check-out</h2>
            @if($departureRequest)
                <p class="text-sm">Last request: <strong>{{ ucfirst($departureRequest['status']) }}</strong> · {{ $departureRequest['hours'] }} additional room hour(s) · <x-amount :value="$departureRequest['fee_cents'] / 100" /></p>
                @if($departureRequest['status'] === 'pending')
                    <p class="text-sm text-text-muted">Under staff review. No fee charged and no check out extension yet.</p>
                @elseif($departureRequest['status'] === 'declined')
                    <p class="text-sm text-text-muted">{{ $departureRequest['reason'] }} No fee was charged.</p>
                @else
                    <p class="text-sm text-text-muted">Approved fee is included in your total and remaining balance. This does not record a payment.</p>
                @endif
            @endif
            @if($reservation->canRequestLateCheckout())
                <p class="text-sm text-text-muted"><x-amount :value="300" /> per additional room hour. Staff must approve the extended occupancy; a quote does not hold the room.</p>
                <label for="late-hours" class="block text-sm font-medium">Additional hours after your current check out</label>
                <input id="late-hours" type="number" min="1" max="{{ config('resort.stay.max_additional_hours', 12) - $reservation->late_check_out_hours }}" x-model.number="hours" @input="quote = null; attempt++; loading = false" class="w-full rounded-lg border-border">
                <button type="button" @click="check" :disabled="loading" class="text-primary font-semibold" x-text="loading ? 'Checking…' : 'Review exact fee and availability'"></button>
                <p x-show="error" x-text="error" role="alert" class="text-sm text-danger"></p>
                <template x-if="quote">
                    <div class="space-y-3 text-sm">
                        <p><strong x-text="quote.availability.status_label"></strong> for extended occupancy. <span x-text="quote.availability.reason"></span></p>
                        <p>Requested check out: <span x-text="quote.departure"></span> Philippine time</p>
                        <p>Exact additional fee: <strong x-text="window.pesosFromCents(quote.fee_cents)"></strong></p>
                        <form method="POST" action="{{ route('customer.reservations.late-checkout.store', $reservation) }}">
                            @csrf
                            <input type="hidden" name="hours" :value="quote.hours">
                            <input type="hidden" name="fee_cents" :value="quote.fee_cents">
                            <x-primary-button type="submit">Accept fee & request staff approval</x-primary-button>
                        </form>
                        <p class="text-xs text-text-muted">If unavailable, staff can decline without charging you. Only approval updates your booking.</p>
                    </div>
                </template>
            @endif
        </section>
    @endif
@endif
