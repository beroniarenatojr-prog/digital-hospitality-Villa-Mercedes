@php
    $departureRequest = $reservation->late_checkout_request;
@endphp
@if($departureRequest)
    <x-admin.panel title="Room late check-out request" description="An unapproved request never occupies the room or adds a charge.">
        <p class="text-sm"><strong>{{ ucfirst($departureRequest['status']) }}</strong> · {{ $departureRequest['hours'] }} additional room hour(s)</p>
        <p class="mt-2 text-sm">Requested check out: {{ $departureRequest['departure'] }} Philippine time</p>
        <p class="mt-2 text-sm">Exact fee: <strong><x-amount :value="$departureRequest['fee_cents'] / 100" /></strong> (<x-amount :value="300" /> per room hour)</p>
        @if($departureRequest['status'] === 'pending')
            @php
                $departureQuote = null;
                $departureError = null;
                try {
                    $departureQuote = app(\App\Services\LateCheckoutService::class)->quote($reservation, $departureRequest['hours']);
                } catch (\Illuminate\Validation\ValidationException $exception) {
                    $departureError = collect($exception->errors())->flatten()->first();
                }
            @endphp
            <p class="mt-3 text-sm">{{ $departureError ?: ($departureQuote['availability']['status_label'].': '.($departureQuote['availability']['reason'] ?? 'No current booking conflict.')) }}</p>
            <p class="mt-2 text-xs text-text-muted">Approval rechecks availability under resource locks and adds this fee to the balance, not to verified paid.</p>
            <form method="POST" action="{{ route('admin.reservations.late-checkout.decide', $reservation) }}" class="mt-4 space-y-3">
                @csrf
                @method('PATCH')
                <input type="hidden" name="fee_cents" value="{{ $departureRequest['fee_cents'] }}">
                <x-admin.button type="submit" name="decision" value="approve" :disabled="!($departureQuote['availability']['is_available'] ?? false)">Approve & add fee</x-admin.button>
                <label for="late-decline-reason-{{ $reservation->id }}" class="admin-label">Decline reason</label>
                <textarea id="late-decline-reason-{{ $reservation->id }}" name="reason" maxlength="1000" rows="2" class="admin-input" placeholder="Explain the unavailable period or operational closure">{{ old('reason') }}</textarea>
                <x-admin.button type="submit" name="decision" value="decline" variant="secondary">Decline without charge</x-admin.button>
                <x-input-error :messages="$errors->get('late_checkout')" />
                <x-input-error :messages="$errors->get('hours')" />
                <x-input-error :messages="$errors->get('reason')" />
            </form>
        @else
            <p class="mt-3 text-sm">{{ $departureRequest['reason'] ?? 'Approved fee is included in the booking total.' }}</p>
        @endif
    </x-admin.panel>
@endif
