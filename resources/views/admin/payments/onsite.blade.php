@if (
    (config('payments.deposit_workflow') || $reservation->policy_version === config('resort.policy.confirmed_version'))
    && $reservationAccepted
    && $paymentReady
    && ($groupDue ?? $reservation->getRemainingBalance()) > 0
)
    <x-admin.panel
        :title="isset($groupAction) ? 'Record group balance' : 'Record remaining reservation balance'"
        description="Record the balance paid at check in for this reservation."
    >
        @if (! isset($groupAction))
            <p class="text-sm text-text-muted">
                Remaining reservation balance: <span class="font-semibold text-text"><x-amount :value="($groupDue ?? $reservation->getRemainingBalance())" /></span>.
                Record only the amount the guest pays toward this balance, and verify QR transfers in the resort account first.
            </p>
        @endif
        <form method="POST" action="{{ $groupAction ?? route('admin.payments.onsite', $reservation) }}" class="mt-4 space-y-4">
            @csrf

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="onsite_amount_{{ $reservation->id }}" class="admin-label">Remaining balance collected <span class="text-danger" aria-hidden="true">*</span></label>
                    <input
                        type="number"
                        name="amount"
                        id="onsite_amount_{{ $reservation->id }}"
                        @readonly(isset($groupAction))
                        step="0.01"
                        min="0.01"
                        required
                        value="{{ old('amount', ($groupDue ?? $reservation->getRemainingBalance())) }}"
                        class="admin-input mt-1 tabular-nums"
                    >
                </div>

                <div>
                    <label for="onsite_method_{{ $reservation->id }}" class="admin-label">Method <span class="text-danger" aria-hidden="true">*</span></label>
                    <select name="method" id="onsite_method_{{ $reservation->id }}" required class="admin-input mt-1">
                        @foreach (\App\Enums\PaymentMethod::cases() as $method)
                            <option value="{{ $method->value }}" @selected(old('method') === $method->value)>{{ $method->label() }}</option>
                        @endforeach
                    </select>
                </div>


                <div>
                    <label for="onsite_collected_at_{{ $reservation->id }}" class="admin-label">Collected at <span class="text-danger" aria-hidden="true">*</span></label>
                    <input
                        type="datetime-local"
                        name="collected_at"
                        id="onsite_collected_at_{{ $reservation->id }}"
                        required
                        value="{{ old('collected_at', now('Asia/Manila')->format('Y-m-d\TH:i')) }}"
                        class="admin-input mt-1"
                    >
                </div>
            </div>

            <div>
                <label for="onsite_note_{{ $reservation->id }}" class="admin-label">Balance payment note</label>
                <textarea name="reconciliation_note" id="onsite_note_{{ $reservation->id }}" maxlength="500" rows="2" class="admin-input mt-1">{{ old('reconciliation_note') }}</textarea>
                <p class="admin-help">Add a note when the recorded balance payment differs from the amount or reference on the guest's proof.</p>
            </div>

            <label class="flex items-start gap-2.5 text-sm">
                <input type="checkbox" name="funds_received" value="1" required class="mt-0.5 rounded border-border text-primary focus:ring-primary">
                <span>I received this remaining-balance payment or verified the transfer in the resort account.</span>
            </label>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <x-admin.button type="submit">Record balance payment</x-admin.button>
            </div>
        </form>
    </x-admin.panel>
@endif
