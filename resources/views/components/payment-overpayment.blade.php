@props(['reservation', 'staff' => false])

@if ($reservation->getOverpaymentAmount() > 0)
    <x-alert type="warning" title="Overpayment requires reconciliation">
        Verified payments exceed the current reservation total by ₱{{ number_format($reservation->getOverpaymentAmount(), 2) }}.
        {{ $staff ? 'Review the payment ledger and arrange reconciliation with the guest.' : 'Please contact the resort to reconcile this amount.' }}
    </x-alert>
@endif
