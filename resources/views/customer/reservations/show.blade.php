<x-customer-layout>
    @php
        $remaining = $reservation->getRemainingBalance();
        $paymentState = $reservation->guestPaymentState();
        $canPay = $reservation->canAcceptGuestPayment();
        $pendingVerification = $reservation->getPendingVerificationTotal();
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
                <h1 class="font-heading text-4xl md:text-5xl font-semibold text-text">Reservation details</h1>
                <a href="{{ route('customer.reservations.index') }}" class="text-sm text-primary font-medium hover:underline">
                    &larr; Back to my reservations
                </a>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
                <!-- Left Column: Reservation Info & Pricing -->
                <div class="md:col-span-2 space-y-6">
                    @if($reservation->canShowArrivalPass())
                        <div class="bg-primary text-white rounded-2xl shadow-sm px-5 py-5 sm:px-6">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/70">For arrival</p>
                            <h2 class="mt-2 font-heading text-2xl font-semibold">Show this at the front desk</h2>
                            <p class="mt-2 font-mono text-2xl sm:text-3xl font-bold tracking-wide">{{ $reservation->reservation_code }}</p>
                            <p class="mt-2 text-sm text-white/80">You do not need to sign in at the resort. Staff can look this code up, or just the last 6 characters.</p>
                            <a href="{{ $reservation->arrivalPassUrl() }}" class="mt-4 inline-flex items-center justify-center min-h-[44px] px-4 rounded-lg bg-white text-primary text-sm font-semibold hover:bg-white/90">
                                Open arrival pass
                            </a>
                        </div>
                    @endif

                    <!-- Reservation Information -->
                    <div class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
                        <div class="px-5 py-4 sm:px-6 border-b border-border flex justify-between items-center gap-3">
                            <h2 class="font-heading text-2xl font-semibold text-text">
                                {{ $reservation->facility->name }}
                            </h2>
                            <x-badge :status="$reservation->status->label()" />
                        </div>
                        <div class="px-5 sm:px-0 py-2 sm:py-0">
                            <dl class="sm:divide-y sm:divide-border">
                                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm font-medium text-text-muted">Reservation code</dt>
                                    <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2">{{ $reservation->reservation_code }}</dd>
                                </div>
                                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm font-medium text-text-muted">Reservation source</dt>
                                    <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2">{{ $reservation->reservation_source->label() }}</dd>
                                </div>
                                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm font-medium text-text-muted">Created on</dt>
                                    <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2">{{ $reservation->created_at->format('M d, Y g:i A') }}</dd>
                                </div>
                                @if($reservation->swimmingSession)
                                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm font-medium text-text-muted">Swimming session</dt>
                                    <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2">
                                        {{ $reservation->swimmingSession->name }}
                                        <span class="block mt-1 text-text-muted">This reserves the cottage only. Pool entrance is paid separately at the entrance.</span>
                                        @unless($reservation->swimmingSession->children_allowed)
                                            <span class="block mt-1 font-semibold text-red-700">Children are not allowed to swim during this session.</span>
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
                                    <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2">{{ $reservation->late_check_out_hours }} hour(s)</dd>
                                </div>
                                @endif
                            </dl>
                        </div>
                    </div>

                    <div class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
                        <div class="px-5 py-4 sm:px-6 border-b border-border">
                            <h2 class="font-heading text-2xl font-semibold text-text">Guest information sheet</h2>
                            <p class="mt-1 text-sm text-text-muted">The same details collected at the front desk.</p>
                        </div>
                        <div class="px-5 sm:px-0 py-2 sm:py-0">
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
                                        @forelse($reservation->otherGuestNames() as $index => $companion)
                                            <span class="block">{{ $index + 1 }}. {{ $companion }}</span>
                                        @empty
                                            —
                                        @endforelse
                                    </dd>
                                </div>
                                @if($reservation->special_requests)
                                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm font-medium text-text-muted">Preferences & requests</dt>
                                    <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2 whitespace-pre-line">{{ $reservation->special_requests }}</dd>
                                </div>
                                @endif
                            </dl>
                        </div>
                    </div>

                    @if(in_array($reservation->status, [\App\Enums\ReservationStatus::Pending, \App\Enums\ReservationStatus::Approved], true))
                    <div class="bg-surface border border-dashed border-border rounded-2xl p-5 sm:p-6">
                        <p class="text-sm font-semibold text-text">What happens next</p>
                        <ol class="mt-3 space-y-3 text-sm text-text-muted">
                            <li class="flex gap-3"><span class="w-5 h-5 flex-none grid place-items-center rounded-full bg-primary-light text-[11px] font-bold text-primary">1</span>{{ $reservation->status === \App\Enums\ReservationStatus::Approved ? 'Your booking is confirmed. Review your arrival details below.' : ($reservation->deposit_required !== null ? 'Submit your deposit using the resort QR instructions before the deadline.' : 'Wait for the staff quotation and approval before transferring funds.') }}</li>
                            <li class="flex gap-3"><span class="w-5 h-5 flex-none grid place-items-center rounded-full bg-primary-light text-[11px] font-bold text-primary">2</span>Staff verify the deposit and confirm your booking. Pay the remaining reservation balance at arrival.</li>
                            <li class="flex gap-3"><span class="w-5 h-5 flex-none grid place-items-center rounded-full bg-primary-light text-[11px] font-bold text-primary">3</span>Present your reservation code or arrival pass at the front desk.</li>
                        </ol>
                    </div>
                    @endif

                    <!-- Pricing Snapshot -->
                    <div class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
                        <div class="px-5 py-4 sm:px-6 border-b border-border">
                            <h2 class="font-heading text-2xl font-semibold text-text">
                                Pricing
                            </h2>
                        </div>
                        <div class="px-5 sm:px-0 py-2 sm:py-0">
                            <dl class="sm:divide-y sm:divide-border">
                                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm font-medium text-text-muted">Base amount</dt>
                                    <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2">₱{{ number_format($reservation->base_price, 2) }}</dd>
                                </div>
                                @if($reservation->additional_charges > 0)
                                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm font-medium text-text-muted">Additional charges</dt>
                                    <dd class="mt-1 text-sm text-text sm:mt-0 sm:col-span-2">₱{{ number_format($reservation->additional_charges, 2) }}</dd>
                                </div>
                                @endif
                                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 bg-background">
                                    <dt class="text-sm font-bold text-text">Total amount</dt>
                                    <dd class="mt-1 font-heading text-2xl font-semibold text-primary sm:mt-0 sm:col-span-2">₱{{ number_format($reservation->total_price, 2) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Payments & Actions -->
                <div class="space-y-6 sticky top-24 self-start">
                    <!-- Payment Status -->
                    <div class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
                        <div class="px-5 py-4 sm:px-6 border-b border-border">
                            <h2 class="font-heading text-2xl font-semibold text-text">
                                Payment
                            </h2>
                        </div>
                        <div class="px-5 py-5 sm:px-6 space-y-4">
                            <x-payment-overpayment :reservation="$reservation" />
                            @if($reservation->deposit_required !== null)
                                <div class="rounded-lg border border-border p-3 text-sm">
                                    <p>Required deposit (50%): <strong>₱{{ number_format($reservation->requiredDepositAmount(), 2) }}</strong></p>
                                    @if($reservation->status === \App\Enums\ReservationStatus::Pending)
                                        <p>Proof deadline: {{ $reservation->deposit_due_at?->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }} (Philippine time)</p>
                                        @if($pendingVerification > 0)<p>Availability stays held while staff review your timely proof.</p>@endif
                                    @elseif($reservation->status === \App\Enums\ReservationStatus::Expired)
                                        <p>This unpaid hold expired. Contact staff immediately if you transferred funds; do not send another payment.</p>
                                    @endif
                                </div>
                            @endif
                            <div class="flex justify-between items-center border-b border-border pb-3">
                                <span class="text-sm text-text-muted">Total amount</span>
                                <span class="font-medium text-text">₱{{ number_format($reservation->total_price, 2) }}</span>
                            </div>
                            <div class="flex justify-between items-center border-b border-border pb-3">
                                <span class="text-sm text-text-muted">Verified paid</span>
                                <span class="font-medium text-success">₱{{ number_format($reservation->getVerifiedTotalPaid(), 2) }}</span>
                            </div>
                            @if($pendingVerification > 0)
                                <div class="flex justify-between items-center border-b border-border pb-3">
                                    <span class="text-sm text-text-muted">Awaiting verification</span>
                                    <span class="font-medium text-text">₱{{ number_format($pendingVerification, 2) }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-text-muted">Remaining balance</span>
                                @if($remaining > 0)
                                    <span class="font-heading text-2xl font-semibold text-primary">₱{{ number_format($remaining, 2) }}</span>
                                @else
                                    <span class="font-semibold text-success">Fully paid</span>
                                @endif
                            </div>
                        </div>
                        <div class="bg-background px-5 py-4 sm:px-6 border-t border-border">
                            @if($paymentState === \App\Enums\GuestPaymentState::AwaitingApproval)
                                <p class="text-sm text-text-muted text-center">Staff assistance is needed for this request. Check its payment history or contact the resort.</p>
                            @elseif($paymentState === \App\Enums\GuestPaymentState::OnSiteBalance)
                                <p class="text-sm font-semibold">Remaining balance payable at the resort upon arrival.</p>
                                <a class="text-primary underline" href="{{ route('customer.payments.index', $reservation) }}">View payment history</a>
                            @elseif($paymentState === \App\Enums\GuestPaymentState::ReceiptPending)
                                <x-primary-button tag="a" href="{{ route('customer.payments.index', $reservation) }}" class="w-full justify-center !py-3 !text-base">
                                    Receipt awaiting verification
                                </x-primary-button>
                            @elseif($canPay)
                                <x-primary-button tag="a" href="{{ route('customer.payments.index', $reservation) }}" class="w-full justify-center !py-3 !text-base">
                                    {{ config('payments.deposit_workflow') ? 'Submit deposit' : 'Pay balance' }}
                                </x-primary-button>
                            @else
                                <a href="{{ route('customer.payments.index', $reservation) }}" class="block text-center text-sm font-medium text-primary hover:underline">
                                    View payment history &rarr;
                                </a>
                            @endif
                        </div>
                    </div>

                    <!-- Actions -->
                    @include('customer.reservations.partials.review')
                    @inject('reservationService', 'App\Services\Customer\CustomerReservationService')
                    @if($reservation->status === \App\Enums\ReservationStatus::Pending && ($reservationService->canReschedule($reservation) || $reservationService->canRequestCancellation($reservation) || $reservation->cancellation_requested_at))
                        <div class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
                            <div class="px-5 py-4 sm:px-6 border-b border-border">
                                <h2 class="font-heading text-2xl font-semibold text-text">
                                    Change this reservation
                                </h2>
                            </div>
                            <div class="p-5 sm:p-6 flex flex-col space-y-3">
                                @if($reservationService->canReschedule($reservation))
                                    @include('customer.reservations.partials.reschedule-modal')
                                @endif
                                @include('customer.reservations.partials.cancel-modal')
                            </div>
                        </div>
                    @elseif($reservationService->canReschedule($reservation) || $reservationService->canCancel($reservation))
                        <div class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
                            <div class="px-5 py-4 sm:px-6 border-b border-border">
                                <h2 class="font-heading text-2xl font-semibold text-text">
                                    Change this reservation
                                </h2>
                            </div>
                            <div class="p-5 sm:p-6 flex flex-col space-y-3">
                                @if($reservationService->canReschedule($reservation))
                                    @include('customer.reservations.partials.reschedule-modal')
                                @endif

                                @if($reservationService->canCancel($reservation))
                                    @include('customer.reservations.partials.cancel-modal')
                                @endif
                            </div>
                        </div>
                    @elseif(in_array($reservation->status, [\App\Enums\ReservationStatus::Pending, \App\Enums\ReservationStatus::Approved], true))
                        @php
                            $cutoffHours = $reservationService->cancellationCutoffHours();
                            $deadline = $reservationService->selfServiceDeadline($reservation, $cutoffHours);
                        @endphp
                        <div class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
                            <div class="px-5 py-4 sm:px-6 border-b border-border">
                                <h2 class="font-heading text-2xl font-semibold text-text">Change this reservation</h2>
                            </div>
                            <div class="p-5 sm:p-6 space-y-3 text-sm text-text-muted">
                                @if($reservation->deposit_required && ($reservation->getVerifiedTotalPaid() > 0 || $reservation->getPendingVerificationTotal() > 0))
                                    <p>Contact staff to change or cancel this booking so they can reconcile your deposit first.</p>
                                @else
                                <p>
                                    Online changes closed on
                                    <span class="font-semibold text-text">{{ $deadline->format('M d, Y g:i A') }}</span>
                                    ({{ $cutoffHours }} hours before check-in).
                                </p>
                                @endif
                                <p>
                                    To reschedule or cancel now, contact the resort at
                                    <a href="{{ config('resort.phone_href') }}" class="font-medium text-primary hover:underline">{{ config('resort.phone') }}</a>.
                                </p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-customer-layout>
