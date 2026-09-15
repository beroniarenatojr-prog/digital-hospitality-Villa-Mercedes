@if($payment->source === 'customer_deposit')
    @php
        $duplicates = \App\Models\Payment::where('id', '!=', $payment->id)->where(function ($q) use ($payment) {
            $q->where(fn ($ref) => $ref->where('method', $payment->method->value)->where('reference_number', $payment->reference_number));
            if($payment->proof_hash) $q->orWhere('proof_hash', $payment->proof_hash);
        })->count();
    @endphp
    <section class="bg-white border rounded-lg p-5 space-y-2">
        <h2 class="font-semibold">Deposit review</h2>
        <p>Required deposit: ₱{{ number_format($payment->reservation->requiredDepositAmount(), 2) }} · Proof received {{ $payment->created_at->format('M d, Y g:i A') }}</p>
        @if($payment->status === \App\Enums\PaymentStatus::Pending && $payment->created_at->lte(now()->subHours(config('payments.review_hours'))))<p class="font-semibold text-danger">Overdue review — reconcile this transfer now. Availability stays held until staff resolve it.</p>@endif
        @if($duplicates)<p class="text-danger font-semibold">{{ $duplicates }} other submission(s) use this reference or receipt. Check for duplicate funds before verification.</p>@endif
        <p>Receiving account shown to guest: {{ $payment->destination_snapshot['account_name'] ?? '' }} · {{ $payment->destination_snapshot['account_number'] ?? '' }}</p>
        <p>Deposit deadline: {{ $payment->reservation->deposit_due_at?->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') ?? 'Staff arranged' }} (Philippine time)</p>
        <p class="text-sm">Verify the actual credit in the resort account. Uncertain transfers stay pending until reconciled. Reject only when these funds were not received. A partial verified deposit requires a top-up.</p>
        @if($payment->reservation->status === \App\Enums\ReservationStatus::Pending && $payment->reservation->getPendingVerificationTotal() <= 0 && $payment->reservation->getDepositAvailableAmount() > 0)
            <form method="POST" action="{{ route('admin.payments.top-up', $payment->reservation) }}" class="space-y-2">@csrf
                <label class="block">Top-up instructions<textarea name="reason" maxlength="500" required class="block w-full rounded border-gray-300" rows="2"></textarea></label>
                <button class="bg-primary text-white rounded px-4 py-2">Request deposit top-up</button>
            </form>
        @endif
    </section>
@endif
