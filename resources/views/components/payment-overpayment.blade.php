@props(['reservation', 'staff' => false])

@if ($reservation->getOverpaymentAmount() > 0)
    {{-- One money renderer for the whole app, staff and guest alike: this is the
         same amount the reconciliation screen shows, so the two can never
         disagree about thousands separators or decimals. --}}
    <x-alert type="warning" title="Overpayment requires reconciliation">
        Verified payments exceed the current reservation total by
        <x-amount :value="$reservation->getOverpaymentAmount()" />.
        {{ $staff ? 'Review the payment ledger and arrange reconciliation with the guest.' : 'Please contact the resort to reconcile this amount.' }}
    </x-alert>
@endif
