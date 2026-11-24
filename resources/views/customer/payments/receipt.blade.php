<x-customer-layout>
    <div class="mx-auto max-w-3xl px-4 py-10 space-y-6">
        <x-customer.page-header title="Verified payment receipt" :back="['label' => 'My reservations', 'href' => route('customer.reservations.index')]" />
        <p class="text-sm text-text-muted">Payment record #{{ $payment->id }} · Verified {{ $payment->verified_at?->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }} Philippine time</p>
        <section class="rounded-2xl border border-border bg-surface p-6 space-y-4">
            <p>Booking: <strong>{{ $group?->group_code ?? $reservations->first()?->reservation_code }}</strong></p>
            <p>Paid amount for this payment: <strong><x-amount :value="$payment->amount" /></strong></p>
            <p>Payment status: <strong>{{ $payment->status->label() }}</strong> · {{ $payment->method->label() }}</p>
            @if($payment->reference_number)<p>Reference: {{ $payment->reference_number }}</p>@endif
            <p class="text-sm text-text-muted">The verified payment above is recorded funds received. The itemization below shows the current agreed booking, including later approved charges; it is not a claim that those charges have all been paid. Email queueing or sending is not proof of delivery.</p>
        </section>
        @foreach($reservations as $reservation)
            <section class="rounded-2xl border border-border bg-surface p-6 space-y-3">
                <h2 class="text-xl font-semibold">{{ $reservation->facility?->name }} · {{ $reservation->reservation_code }}</h2>
                <p class="text-sm">Booking status: {{ $reservation->status->label() }}</p>
                <p class="text-sm">Accepted policy: {{ $reservation->policy_version }}. Historical accepted terms and prices are retained.</p>
                @php($refund = ($group?->booking_workflow ?? $reservation->booking_workflow ?? [])['refund'] ?? null)
                @if($refund)<p class="text-sm">Refund stage: {{ $refund['status'] }}. Request, owner approval, and money returned are separate records.</p>@endif
                <p class="text-sm">{{ $reservation->occupancyStart()->format('M d, Y g:i A') }} – {{ $reservation->occupancyEnd()->format('M d, Y g:i A') }} Philippine time</p>
                @if($reservation->early_check_in_hours)<p class="text-sm">Early check-in: {{ $reservation->early_check_in_hours }} room hour(s)</p>@endif
                @if($reservation->late_check_out_hours)<p class="text-sm">Approved late check-out: {{ $reservation->late_check_out_hours }} room hour(s)</p>@endif
                <dl class="space-y-2">
                    @foreach($reservation->priceItems() as $label => $cents)
                        <div class="flex justify-between gap-4"><dt>{{ $label }}</dt><dd><x-amount :value="$cents / 100" /></dd></div>
                    @endforeach
                    <div class="flex justify-between gap-4 border-t border-border pt-3 font-semibold"><dt>Booking total</dt><dd><x-amount :value="$reservation->total_price" /></dd></div>
                </dl>
            </section>
        @endforeach
        <section class="rounded-2xl border border-border bg-surface p-6">
            <dl class="space-y-3">
                <div class="flex justify-between gap-4"><dt>Verified paid to date</dt><dd><x-amount :value="$verifiedPaid" /></dd></div>
                <div class="flex justify-between gap-4"><dt>Under review (not verified paid)</dt><dd><x-amount :value="$pendingPaid" /></dd></div>
                <div class="flex justify-between gap-4 font-semibold"><dt>Remaining balance due at check-in</dt><dd><x-amount :value="$balance" /></dd></div>
            </dl>
        </section>
        <div class="flex flex-wrap gap-4 print:hidden">
            <button type="button" onclick="window.print()" class="rounded-lg bg-primary px-4 py-2 font-semibold text-white">Print receipt</button>
            <a href="{{ route('customer.reservations.index') }}" class="text-primary font-semibold">My reservations</a>
        </div>
        <p class="text-xs text-text-muted">Booking payment acknowledgment; not a tax invoice.</p>
    </div>
</x-customer-layout>
