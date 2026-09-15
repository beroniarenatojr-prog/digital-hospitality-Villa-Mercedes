@if(config('payments.deposit_workflow') && $reservation->getRemainingBalance() > 0)
<section class="bg-white border rounded-lg p-5 space-y-3">
    <h2 class="text-lg font-semibold">Record on-site payment</h2>
    <p>Collect the remaining ₱{{ number_format($reservation->getRemainingBalance(), 2) }} at arrival. Check incoming QR transfers in the resort account before recording them.</p>
    <p class="text-sm text-gray-600">This records reservation payments only. Keep food, damage, and other charges incurred during the visit in the resort's separate incidental-charge register.</p>
    <form method="POST" action="{{ route('admin.payments.onsite', $reservation) }}" class="space-y-3">@csrf
        <label class="block">Amount collected<input type="number" name="amount" step="0.01" min="0.01" required value="{{ old('amount', $reservation->getRemainingBalance()) }}" class="block w-full rounded"></label>
        <label class="block">Method<select name="method" required class="block w-full rounded">@foreach(\App\Enums\PaymentMethod::cases() as $method)<option value="{{ $method->value }}">{{ $method->label() }}</option>@endforeach</select></label>
        <label class="block">Transaction reference (required for transfers)<input name="reference_number" maxlength="255" class="block w-full rounded"></label>
        <label class="block">Collected at (Philippine time)<input type="datetime-local" name="collected_at" required value="{{ now('Asia/Manila')->format('Y-m-d\TH:i') }}" class="block w-full rounded"></label>
        <label class="block">Reconciliation note, if needed<textarea name="reconciliation_note" maxlength="500" rows="2" class="block w-full rounded"></textarea></label>
        <label class="flex gap-2"><input type="checkbox" name="funds_received" value="1" required> I received the cash or verified the transfer in the resort account.</label>
        <button class="bg-primary text-white px-4 py-2 rounded">Record collected payment</button>
    </form>
</section>
@endif
