<x-customer-layout>
    @php
        $verified = $group->verifiedPaymentsTotal();
        $pending = $group->pendingPaymentsTotal();
        $balance = $group->outstandingBalance();
        $refund = $group->booking_workflow['refund']['status'] ?? null;
    @endphp
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-10 sm:px-6 lg:px-8">
        <x-customer.page-header title="Deposit & payment history" :description="$group->group_code . ' · ' . $group->reservations->pluck('facility.name')->join(' + ')" :breadcrumbs="[['label' => 'My reservations', 'href' => route('customer.reservations.index', request()->only('tab', 'page'))], ['label' => $group->group_code], ['label' => 'Payments']]" />
        @include('customer.reservations.partials.group-tabs')
        @if($refund)
            <x-alert type="info" title="Cancellation and refund">
                {{ match ($refund) { 'returned' => 'Funds returned — refund complete.', 'approved' => 'Owner approved — awaiting return.', default => 'Refund requested — awaiting review.' } }}
                <a href="{{ route('customer.reservation-groups.show', $group) }}#refund-flow" class="font-semibold underline">Track refund and view next steps</a>
            </x-alert>
        @endif
        @if($pending > 0)<x-alert type="info" title="Receipt awaiting verification">Staff are checking your transfer. Uploaded proof is not verified payment. Your balance updates after verification.</x-alert>@endif
        <dl class="grid gap-6 rounded-2xl border border-border bg-surface p-6 sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-sm text-text-muted">Total</dt><dd class="mt-1 text-xl font-semibold"><x-amount :value="$group->total_amount" /></dd></div>
            <div><dt class="text-sm text-text-muted">Verified paid</dt><dd class="mt-1 text-xl font-semibold text-success"><x-amount :value="$verified" /></dd></div>
            <div><dt class="text-sm text-text-muted">Under review</dt><dd class="mt-1 text-xl font-semibold text-warning"><x-amount :value="$pending" /></dd></div>
            <div><dt class="text-sm text-text-muted">Remaining balance</dt><dd class="mt-1 text-xl font-semibold"><x-amount :value="$balance" /></dd>
                @if(in_array($refund, ['approved', 'returned'], true))<dd class="mt-1 text-sm">{{ $refund === 'returned' ? 'Funds returned' : 'Owner approved — awaiting return' }}. No check in balance collectible.</dd>@endif
            </div>
        </dl>
        @if($verified > (float)$group->total_amount)<x-alert type="info" title="Overpayment recorded">Verified funds exceed the booking total by <x-amount :value="$verified - (float)$group->total_amount" />. Contact staff to reconcile this amount.</x-alert>@endif
        <section class="overflow-hidden rounded-2xl border border-border bg-surface">
            <div class="border-b border-border px-5 py-4 sm:px-6"><h2 class="font-heading text-2xl font-semibold">Payment history</h2><p class="mt-1 text-sm text-text-muted">Payments for all services in this booking, including shared transfers.</p></div>
            <ul class="divide-y divide-border">
                @forelse($payments as $payment)
                    <li class="space-y-3 px-5 py-4 sm:px-6">
                        <div class="flex flex-wrap items-center justify-between gap-3"><span class="font-semibold"><x-amount :value="$payment->amount" /> · {{ $payment->method->label() }}</span><x-badge :status="$payment->status" /></div>
                        <p class="text-sm text-text-muted">{{ $payment->payment_date?->format('M d, Y') ?? $payment->created_at->format('M d, Y') }} @if($payment->reference_number) · Reference: {{ $payment->reference_number }} @endif</p>
                        @if($payment->status === \App\Enums\PaymentStatus::Pending)<p class="text-sm text-text-muted">Under review — not yet verified paid.</p>@endif
                        @if($payment->status === \App\Enums\PaymentStatus::Rejected && $payment->rejection_reason)<p class="text-sm text-danger">{{ $payment->rejection_reason }}</p>@endif
                        @if($payment->verified_at)<p class="text-sm text-text-muted">Verified on {{ $payment->verified_at->format('M d, Y g:i A') }}</p>@endif
                        <div class="flex flex-wrap gap-4">
                            @if($payment->proof_path)<a href="{{ route('customer.payments.view', $payment) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center font-semibold text-primary underline">View submitted proof</a>@endif
                            @if($payment->status === \App\Enums\PaymentStatus::Verified)<a href="{{ route('customer.payments.receipt', $payment) }}" class="inline-flex min-h-11 items-center font-semibold text-primary underline">Payment receipt</a>@endif
                        </div>
                    </li>
                @empty
                    <li class="px-6 py-5 text-sm text-text-muted">No payments submitted for this booking yet.</li>
                @endforelse
            </ul>
        </section>
        @if($group->isPending() && $group->quoted_at && !$group->requires_quote && !$group->directPayments()->exists())
            <x-primary-button tag="a" href="{{ route('customer.grouped-booking.quote-checkout', $group) }}">Review quote and submit deposit</x-primary-button>
        @endif
    </div>
</x-customer-layout>
