@php
    $initialDestinationId = (string) old('payment_destination_id', $proofDestinations->first()?->id);
    $initialMethod = old('payment_method', $proofDestinations->firstWhere('id', (int) $initialDestinationId)?->method->value
        ?? $proofDestinations->first()?->method->value);
    $activeDestinationIds = $destinations->pluck('id')->map(fn ($id) => (string) $id)->values();
@endphp
<section
    class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden"
    @if($proofDestinations->isNotEmpty())
        x-data="{
            selected: '{{ $initialDestinationId }}',
            method: '{{ $initialMethod }}',
            activeIds: {{ $activeDestinationIds->toJson() }},
            get showsQr() { return this.activeIds.includes(String(this.selected)); }
        }"
    @endif
>
    <div class="px-5 py-4 sm:px-6 border-b border-border">
        <h2 class="font-heading text-2xl font-semibold text-text">Submit your deposit</h2>
        <p class="mt-1 text-sm text-text-muted">
            Required deposit: <strong class="text-text">₱{{ number_format($reservation->requiredDepositAmount(), 2) }}</strong>.
            Deposit still available to submit: <strong class="text-text">₱{{ number_format($reservation->getDepositAvailableAmount(), 2) }}</strong>.
            @if($reservation->deposit_due_at)
                Submit proof by {{ $reservation->deposit_due_at->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }} (Philippine time).
            @endif
        </p>
        <p class="mt-1 text-sm text-text-muted">Pay using the resort QR, then upload your transfer proof. Staff must verify receipt of funds before confirmation. The remaining reservation balance is payable at the resort upon arrival.</p>
    </div>

    <div class="px-5 py-5 sm:p-6">
        @if($proofDestinations->isNotEmpty())
            <form method="POST" action="{{ route('customer.payments.store', $reservation) }}" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @csrf
                <div class="space-y-4">
                    <label class="block text-sm font-medium text-text">Receiving account
                        <select
                            name="payment_destination_id"
                            required
                            class="mt-1 block w-full rounded-md border-border text-text focus:border-primary focus:ring-primary"
                            x-model="selected"
                            @change="method = $event.target.selectedOptions[0].dataset.method"
                        >
                            @foreach($proofDestinations as $destination)
                                <option
                                    value="{{ $destination->id }}"
                                    data-method="{{ $destination->method->value }}"
                                    @selected((string) old('payment_destination_id', $proofDestinations->first()->id) === (string) $destination->id)
                                >{{ $destination->method->label() }} — {{ $destination->account_number }}{{ $destination->is_active ? '' : ' (retired account: existing transfer proof only)' }}</option>
                            @endforeach
                        </select>
                    </label>
                    <input type="hidden" name="payment_method" :value="method">
                    <p class="text-sm text-text-muted">Use the QR for the account you select. A retired account can be selected only to report money already transferred before its replacement.</p>

                    @forelse($destinations as $destination)
                        <div
                            class="border border-border rounded-xl bg-background p-4"
                            x-show="String(selected) === '{{ $destination->id }}'"
                            x-cloak
                        >
                            <h3 class="font-semibold text-text">{{ $destination->method->label() }} {{ $destination->bank }}</h3>
                            <img src="{{ route('payment-destinations.image', $destination) }}" class="w-56 h-56 object-contain mx-auto" alt="Resort payment QR for {{ $destination->account_name }}">
                            <p class="text-sm text-text">{{ $destination->account_name }} · {{ $destination->account_number }}</p>
                            <a class="text-primary underline text-sm" href="{{ route('payment-destinations.image', [$destination, 'download' => 1]) }}">Download QR</a>
                            <a class="text-primary underline text-sm ml-3" href="{{ route('payment-destinations.image', $destination) }}" target="_blank" rel="noopener">View full size</a>
                            <p class="text-xs text-text-muted mt-2">On the same phone, save the QR and import it in your payment app if supported. Check the recipient and enter the exact deposit amount.</p>
                        </div>
                    @empty
                        <p class="text-sm text-text-muted">Payment instructions are currently unavailable. Contact the resort before transferring money.</p>
                    @endforelse

                    <p class="text-sm text-text-muted" x-show="!showsQr" x-cloak>
                        This retired account is only for reporting a transfer already made. New transfers should use an active QR.
                    </p>
                </div>

                <div class="space-y-4">
                    <label class="block text-sm font-medium text-text">Amount transferred (deposit only)
                        <input type="number" name="amount" min="0.01" step="0.01" max="{{ $reservation->getDepositAvailableAmount() }}" value="{{ old('amount', $reservation->getDepositAvailableAmount()) }}" required class="mt-1 block w-full rounded-md border-border text-text focus:border-primary focus:ring-primary">
                    </label>
                    <label class="block text-sm font-medium text-text">Proof of payment
                        <input type="file" name="proof" accept=".png,.jpg,.jpeg,.pdf" required class="mt-2 block w-full text-sm text-text-muted file:mr-3 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-2 file:text-sm file:font-medium file:text-white">
                    </label>
                    <p class="text-sm text-text-muted">PNG, JPG, or PDF, up to 5 MB. A family member may pay for you; staff will read the reference number and transfer date from your uploaded slip.</p>
                    <button type="submit" class="w-full bg-primary text-white rounded-lg px-4 py-3 font-medium hover:opacity-95">Submit deposit proof</button>
                </div>
            </form>
        @elseif($destinations->isEmpty())
            <p class="text-sm text-text-muted">Payment instructions are currently unavailable. Contact the resort before transferring money.</p>
        @endif
    </div>
</section>
