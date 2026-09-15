@php
    $remaining = $reservation->getRemainingBalance();
    $paymentState = $reservation->guestPaymentState();
    $canPay = $reservation->canAcceptGuestPayment();
@endphp

<div class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 sm:px-6 flex flex-col sm:flex-row sm:justify-between sm:items-start gap-3 border-b border-border">
        <div class="min-w-0">
            <h3 class="font-heading text-2xl font-semibold text-text truncate">
                {{ $reservation->facility->name }}
            </h3>
            <p class="mt-1 text-sm text-text-muted">
                {{ $reservation->reservation_code }}
            </p>
        </div>
        <div class="self-start">
            <x-badge :status="$reservation->status->label()" />
        </div>
    </div>

    @php
        $showTimeline = in_array($reservation->status, [
            \App\Enums\ReservationStatus::Pending,
            \App\Enums\ReservationStatus::Approved,
        ], true);
        $timelinePaid = $remaining <= 0;
        $timelineArrival = $reservation->checked_in_at !== null;
        $timelineSteps = [
            ['key' => 'submitted', 'label' => 'Submitted', 'done' => true],
            ['key' => 'confirmed', 'label' => 'Confirmed', 'done' => $reservation->status === \App\Enums\ReservationStatus::Approved],
            ['key' => 'paid', 'label' => 'Balance at arrival', 'done' => $timelinePaid],
            ['key' => 'arrival', 'label' => 'Arrival', 'done' => $timelineArrival],
        ];
    @endphp

    @if($showTimeline)
        <ol class="px-5 sm:px-6 py-3 border-b border-border flex items-center gap-2 overflow-x-auto" aria-label="Reservation progress">
            @foreach($timelineSteps as $index => $step)
                <li class="flex items-center gap-2 shrink-0">
                    <span @class([
                        'w-6 h-6 rounded-full inline-flex items-center justify-center text-[11px] font-bold',
                        'bg-success text-white' => $step['done'],
                        'bg-primary text-white' => ! $step['done'] && ($index === 0 || $timelineSteps[$index - 1]['done']),
                        'bg-muted text-text-muted' => ! $step['done'] && ! ($index === 0 || $timelineSteps[$index - 1]['done']),
                    ])>{{ $index + 1 }}</span>
                    <span @class(['text-xs font-semibold', 'text-text' => $step['done'] || ($index === 0 || $timelineSteps[$index - 1]['done']), 'text-text-muted' => ! $step['done'] && ! ($index === 0 || $timelineSteps[$index - 1]['done'])])>
                        {{ $step['label'] }}
                    </span>
                </li>
                @if(! $loop->last)
                    <li class="flex-1 h-0.5 bg-border rounded min-w-[1rem]" aria-hidden="true">
                        <div class="h-full bg-primary rounded {{ $step['done'] ? 'w-full' : 'w-0' }}"></div>
                    </li>
                @endif
            @endforeach
        </ol>
    @endif

    <dl class="px-5 py-4 sm:px-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="sm:col-span-2">
            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Dates</dt>
            <dd class="mt-1 text-sm text-text">
                {{ $reservation->check_in->format('M d, Y g:i A') }}
                <span class="text-text-muted">to</span>
                {{ $reservation->check_out->format('M d, Y g:i A') }}
            </dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Guests</dt>
            <dd class="mt-1 text-sm text-text">{{ $reservation->guest_count }}</dd>
        </div>
    </dl>

    <div class="px-5 py-4 sm:px-6 bg-background border-t border-border flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex flex-wrap gap-x-6 gap-y-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Total</p>
                <p class="mt-0.5 text-sm font-medium text-text">₱{{ number_format($reservation->total_price, 2) }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Paid</p>
                <p class="mt-0.5 text-sm font-medium text-success">₱{{ number_format($reservation->getVerifiedTotalPaid(), 2) }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Balance</p>
                @if($remaining > 0)
                    <p class="mt-0.5 font-heading text-xl font-semibold text-primary">₱{{ number_format($remaining, 2) }}</p>
                @else
                    <p class="mt-0.5 text-sm font-semibold text-success">Fully paid</p>
                @endif
            </div>
        </div>

        <div class="flex flex-col-reverse sm:flex-row gap-3 sm:items-center shrink-0">
            @if($reservation->canShowArrivalPass())
                <a href="{{ $reservation->arrivalPassUrl() }}"
                   class="inline-flex justify-center items-center min-h-[44px] px-4 text-sm font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 rounded-lg">
                    Arrival pass
                </a>
            @endif
            <a href="{{ route('customer.reservations.show', $reservation) }}"
               class="inline-flex justify-center items-center min-h-[44px] px-4 text-sm font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 rounded-lg">
                View details
            </a>

            @if($paymentState === \App\Enums\GuestPaymentState::AwaitingApproval)
                <span class="inline-flex justify-center items-center min-h-[44px] px-4 text-sm font-semibold text-text-muted">
                    Waiting for resort approval
                </span>
            @elseif($paymentState === \App\Enums\GuestPaymentState::ReceiptPending)
                <x-primary-button tag="a" href="{{ route('customer.payments.index', $reservation) }}" class="justify-center">
                    Receipt awaiting verification
                </x-primary-button>
            @elseif($canPay)
                <x-primary-button tag="a" href="{{ route('customer.payments.index', $reservation) }}" class="justify-center">
                    {{ config('payments.deposit_workflow') ? 'Submit deposit' : 'Pay balance' }}
                </x-primary-button>
            @endif
        </div>
    </div>
</div>
