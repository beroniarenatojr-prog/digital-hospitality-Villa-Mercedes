@php
    $lines = $group->reservations;
    $active = $lines->filter(fn ($line) => in_array($line->status, [\App\Enums\ReservationStatus::Pending, \App\Enums\ReservationStatus::Approved, \App\Enums\ReservationStatus::Completed], true));
    $hasPending = $active->contains(fn ($line) => $line->status === \App\Enums\ReservationStatus::Pending);
    $stayFinished = $active->isNotEmpty() && $active->every(fn ($line) => $line->status === \App\Enums\ReservationStatus::Completed || ($line->status === \App\Enums\ReservationStatus::Approved && $line->check_out->isPast()));
    $hasCurrent = $active->contains(fn ($line) => $line->status === \App\Enums\ReservationStatus::Approved && $line->check_in->isPast() && $line->check_out->isFuture());
    $status = match (true) {
        $hasCurrent => \App\Enums\ReservationStatus::Approved,
        $hasPending => \App\Enums\ReservationStatus::Pending,
        $stayFinished => \App\Enums\ReservationStatus::Completed,
        $active->isNotEmpty() => \App\Enums\ReservationStatus::Approved,
        default => $lines->first()->status,
    };
    $mixedStatuses = $lines->map(fn ($line) => $line->status->value)->unique()->count() > 1;
    $verified = $group->verifiedPaymentsTotal();
    $pending = $group->pendingPaymentsTotal();
    $balance = $group->outstandingBalance();
    $balanceIfVerified = max(0, round($balance - $pending, 2));
    $detailsUrl = route('customer.reservation-groups.show', [$group, ...request()->only('tab', 'page')]);
    $paymentsUrl = route('customer.reservation-groups.payments', [$group, ...request()->only('tab', 'page')]);
    $canPayQuote = $group->isPending() && $group->quoted_at && ! $group->requires_quote && ! $group->directPayments()->exists();
    $refund = $group->booking_workflow['refund']['status'] ?? null;
    $isClosed = $active->isEmpty();
    $trackClosedRefund = $isClosed && $refund;
    $closedReason = $isClosed ? ($group->rejection_reason ?: $group->cancellation_reason) : null;
    $statusLabel = match (true) {
        $mixedStatuses => 'Status by service',
        $hasCurrent => 'Current stay',
        $hasPending && $group->requires_quote => 'Quote requested',
        $hasPending => 'Awaiting confirmation',
        $status === \App\Enums\ReservationStatus::Rejected => 'Declined',
        default => null,
    };
    $firstDate = $lines->min('check_in');
    $lastDate = $lines->max('check_out');
@endphp
<div class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 sm:px-6 flex flex-col sm:flex-row sm:justify-between sm:items-start gap-3 border-b border-border">
        <div class="min-w-0">
            <h2 class="break-words font-heading text-2xl font-semibold text-text">Grouped reservation · {{ $lines->count() }} services</h2>
            <p class="mt-1 text-sm text-text-muted">{{ $group->group_code }}</p>
        </div>
        <div class="self-start">
            <x-badge :status="$mixedStatuses ? 'Status by service' : $status" :label="$statusLabel" />
        </div>
    </div>
    <div class="space-y-2 px-5 py-4 sm:px-6">
        <p class="text-sm text-text"><span class="font-semibold">Booking dates</span> · {{ $firstDate->format('M j, Y') }}@if(!$firstDate->isSameDay($lastDate)) to {{ $lastDate->format('M j, Y') }}@endif</p>
        @if($closedReason)<p class="break-words text-sm text-text-muted">{{ $closedReason }}</p>@endif
        <section>
            <h3 class="pt-2 text-sm font-semibold text-text">Services and guest counts</h3>
            <ul class="divide-y divide-border text-sm">
                @foreach($lines as $line)
                    <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-2">
                        <span>{{ $line->facility->name }}</span>
                        <span class="flex flex-wrap items-center gap-2 text-text-muted">
                            {{ $line->guest_count }} {{ Str::plural('guest', $line->guest_count) }}
                            @if($mixedStatuses)<x-badge :status="$line->status" :label="$line->status === \App\Enums\ReservationStatus::Rejected ? 'Declined' : null" />@endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
    <div class="flex flex-col gap-4 border-t border-border bg-background px-5 py-4 sm:px-6 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="grid grid-cols-2 gap-x-6 gap-y-3 sm:flex sm:flex-wrap">
                <div><p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Total</p><p class="mt-0.5 text-sm font-medium text-text"><x-amount :value="$group->total_amount" /></p></div>
                <div><p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Verified paid</p><p class="mt-0.5 text-sm font-medium text-success"><x-amount :value="$verified" /></p></div>
                @if ($pending > 0)<div><p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Receipt under review</p><p class="mt-0.5 text-sm font-semibold text-warning"><x-amount :value="$pending" /></p></div>@endif
                @if(! $isClosed || $refund)
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ $refund ? 'Refund' : ($pending > 0 ? 'Balance if verified' : 'Remaining balance') }}</p>
                    @if($refund)
                        <p class="mt-0.5 text-sm font-semibold text-text">{{ match ($refund) { 'returned' => 'Funds returned', 'approved' => 'Refund approved, awaiting return', default => 'Refund requested' } }}</p>
                    @else
                        <p class="mt-0.5 font-heading text-xl font-semibold text-primary"><x-amount :value="$pending > 0 ? $balanceIfVerified : $balance" /></p>
                    @endif
                </div>
                @endif
            </div>
            @if($pending > 0)
                <p class="mt-3 text-sm text-text-muted">{{ $isClosed ? 'The resort still needs to verify your transfer.' : 'Receipt awaiting verification. Wait for the resort to verify your transfer before sending another payment.' }}</p>
            @endif
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap lg:justify-end">
            @if($trackClosedRefund)
                <x-primary-button tag="a" href="{{ $detailsUrl }}#refund-flow" class="justify-center">Track refund</x-primary-button>
            @elseif($canPayQuote)
                <x-primary-button tag="a" href="{{ route('customer.grouped-booking.quote-checkout', $group) }}" class="justify-center">Review quote and submit deposit</x-primary-button>
            @elseif($pending > 0 || ($isClosed && $verified > 0))
                <x-primary-button tag="a" href="{{ $paymentsUrl }}" class="justify-center">View payment status</x-primary-button>
            @endif
            @php($hasPrimaryAction = $trackClosedRefund || $canPayQuote || $pending > 0 || ($isClosed && $verified > 0))
            <a href="{{ $detailsUrl }}" @class(['inline-flex min-h-11 items-center justify-center rounded-lg px-4 text-sm font-semibold', 'bg-primary text-white hover:bg-primary-hover' => ! $hasPrimaryAction, 'text-primary underline' => $hasPrimaryAction])>View booking</a>
            @if($refund && ! $trackClosedRefund)<a href="{{ $detailsUrl }}#refund-flow" class="inline-flex min-h-11 items-center justify-center text-sm font-semibold text-primary underline">Track refund</a>@endif
        </div>
    </div>
</div>
