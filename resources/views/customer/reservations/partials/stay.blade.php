@inject('reservationService', 'App\Services\Customer\CustomerReservationService')

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
    <!-- What was booked: readable at a glance, checkable in detail -->
    <div class="md:col-span-2 space-y-6">
        {{--
            One card for the stay itself: the dates, the people and the extras a
            guest can act on. Booking metadata (when it was made, by which route)
            is reporting, not a decision, so it is demoted to the footer of the
            same card instead of competing with the check-in row. It used to sit
            above it.
        --}}
        <div class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 sm:px-6 border-b border-border">
                <h2 class="font-heading text-2xl font-semibold text-text">Your stay</h2>
                <p class="mt-1 text-sm text-text-muted">{{ $reservation->facility->name }}</p>
            </div>
            <div class="px-5 sm:px-0 py-2 sm:py-0">
                <dl class="sm:divide-y sm:divide-border">
                    @if($reservation->swimmingSession)
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-text-muted">Swimming session</dt>
                        <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2">
                            {{ $reservation->swimmingSession->name }}
                            <span class="block mt-1 text-text-muted">This reserves the cottage only. Pool entrance is paid separately at the entrance.</span>
                            @unless($reservation->swimmingSession->children_allowed)
                                <span class="block mt-1 font-semibold text-danger">Children are not allowed to swim during this session.</span>
                            @endunless
                        </dd>
                    </div>
                    @endif
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-text-muted">Check-in</dt>
                        <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2">{{ $reservation->check_in->format('M d, Y g:i A') }}</dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-text-muted">Check-out</dt>
                        <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2">{{ $reservation->check_out->format('M d, Y g:i A') }}</dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-text-muted">Guest count</dt>
                        <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2">{{ $reservation->guest_count }}</dd>
                    </div>
                    @if($reservation->early_check_in_hours > 0)
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-text-muted">Early check-in</dt>
                        <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2">{{ $reservation->early_check_in_hours }} hour(s)</dd>
                    </div>
                    @endif
                    @if($reservation->late_check_out_hours > 0)
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-text-muted">Late check-out</dt>
                        <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2">{{ $reservation->late_check_out_hours }} hour(s) · Approved check out {{ $reservation->occupancyEnd()->format('M d, Y g:i A') }}</dd>
                    </div>
                    @endif
                    @if($reservation->hall_extension_charge > 0)
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-text-muted">Hall extension (included in total)</dt>
                        <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2"><x-amount :value="$reservation->hall_extension_charge" /></dd>
                    </div>
                    @endif
                </dl>
                @if($reservation->facility->isFunctionHall())
                    <p class="px-5 py-3 text-sm text-text-muted sm:px-6">Staff begin clearing the venue 30 minutes before your booked event ends.</p>
                @endif
                <p class="px-5 pb-3 text-sm text-text-muted sm:px-6">Check out beyond the booked time may incur an additional fee. Late check out requests require staff approval.</p>
            </div>
            <p class="border-t border-border bg-background px-5 py-3 text-xs text-text-muted sm:px-6">
                Booked {{ $reservation->created_at->format('M d, Y g:i A') }} · {{ $reservation->reservation_source->label() }}
            </p>
        </div>

        {{--
            Reference material, not a decision. The sheet is the same
            information the desk types in on check in, so a guest reads it
            once to check it and never again — it does not deserve a card
            the height of the payment panel. Collapsed by default, still
            one open click away, and it prints open because the markup is
            real markup either way.
        --}}
        <x-content-panel class="group bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
            <x-slot:heading>
                <span>
                    Guest information sheet
                    <span class="mt-1 block font-sans text-sm font-normal text-text-muted">
                        The details the front desk records on check in. Open this to check names, contact number and ID before you travel.
                    </span>
                </span>
                
            </x-slot:heading>
            <div class="px-5 sm:px-0 py-2 sm:py-0 border-t border-border">
                <dl class="sm:divide-y sm:divide-border">
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-text-muted">Name</dt>
                        <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2">{{ $reservation->guestName() }}</dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-text-muted">Address</dt>
                        <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2">{{ $reservation->guest_place_of_origin ?: '—' }}</dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-text-muted">Nationality</dt>
                        <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2">{{ $reservation->guest_nationality ?: '—' }}</dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-text-muted">Company Name/Agency</dt>
                        <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2">{{ $reservation->guest_company ?: '—' }}</dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-text-muted">Room #</dt>
                        <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2">{{ $reservation->facility->name }}</dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-text-muted">Valid ID type</dt>
                        <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2">{{ $reservation->guest_valid_id?->label() ?: '—' }}</dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-text-muted">Gender</dt>
                        <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2">{{ $reservation->guest_gender?->label() ?: '—' }}</dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-text-muted">Contact #</dt>
                        <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2">{{ $reservation->guestPhone() ?: '—' }}</dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-text-muted">Email Add</dt>
                        <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2">{{ $reservation->guestEmail() ?: '—' }}</dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-text-muted">Other Guest Names</dt>
                        <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2">
                            @forelse($reservation->otherGuests() as $index => $companion)
                                <span class="block">
                                    {{ $index + 1 }}. {{ $companion['name'] }}
                                    @if($companion['valid_id_label'])
                                        <span class="text-text-muted">({{ $companion['valid_id_label'] }})</span>
                                    @endif
                                </span>
                            @empty
                                —
                            @endforelse
                        </dd>
                    </div>
                    @if($reservation->reservationGroup?->special_requests)
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-text-muted">Requests for the whole booking</dt>
                        <dd class="mt-1 whitespace-pre-wrap break-words text-sm text-text sm:mt-0 sm:col-span-2">{{ $reservation->reservationGroup->special_requests }}</dd>
                    </div>
                    @endif
                    @if($reservation->special_requests && $reservation->special_requests !== $reservation->reservationGroup?->special_requests)
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-text-muted">Preferences & requests</dt>
                        <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2 whitespace-pre-line">{{ $reservation->special_requests }}</dd>
                    </div>
                    @endif
                </dl>
            </div>
        </x-content-panel>
    </div>

    <!-- Managing the booking, kept apart from reading it -->
    <div class="space-y-6 self-start">
        @include('customer.reservations.partials.late-checkout')
        @include('customer.reservations.partials.review')

        @if($reservation->status === \App\Enums\ReservationStatus::Pending && ($reservationService->canReschedule($reservation) || $reservationService->canRequestCancellation($reservation) || $reservation->cancellation_requested_at))
            <div class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 sm:px-6 border-b border-border">
                    <h2 class="font-heading text-2xl font-semibold text-text">
                        Change this reservation
                    </h2>
                </div>
                <div class="p-5 sm:p-6 flex flex-col space-y-3">
                    @if($reservation->policy_version === config('resort.policy.confirmed_version'))
            <p class="text-sm text-text-muted">Use Booking discussions above for a revised quote and guest acceptance. Physical check-in ends rescheduling eligibility.</p>
        @elseif($reservationService->canReschedule($reservation))
                        @include('customer.reservations.partials.reschedule-modal')
                    @endif
                    @include('customer.reservations.partials.withdraw-modal')
                </div>
            </div>
        @elseif($reservationService->canReschedule($reservation))
            <div class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 sm:px-6 border-b border-border">
                    <h2 class="font-heading text-2xl font-semibold text-text">
                        Change this reservation
                    </h2>
                </div>
                <div class="p-5 sm:p-6 flex flex-col space-y-3">
                    @include('customer.reservations.partials.reschedule-modal')
                </div>
            </div>
        @elseif(in_array($reservation->status, [\App\Enums\ReservationStatus::Pending, \App\Enums\ReservationStatus::Approved], true))
            @php
                $cutoffHours = $reservationService->rescheduleCutoffHours();
                $deadline = $reservationService->selfServiceDeadline($reservation, $cutoffHours);
            @endphp
            <div class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 sm:px-6 border-b border-border">
                    <h2 class="font-heading text-2xl font-semibold text-text">Change this reservation</h2>
                </div>
                <div class="p-5 sm:p-6 text-sm text-text-muted">
                    @if($reservation->deposit_required && ($reservation->getVerifiedTotalPaid() > 0 || $reservation->getPendingVerificationTotal() > 0))
                        <p>
                            Contact staff to change this booking so they can reconcile your deposit first, or call
                            <a href="{{ config('resort.phone_href') }}" class="font-medium text-primary hover:underline">{{ config('resort.phone') }}</a>.
                        </p>
                    @else
                        <p>
                            Online rescheduling closed on
                            <span class="font-semibold text-text">{{ $deadline->format('M d, Y g:i A') }}</span>
                            ({{ $cutoffHours }} hours before check-in). To change this booking now, call
                            <a href="{{ config('resort.phone_href') }}" class="font-medium text-primary hover:underline">{{ config('resort.phone') }}</a>.
                        </p>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
