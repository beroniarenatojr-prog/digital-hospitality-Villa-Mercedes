@php
    $group = $record instanceof \App\Models\ReservationGroup ? $record : $record->reservationGroup;
    $lines = $record instanceof \App\Models\ReservationGroup ? $record->reservations : collect([$record]);
    $workflow = ($group ?? $record)->booking_workflow ?? [];
    $refund = $workflow['refund']['status'] ?? null;
    $discussion = in_array($refund, ['requested', 'approved'], true) || ($workflow['child_charge']['status'] ?? null) === 'disputed';
    $payments = $group ? $group->directPayments->merge($group->payments) : $record->payments;
    $proof = $payments->where('status', \App\Enums\PaymentStatus::Pending)->sortBy('id')->first();
    $remaining = $group ? $group->outstandingBalance() : $record->getRemainingBalance();
    $pending = $group ? $group->isPending() : $record->status === \App\Enums\ReservationStatus::Pending;
    $allComplete = $lines->isNotEmpty() && $lines->every(fn ($line) => $line->status === \App\Enums\ReservationStatus::Completed);
    $collectBalance = config('payments.deposit_workflow') || ($group ?? $record)->policy_version === config('resort.policy.confirmed_version');
    $now = \App\Support\ResortTime::databaseNow();
    $combined = $record instanceof \App\Models\ReservationGroup && $lines->count() > 1;
    $awaiting = $combined && $lines->every(fn ($line) => $line->status === \App\Enums\ReservationStatus::Approved && ! $line->checked_in_at);
    $arrivingTogether = $awaiting && $lines->every(fn ($line) => $line->check_in->isSameDay($now));
    $departingTogether = $combined && $lines->every(fn ($line) => $line->isInHouse());
    $earlyReview = $lines->filter(fn ($line) => $line->policy_version === config('resort.policy.confirmed_version')
        && $line->facility->isRoom() && $line->check_in->isSameDay($now) && ! $line->check_in->isSameDay($line->check_out)
        && \App\Services\ReservationService::earlyArrivalHours($now->diffInMinutes($line->check_in, false)) > $line->early_check_in_hours);
    $lines = $lines->sortBy(fn ($line) => [$line->checked_in_at !== null, $line->check_in->timestamp]);
@endphp
<x-admin.panel aria-label="Current booking task" class="mb-6" :title="$group?->guest_name ?? $lines->first()?->guestName() ?? 'Booking'">
    <div class="space-y-3 text-sm text-text">
    @if ($allComplete)
        <p>Verified payments · <x-amount :value="$group ? $group->verifiedPaymentsTotal() : $record->getVerifiedTotalPaid()" /></p>
    @endif
    @if ($discussion)
        <p class="font-semibold">{{ $refund === 'requested' ? 'Review cancellation request' : ($refund === 'approved' ? 'Record returned funds' : 'Review disputed charge') }}</p>
    @endif
    @if ($proof)
        <p>{{ $lines->pluck('facility.name')->join(', ') }}</p>
        <x-admin.button :href="route('admin.payments.show', $proof)">Review <x-amount :value="$proof->amount" /> payment</x-admin.button>
    @endif
    @if ($pending && ! $proof)
        @if ($group?->requires_quote)
            <x-admin.button :href="$record instanceof \App\Models\ReservationGroup ? '#booking-quote' : route('admin.reservation-groups.show', $group).'#booking-quote'">Prepare quote</x-admin.button>
        @elseif (($group ?? $record)->policy_version === config('resort.policy.confirmed_version') && ($group ? $group->verifiedPaymentsTotal() < $group->deposit_required : $record->getVerifiedTotalPaid() < $record->requiredDepositAmount()))
            <p>Awaiting payment proof</p>
        @elseif (! $discussion)
            <x-admin.button href="#booking-decision">Review reservation</x-admin.button>
        @endif
    @elseif (! $pending)
        <p class="text-sm">@if ($allComplete)<strong>Stay summary</strong> · @endif Remaining booking balance <strong class="ml-1 tabular-nums"><x-amount :value="$remaining" /></strong></p>
        @if ($lines->count() > 1)
            <p class="text-sm">{{ $lines->filter(fn ($line) => $line->isInHouse())->count() }} of {{ $lines->count() }} services checked in</p>
        @endif
        @if (($awaiting || $departingTogether) && ! $discussion)
            <p>{{ $lines->pluck('facility.name')->join(', ') }}</p>
            @if ($departingTogether)
                <x-admin.confirm-action :action="route('admin.reservation-groups.attendance', [$group, 'check-out'])" tone="primary" label="Check out group" title="Check out all {{ $lines->count() }} services for {{ $group->guest_name }}?" consequence="Confirm once the guests have departed from all listed services." />
            @elseif (! $proof)
                @if ($earlyReview->isNotEmpty())
                    @foreach ($earlyReview as $line)
                        <form method="POST" action="{{ route('admin.reservations.discussion', $line) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="action" value="review_arrival">
                            <x-admin.button type="submit">Review early arrival · {{ $line->facility->name }}</x-admin.button>
                        </form>
                    @endforeach
                @elseif ($remaining > 0 && $collectBalance && $group->verifiedPaymentsTotal() >= $group->deposit_required)
                    <div class="space-y-3 pt-2">
                        <div class="font-semibold text-primary">Record group payment · <x-amount :value="$remaining" /></div>
                        @include('admin.payments.onsite', ['reservation' => $lines->first(), 'reservationAccepted' => true, 'paymentReady' => true, 'groupDue' => $remaining, 'groupAction' => route('admin.reservation-groups.balance', $group)])
                    </div>
                @elseif ($arrivingTogether && ($remaining <= 0 || ! $collectBalance))
                    <x-admin.confirm-action :action="route('admin.reservation-groups.attendance', [$group, 'check-in'])" tone="primary" label="Check in group" title="Check in all {{ $lines->count() }} services for {{ $group->guest_name }}?" consequence="Confirm once the guests have arrived for all listed services." />
                @else
                    <p>See the arrival schedule for each booked service below.</p>
                @endif
            @endif
        @else
        @foreach ($lines as $line)
            <div class="space-y-2 border-t border-border pt-3">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-semibold text-text">{{ $line->facility->name }}</h3>
                    <x-badge :status="$line->status" :label="$line->frontDeskStatusLabel()" />
                </div>
                @if ($line->checked_in_at)
                    <p class="text-xs text-text-muted">Arrival recorded <x-admin.date-time :value="$line->checked_in_at" /></p>
                @endif
                @if ($line->checked_out_at)
                    <p class="text-xs text-text-muted">Departure recorded <x-admin.date-time :value="$line->checked_out_at" /></p>
                @elseif ($line->isInHouse())
                    <x-admin.confirm-action :action="route('admin.reservations.check-out', $line)" tone="primary" :label="$line->occupancyEnd()->gt($now) ? 'Check out early' : 'Check out guest'" title="Check out {{ $line->guestName() }} from {{ $line->facility->name }}?" confirm="Confirm checkout" :consequence="$line->occupancyEnd()->gt($now) ? 'Confirm the guest is leaving before the scheduled end. This records departure and completes this stay.' : 'Confirm once the guest leaves. This records departure and completes this stay.'" />
                @elseif ($line->status === \App\Enums\ReservationStatus::Approved && ! $discussion)
                    <p class="text-xs text-text-muted">Expected arrival <x-admin.date-time :value="$line->check_in" /></p>
                    @if (! $proof && ($line->deposit_required === null || $line->getVerifiedTotalPaid() >= $line->requiredDepositAmount()))
                        @if ($line->policy_version === config('resort.policy.confirmed_version') && $line->facility->isRoom()
                            && $line->check_in->isSameDay($now) && ! $line->check_in->isSameDay($line->check_out)
                            && \App\Services\ReservationService::earlyArrivalHours($now->diffInMinutes($line->check_in, false)) > $line->early_check_in_hours)
                            <form method="POST" action="{{ route('admin.reservations.discussion', $line) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="review_arrival">
                                <x-admin.button type="submit">Review early check-in charge</x-admin.button>
                            </form>
                        @elseif ($collectBalance && $remaining > 0 && $line->getRemainingBalance() > 0)
                            <section >
                                <div class="px-4 py-3 text-sm font-semibold text-text">Record payment · <x-amount :value="$line->getRemainingBalance()" /></div>
                                @include('admin.payments.onsite', ['reservation' => $line, 'reservationAccepted' => true, 'paymentReady' => true])
                            </section>
                        @elseif ((! $collectBalance || $remaining <= 0) && $line->check_in->toDateString() <= $now->toDateString())
                            <x-admin.confirm-action :action="route('admin.reservations.check-in', $line)" tone="primary" label="Check in guest" title="Check in {{ $line->guestName() }} at {{ $line->facility->name }}?" confirm="Confirm check-in" consequence="Confirm once the guest arrives. This records physical check-in." />
                        @elseif ($remaining > 0)
                            <p>Settle the remaining booking balance before check-in.</p>
                        @endif
                    @elseif (! $proof)
                        <p>Awaiting payment proof</p>
                    @endif
                @endif
            </div>
        @endforeach
        @endif
    @endif
    </div>
</x-admin.panel>
@unless ($record instanceof \App\Models\ReservationGroup)
    @include('admin.reservations.partials.front-desk-adjustments')
@endunless
@if (! ($record instanceof \App\Models\ReservationGroup) && $lines->isNotEmpty() && ($group ?? $record)->policy_version === config('resort.policy.confirmed_version'))
    <div class="mb-5 rounded-xl border border-border bg-surface p-5 shadow-xs space-y-4">
        <h3 class="font-heading text-base font-bold text-text">Cancellation, refund, and rescheduling</h3>
        @include('customer.reservations.partials.booking-discussions', ['reservation' => $lines->first(), 'staffDiscussion' => true])
    </div>
@endif
