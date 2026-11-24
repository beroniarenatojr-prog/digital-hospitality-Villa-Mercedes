@php
    $status = $reservation->status;
    $canDecide = $status === \App\Enums\ReservationStatus::Pending;
    $canModify = in_array($status, [\App\Enums\ReservationStatus::Pending, \App\Enums\ReservationStatus::Approved], true);
    $verifiedPaid = $reservation->getVerifiedTotalPaid();
    $pendingVerification = $reservation->getPendingVerificationTotal();
    $balance = $reservation->getRemainingBalance();
    $refundStatus = ($reservation->reservationGroup?->booking_workflow ?? $reservation->booking_workflow ?? [])['refund']['status'] ?? null;
    $refundApproved = in_array($refundStatus, ['approved', 'returned'], true);
    $balanceIfVerified = max(0, $balance - $pendingVerification);
    $pendingProof = $reservation->payments
        ->first(fn ($payment) => $payment->status === \App\Enums\PaymentStatus::Pending);
    $needsDepositReconciliation = $reservation->deposit_required !== null
        && $pendingVerification > 0;
    $reservationAccepted = in_array($status, [\App\Enums\ReservationStatus::Approved, \App\Enums\ReservationStatus::Completed], true);
    $paymentReady = $reservation->deposit_required === null
        || $verifiedPaid >= $reservation->requiredDepositAmount();
    $arrivalStarted = $reservation->checked_in_at !== null || $status === \App\Enums\ReservationStatus::Completed;
    $discussionHold = isset(($reservation->reservationGroup?->booking_workflow ?? $reservation->booking_workflow ?? [])['refund'])
        || (($reservation->reservationGroup?->booking_workflow ?? $reservation->booking_workflow ?? [])['child_charge']['status'] ?? null) === 'disputed';
    $arrivalPreparation = $reservationAccepted && $paymentReady && ! $arrivalStarted && ! $discussionHold;
    // "as a online booking" reads as a typo; the phrase carries the article so
    // the sentence stays grammatical for both sources.
    $sourcePhrase = $reservation->reservation_source === \App\Enums\ReservationSource::Online ? 'an online' : 'a walk-in';
@endphp

<x-admin-layout>
    {{-- The code short enough to sit in a tab without wrapping names this record
         in all three places it is named: the top bar, the trail below, and the
         heading that gives the page an anchor. --}}
    <x-slot name="header">{{ $reservation->reservation_code }}</x-slot>

    <x-admin.page-header
        title="{{ $reservation->reservation_code }}"
        :breadcrumbs="[
            ['label' => 'Reservations', 'url' => route('admin.reservations.index')],
            ['label' => $reservation->reservation_code],
        ]"
    >
        <x-slot:actions>
            <x-badge :status="$status" />
        </x-slot:actions>
    </x-admin.page-header>
    @include('admin.reservations.partials.current-task', ['record' => $reservation])

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-admin.panel title="Guest Information Sheet">
                @if ($reservation->reservation_group_id && $reservation->reservationGroup)
                    <div class="mb-4 p-3 bg-muted/40 rounded-lg flex items-center justify-between">
                        <div>
                            <span class="text-xs font-semibold uppercase text-text-muted">Part of Group Request</span>
                            <p class="font-bold text-sm text-text">{{ $reservation->reservationGroup->group_code }}</p>
                        </div>
                        <a href="{{ route('admin.reservation-groups.show', $reservation->reservationGroup) }}" class="text-xs font-semibold text-primary hover:underline">
                            View Entire Group &rarr;
                        </a>
                    </div>
                @endif
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Filed on</dt>
                        <dd class="mt-1 text-sm"><x-admin.date-time :value="$reservation->reservation_date ?? $reservation->created_at" format="day" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Name</dt>
                        <dd class="mt-1 text-sm font-semibold text-text">{{ $reservation->guestName() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Contact number</dt>
                        <dd class="mt-1 text-sm tabular-nums">{{ $reservation->guestPhone() ?? 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Email</dt>
                        <dd class="mt-1 text-sm">
                            @if ($reservation->guestEmail())
                                <a href="mailto:{{ $reservation->guestEmail() }}" class="rounded text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">{{ $reservation->guestEmail() }}</a>
                            @else
                                <span class="text-text-muted">Not provided</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Address</dt>
                        <dd class="mt-1 text-sm">{{ $reservation->guest_place_of_origin ?: 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Nationality</dt>
                        <dd class="mt-1 text-sm">{{ $reservation->guest_nationality ?: 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Gender</dt>
                        <dd class="mt-1 text-sm">{{ $reservation->guest_gender?->label() ?? 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">ID presented</dt>
                        <dd class="mt-1 text-sm">{{ $reservation->guest_valid_id?->label() ?? 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Company or agency</dt>
                        <dd class="mt-1 text-sm">{{ $reservation->guest_company ?: 'None given' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Encoded by</dt>
                        <dd class="mt-1 text-sm">{{ $reservation->user->name }} ({{ $reservation->reservation_source->label() }})</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Other guest names</dt>
                        <dd class="mt-1 text-sm">
                            @if (count($reservation->otherGuests()) > 0)
                                <ol class="list-inside list-decimal space-y-1">
                                    @foreach ($reservation->otherGuests() as $companion)
                                        <li>
                                            {{ $companion['name'] }}
                                            @if ($companion['valid_id_label'])
                                                <span class="text-text-muted">— {{ $companion['valid_id_label'] }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ol>
                            @else
                                <span class="text-text-muted">None listed</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-admin.panel>

            <x-admin.panel title="Reservation Details">
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Space</dt>
                        <dd class="mt-1 text-sm font-medium">
                            @if ($reservation->facility->trashed())
                                <span>{{ $reservation->facility->name }} (archived)</span>
                            @else
                                <a href="{{ route('admin.facilities.show', $reservation->facility) }}" class="rounded text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">{{ $reservation->facility->name }}</a>
                            @endif
                            <span class="text-text-muted">({{ $reservation->facility->facilityType->name }})</span>
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Stay</dt>
                        <dd class="mt-1 text-sm font-medium">
                            <x-admin.date-range :from="$reservation->check_in" :to="$reservation->check_out" />
                        </dd>
                        <dd class="mt-1 text-xs text-text-muted">
                            Space is held from <x-admin.date-time :value="$reservation->occupancyStart()" /> so early check ins and late exits cannot double-book it.
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Guests</dt>
                        <dd class="mt-1 text-sm tabular-nums">{{ $reservation->guest_count }} persons</dd>
                    </div>
                    @if ($reservation->early_check_in_hours > 0)
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Early check-in</dt>
                            <dd class="mt-1 text-sm tabular-nums">{{ $reservation->early_check_in_hours }} hour(s)</dd>
                        </div>
                    @endif
                    @if ($reservation->late_check_out_hours > 0)
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Late check-out</dt>
                            <dd class="mt-1 text-sm tabular-nums">{{ $reservation->late_check_out_hours }} hour(s)</dd>
                        </div>
                    @endif
                    @if ($reservation->policies_accepted_at)
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Policies acknowledged</dt>
                            <dd class="mt-1 text-sm"><x-admin.date-time :value="$reservation->policies_accepted_at" /></dd>
                        </div>
                    @endif
                    @if ($reservation->reservationGroup?->special_requests)
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Requests for the whole booking</dt>
                            <dd class="mt-1 whitespace-pre-wrap break-words rounded-lg bg-muted p-3 text-sm">{{ $reservation->reservationGroup->special_requests }}</dd>
                        </div>
                    @endif
                    @if ($reservation->special_requests && $reservation->special_requests !== $reservation->reservationGroup?->special_requests)
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Special requests</dt>
                            <dd class="mt-1 whitespace-pre-line rounded-lg bg-muted p-3 text-sm">{{ $reservation->special_requests }}</dd>
                        </div>
                    @endif
                </dl>
            </x-admin.panel>

            @include('admin.reservations.late-checkout')


            <x-admin.panel
                title="Payment summary"
                description="The prices agreed when this booking was filed. Pending proofs stay visible while the receiving account is checked. Only verified funds count as paid."
            >
                <x-slot:actions>
                    @if($pendingProof)
                        <a href="{{ route('admin.payments.show', $pendingProof) }}" class="rounded text-sm font-semibold text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                            Review pending proof
                        </a>
                    @else
                        <a href="{{ route('admin.payments.index', ['search' => $reservation->reservation_code]) }}" class="rounded text-sm font-semibold text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                            Open payment history
                        </a>
                    @endif
                </x-slot:actions>

                <dl class="space-y-3">
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <dt class="text-text-muted">Base price</dt>
                        <dd class="font-medium"><x-amount :value="$reservation->base_price" /></dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <dt class="text-text-muted">Other charges (early, late, extra person)</dt>
                        <dd class="font-medium"><x-amount :value="$reservation->additional_charges - $reservation->hall_extension_charge" /></dd>
                    </div>
                    @if($reservation->hall_extension_charge > 0)
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <dt class="text-text-muted">Hall extension</dt>
                            <dd class="font-medium"><x-amount :value="$reservation->hall_extension_charge" /></dd>
                        </div>
                    @endif
                    <div class="flex items-baseline justify-between gap-3 border-t border-border pt-3 text-sm">
                        <dt class="text-text-muted">Reservation value</dt>
                        <dd class="font-medium"><x-amount :value="$reservation->total_price" /></dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <dt class="text-text-muted">Verified paid</dt>
                        <dd class="font-semibold text-success"><x-amount :value="$verifiedPaid" /></dd>
                    </div>
                    @if($pendingVerification > 0)
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <dt class="font-medium text-warning">Under review</dt>
                            <dd class="font-semibold text-warning"><x-amount :value="$pendingVerification" /></dd>
                        </div>
                    @endif
                    <div class="flex items-baseline justify-between gap-3 border-t border-border pt-3 text-base font-semibold {{ $balance > 0 ? 'text-danger' : 'text-success' }}">
                        <dt>{{ $refundApproved ? 'Cancelled — no check in balance collectible' : ($pendingVerification > 0 ? 'Balance if verified' : ($balance > 0 ? 'Still to collect' : 'Settled in full')) }}</dt>
                        <dd><x-amount :value="$pendingVerification > 0 ? $balanceIfVerified : $balance" /></dd>
                    </div>
                </dl>
            </x-admin.panel>

            <x-payment-overpayment :reservation="$reservation" :staff="true" />

            @if ($reservation->activities && $reservation->activities->count() > 0)
                <x-admin.panel title="Recent activity" description="The last five changes to this reservation." :padding="false">
                    <ul class="divide-y divide-border">
                        @foreach ($reservation->activities->take(5) as $activity)
                            <li class="flex items-start gap-3 px-5 py-3">
                                <svg class="mt-0.5 h-5 w-5 shrink-0 text-text-muted" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div class="min-w-0">
                                    <p class="text-sm">{{ $activity->description }}</p>
                                    <p class="mt-0.5 text-xs text-text-muted">
                                        <x-admin.date-time :value="$activity->created_at" /> by {{ $activity->causer->name ?? 'System' }}
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-admin.panel>
            @endif
        </div>

        <div class="space-y-6 lg:sticky lg:top-24" x-data="{ action: null }">

            @if ($canDecide && ! $discussionHold)
                <x-admin.panel
                    id="booking-decision" title="Decision"
                    description="{{ $reservation->policy_version === config('resort.policy.confirmed_version')
                        ? 'Verify at least the 50% down payment before approval. Recheck availability and resolve guest discussions; remaining balance is due at physical check-in.'
                        : ($reservation->deposit_required !== null
                        ? 'Approving emails the guest that the 50% QR deposit is open. They pay it by the deadline approval sets, and the transfer is verified in Payment review afterwards.'
                        : 'This request needs a staff quotation and approval.') }}"
                >
                    @if ($reservation->cancellation_requested_at)
                        <div class="mb-4 rounded-lg border border-warning/40 bg-warning-light p-3 text-sm">
                            <p class="font-semibold">Guest requested a withdrawal</p>
                            <p class="mt-1"><x-admin.date-time :value="$reservation->cancellation_requested_at" /></p>
                            @if ($reservation->cancellation_request_reason)
                                <p class="mt-1">{{ $reservation->cancellation_request_reason }}</p>
                            @endif
                        </div>

                        <x-admin.confirm-action
                            :action="route('admin.reservations.confirm-withdrawal', $reservation)"
                            label="Confirm the withdrawal"
                            title="Withdraw {{ $reservation->reservation_code }}?"
                            confirm="Withdraw the reservation"
                            :consequence="'The guest asked to withdraw. Confirming releases ' . $reservation->facility->name . ' for the booked dates and notifies them.'"
                            class="mb-3"
                        />
                    @endif

                    <div class="space-y-3">
                        <x-admin.confirm-action
                            :action="route('admin.reservations.approve', $reservation)"
                            label="Approve reservation"
                            title="Approve {{ $reservation->reservation_code }}?"
                            confirm="Approve and notify guest"
                            tone="primary"
                            :consequence="$reservation->policy_version === config('resort.policy.confirmed_version')
                                ? 'Approval requires a verified 50% down payment and a fresh availability check. The guest is notified of confirmation; any remaining balance is due at physical check-in.'
                                : ($reservation->deposit_required !== null
                                ? 'The guest is emailed that the booking is confirmed and that the 50% deposit is open. ' . $reservation->facility->name . ' stays held for them until that deposit deadline, which approval sets.'
                                : 'The guest is notified that the booking is confirmed, and ' . $reservation->facility->name . ' stays held for ' . $reservation->check_in->format('M j, Y') . '.')"
                        />

                        <x-admin.form-action
                            :action="route('admin.reservations.reject', $reservation)"
                            label="Decline reservation"
                            title="Decline {{ $reservation->reservation_code }}?"
                            confirm="Decline and notify guest"
                            reason-label="Why is it declined?"
                            reason-placeholder="For example: the space is already committed to another booking on these dates."
                            :consequence="'Declining releases the space and tells the guest why the request was not accepted. This cannot be undone from here.'"
                        />
                    </div>
                </x-admin.panel>
            @endif

            @if ($canModify && $reservation->policy_version !== config('resort.policy.confirmed_version'))
                <x-admin.panel title="Modify">
                    <div class="space-y-3">
                        @if ($needsDepositReconciliation)
                            <p class="rounded-lg bg-muted p-3 text-sm text-text-muted">
                                Reconcile the deposit or the expired hold before arranging a changed booking.
                            </p>
                        @else
                            <x-admin.button variant="secondary" class="w-full" @click="action = 'reschedule'">Reschedule</x-admin.button>
                        @endif

                        @if ($status === \App\Enums\ReservationStatus::Approved)
                            @unless (config('payments.deposit_workflow'))
                                <x-admin.confirm-action
                                    :action="route('admin.reservations.complete', $reservation)"
                                    label="Mark as completed"
                                    title="Complete {{ $reservation->reservation_code }}?"
                                    confirm="Complete reservation"
                                    tone="primary"
                                    :consequence="'Completing this reservation releases ' . $reservation->facility->name . ' for the booked period so it can be sold again.'"
                                />
                            @endunless

                            <x-admin.form-action
                                :action="route('admin.reservations.cancel', $reservation)"
                                label="Cancel reservation"
                                title="Cancel {{ $reservation->reservation_code }}?"
                                confirm="Cancel reservation"
                                reason-label="Why is it cancelled?"
                                :reason-required="false"
                                reason-placeholder="For example: the guest called to move their stay to another month."
                                :consequence="'Cancelling releases the space and notifies the guest. This cannot be undone from here.'"
                            />
                        @endif
                    </div>
                </x-admin.panel>
            @endif

            @if ($arrivalPreparation)
                <x-admin.panel title="Front desk">
                    <div class="space-y-3">
                        @include('admin.reservations.partials.guest-sheet-actions')
                        <p class="text-xs text-text-muted">Download the one-page PDF for filing, or open the printable copy in a new tab.</p>
                    </div>
                </x-admin.panel>
            @endif

            {{-- Reschedule stays a two-step dialog because the new price has to be
                 seen and accepted before the change is applied. --}}
            <div
                x-cloak
                x-show="action === 'reschedule'"
                x-transition.opacity.duration.150ms
                class="fixed inset-0 z-50 flex items-end justify-center bg-primary/50 p-4 sm:items-center"
                @click.self="action = null"
                @keydown.escape.window="action = null"
            >
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="reschedule-title"
                    class="relative w-full max-w-lg overflow-y-auto rounded-xl border border-border bg-surface p-5 shadow-xl"
                    style="max-height: 90vh;"
                    x-data="{
                        step: 1,
                        loading: false,
                        error: null,
                        previewData: null,
                        cottageIds: @js($facilities->filter(fn ($facility) => $facility->isCottage())->pluck('id')->values()),
                        get isCottage() { return this.cottageIds.includes(Number(this.form.facility_id)); },
                        form: {
                            facility_id: '{{ $reservation->facility_id }}',
                            check_in: '{{ $reservation->check_in->format('Y-m-d\TH:i') }}',
                            check_out: '{{ $reservation->check_out->format('Y-m-d\TH:i') }}',
                            visit_date: '{{ $reservation->check_in->format('Y-m-d') }}',
                            swimming_session_id: '{{ $reservation->swimming_session_id }}',
                            guestCount: {{ $reservation->guest_count }},
                            early_check_in_hours: {{ $reservation->early_check_in_hours }},
                            late_check_out_hours: {{ $reservation->late_check_out_hours }}
                        },
                        async calculate() {
                            this.loading = true;
                            this.error = null;
                            try {
                                const res = await fetch('{{ route('admin.reservations.calculate-reschedule', $reservation) }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                        'Accept': 'application/json'
                                    },
                                    body: JSON.stringify(this.form)
                                });
                                const data = await res.json();
                                if (! res.ok) throw new Error(data.message || 'Validation failed');

                                this.previewData = data;
                                this.step = 2;
                            } catch (e) {
                                this.error = e.message;
                            } finally {
                                this.loading = false;
                            }
                        }
                    }"
                >
                    <h2 id="reschedule-title" class="text-base font-semibold text-text">Reschedule this reservation</h2>
                    <p class="mt-1 text-sm text-text-muted">Preview the new price and availability before anything is saved.</p>

                    <div x-show="step === 1" class="mt-4 space-y-4">
                        <p x-show="error" x-text="error" class="rounded-lg bg-danger-light p-3 text-sm text-danger" role="alert"></p>

                        <div>
                            <label for="reschedule_facility" class="admin-label">Space</label>
                            <select id="reschedule_facility" x-model="form.facility_id" class="admin-input mt-1">
                                @foreach ($facilities as $fac)
                                    <option value="{{ $fac->id }}">{{ $fac->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div x-show="isCottage" class="space-y-4">
                            <div>
                                <label for="reschedule_visit_date" class="admin-label">Visit date</label>
                                <input id="reschedule_visit_date" type="date" x-model="form.visit_date" class="admin-input mt-1">
                            </div>
                            <div>
                                <label for="reschedule_session" class="admin-label">Swimming session</label>
                                <select id="reschedule_session" x-model="form.swimming_session_id" class="admin-input mt-1">
                                    <option value="">Choose a session</option>
                                    @foreach ($swimmingSessions as $session)
                                        <option value="{{ $session->id }}">
                                            {{-- vocabulary-guard: an <option> label holds text only, so the fee cannot be the money component here; it is formatted with the same two decimals and thousands separator. --}}
                                            {{ $session->name }} - {{ \Carbon\Carbon::parse($session->starts_at)->format('g:i A') }} to {{ \Carbon\Carbon::parse($session->ends_at)->format('g:i A') }} - ₱{{ number_format($session->cottage_fee, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="admin-help">The session sets the cottage hours and fee.</p>
                            </div>
                        </div>

                        <div x-show="! isCottage" class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="reschedule_check_in" class="admin-label">Check-in</label>
                                <input id="reschedule_check_in" type="datetime-local" x-model="form.check_in" class="admin-input mt-1">
                            </div>
                            <div>
                                <label for="reschedule_check_out" class="admin-label">Check-out</label>
                                <input id="reschedule_check_out" type="datetime-local" x-model="form.check_out" class="admin-input mt-1">
                            </div>
                        </div>

                        <div>
                            <label for="reschedule_guests" class="admin-label">Guests</label>
                            <input id="reschedule_guests" type="number" x-model="form.guestCount" min="1" class="admin-input mt-1">
                        </div>

                        <div x-show="! isCottage" class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="reschedule_early" class="admin-label">Early check-in (hours)</label>
                                <input id="reschedule_early" type="number" x-model.number="form.early_check_in_hours" min="0" max="12" class="admin-input mt-1">
                            </div>
                            <div>
                                <label for="reschedule_late" class="admin-label">Late check-out (hours)</label>
                                <input id="reschedule_late" type="number" x-model.number="form.late_check_out_hours" min="0" max="12" class="admin-input mt-1">
                            </div>
                        </div>

                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <x-admin.button variant="secondary" @click="action = null">Cancel</x-admin.button>
                            <x-admin.button variant="primary" @click="calculate()" x-bind:disabled="loading">
                                <span x-show="loading" x-cloak class="flex items-center gap-2">
                                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Checking availability
                                </span>
                                <span x-show="! loading">Preview changes</span>
                            </x-admin.button>
                        </div>
                    </div>

                    <div x-cloak x-show="step === 2" class="mt-4">
                        <div class="rounded-lg bg-muted p-4">
                            <h3 class="text-sm font-semibold">New reservation preview</h3>
                            <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                                <dt class="text-text-muted">Space</dt>
                                <dd x-text="previewData?.facility_name"></dd>
                                <dt class="text-text-muted">Stay</dt>
                                <dd x-text="previewData?.dates"></dd>
                                <dt class="text-text-muted">Guests</dt>
                                <dd x-text="previewData?.pax + ' persons'"></dd>
                                <dt class="font-semibold">New total</dt>
                                <dd class="font-semibold tabular-nums" x-text="pesos(previewData?.breakdown?.total_amount)"></dd>
                            </dl>
                        </div>

                        <div class="mt-3">
                            <x-admin.notice variant="warning" title="Nothing is saved until you confirm">
                                Availability and pricing are checked again when you submit. If the space has been taken since, the change is refused and the original booking is left untouched.
                            </x-admin.notice>
                        </div>

                        <form method="POST" action="{{ route('admin.reservations.reschedule', $reservation) }}" class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="facility_id" :value="form.facility_id">
                            <input type="hidden" name="visit_date" :value="isCottage ? form.visit_date : ''">
                            <input type="hidden" name="swimming_session_id" :value="isCottage ? form.swimming_session_id : ''">
                            <input type="hidden" name="check_in" :value="form.check_in">
                            <input type="hidden" name="check_out" :value="form.check_out">
                            <input type="hidden" name="guestCount" :value="form.guestCount">
                            <input type="hidden" name="early_check_in_hours" :value="form.early_check_in_hours">
                            <input type="hidden" name="late_check_out_hours" :value="form.late_check_out_hours">

                            <x-admin.button variant="secondary" @click="step = 1">Back and change</x-admin.button>
                            <x-admin.button type="submit" variant="primary">Confirm reschedule</x-admin.button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
