<x-admin-layout>
    <x-slot name="header">Payment #{{ $payment->id }}</x-slot>

    @php
        $reservation = $payment->reservation;
        $group = $payment->reservationGroup;
        $accountUser = $reservation?->user ?? $group?->user;
        $reservationTotal = $reservation ? $reservation->total_price : ($group ? $group->total_amount : 0);
        $verifiedPaid = $reservation
            ? $reservation->payments->where('status', \App\Enums\PaymentStatus::Verified)->sum('amount')
            : ($group ? $group->verifiedPaymentsTotal() : 0);
        $pendingVerification = $reservation
            ? $reservation->payments->where('status', \App\Enums\PaymentStatus::Pending)->sum('amount')
            : ($group ? $group->pendingPaymentsTotal() : 0);
        $balance = $reservationTotal - $verifiedPaid;
        $balanceIfVerified = max(0, $balance - $pendingVerification);
        $otherPayments = $reservation
            ? $reservation->payments->where('id', '!=', $payment->id)
            : ($group ? $group->directPayments->where('id', '!=', $payment->id) : collect());
        $isPending = $payment->status === \App\Enums\PaymentStatus::Pending;
        $isVerified = $payment->status === \App\Enums\PaymentStatus::Verified;
        $proofViewUrl = route('admin.payments.view', $payment);
        $remaining = $reservation ? $reservation->getRemainingBalance() : ($group ? $group->outstandingBalance() : 0);
        $overpays = (float) $payment->amount > $remaining;
    @endphp

    <x-admin.page-header
        title="Payment #{{ $payment->id }}"
        :breadcrumbs="[
            ['label' => 'Payments', 'url' => route('admin.payments.index')],
            ['label' => 'Payment #'.$payment->id],
        ]"
    >
        <x-slot:actions>
            @if($reservation)
                <x-admin.button variant="secondary" :href="route('admin.reservations.show', $reservation)">Open reservation</x-admin.button>
            @elseif($group)
                <x-admin.button variant="secondary" :href="route('admin.reservation-groups.show', $group)">Open booking group</x-admin.button>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        <div class="order-2 space-y-6 lg:order-1 lg:col-span-2">
            <x-admin.panel title="Proof of payment" description="Compare the amount, reference, and date on this proof against the credit in the resort account.">
                @if ($isPending)
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3 border-b border-border pb-4">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-text">{{ $reservation?->guestName() ?? $group?->guest_name }}</p>
                            <p class="text-xs text-text-muted">{{ $reservation?->facility?->name ?? $group?->reservations->pluck('facility.name')->join(', ') }}</p>
                        </div>
                        <p class="text-sm font-semibold tabular-nums">Review <x-amount :value="$payment->amount" /> payment</p>
                    </div>
                @endif
                @if ($payment->proof_path)
                    <div x-data>
                        @if ($payment->isImageProof())
                            <button type="button" x-ref="previewTrigger" aria-haspopup="dialog" @click="$refs.proofPreview.showModal()" class="block w-full overflow-hidden rounded-lg border border-border bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                <img src="{{ $proofViewUrl }}" alt="Proof of payment for {{ $reservation?->reservation_code ?? ('Group ' . ($group?->group_code ?? 'booking')) }}" class="max-h-[28rem] w-full cursor-zoom-in object-contain">
                            </button>
                            <p class="admin-help">Open the image to enlarge it.</p>
                        @elseif ($payment->isPdfProof())
                            <iframe src="{{ $proofViewUrl }}" title="Proof of payment" class="h-[32rem] w-full rounded-lg border border-border bg-surface"></iframe>
                        @else
                            <p class="text-sm text-text-muted">This file type cannot be previewed here. Open it in a new tab or download it instead.</p>
                        @endif

                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-admin.button :href="$proofViewUrl" target="_blank" rel="noopener">Open proof</x-admin.button>
                            <x-admin.button variant="secondary" :href="route('admin.payments.download', $payment)">Download</x-admin.button>
                        </div>

                        <dialog x-ref="proofPreview" @close="$refs.previewTrigger.focus()" aria-label="Proof of payment enlarged" class="m-auto max-w-[95vw] rounded-lg bg-surface p-4 backdrop:bg-black/70">
                            <div class="max-w-5xl">
                                <x-admin.button type="button" variant="secondary" size="sm" autofocus @click="$refs.proofPreview.close()" class="mb-3">Close preview</x-admin.button>
                                <img src="{{ $proofViewUrl }}" alt="Proof of payment enlarged" class="max-h-[85vh] w-full rounded-lg bg-black object-contain">
                            </div>
                        </dialog>
                    </div>
                @else
                    <x-admin.empty
                        icon="payments"
                        title="No receipt attached"
                        description="This payment was recorded without a proof file, which is normal for cash taken at the front desk. Anything transferred by the guest should have a receipt."
                    />
                @endif
            </x-admin.panel>
            @include('admin.payments.deposit-review')

            <x-admin.panel title="Payment details">
                <x-slot:actions>
                    <x-badge :status="$payment->status" />
                </x-slot:actions>

                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Amount</dt>
                        <dd class="mt-1 text-lg font-semibold text-text"><x-amount :value="$payment->amount" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Method</dt>
                        <dd class="mt-1 text-sm text-text">{{ $payment->method->label() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Reference number</dt>
                        <dd class="mt-1 font-mono text-sm text-text">{{ $payment->reference_number ?: 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Date submitted</dt>
                        <dd class="mt-1"><x-admin.date-time :value="$payment->payment_date" format="date" /></dd>
                    </div>

                    @if ($payment->source === 'onsite')
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Collected at the resort</dt>
                            <dd class="mt-1 text-sm text-text">
                                {{ $payment->collected_at?->copy()->timezone(\App\Support\ResortTime::timezone())->format('M j, Y g:i A') }} Philippine time
                            </dd>
                        </div>
                    @endif

                    @if ($payment->reconciliation_note)
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Reconciliation note</dt>
                            <dd class="mt-1 text-sm text-text">{{ $payment->reconciliation_note }}</dd>
                        </div>
                    @endif

                    @if (! $isPending)
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ $isVerified ? 'Verified' : 'Reviewed' }} by</dt>
                            <dd class="mt-1 text-sm text-text">{{ $payment->verifiedBy?->name ?? 'Payment gateway (automatic)' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ $isVerified ? 'Verified' : 'Reviewed' }} at</dt>
                            <dd class="mt-1"><x-admin.date-time :value="$payment->verified_at" format="datetime" /></dd>
                        </div>
                    @endif

                    @if ($payment->status === \App\Enums\PaymentStatus::Rejected && $payment->rejection_reason)
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Rejection reason</dt>
                            <dd class="mt-1 rounded-lg bg-danger-light p-3 text-sm text-text">{{ $payment->rejection_reason }}</dd>
                        </div>
                    @endif
                </dl>
            </x-admin.panel>


            @if ($reservation)
            <x-admin.panel title="Related reservation">
                <x-slot:actions>
                    <a href="{{ route('admin.reservations.show', $reservation) }}" class="rounded text-sm font-semibold text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                        View reservation
                    </a>
                </x-slot:actions>

                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Reservation code</dt>
                        <dd class="mt-1 font-mono text-sm font-semibold text-text">{{ $reservation->reservation_code }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Status</dt>
                        <dd class="mt-1"><x-badge :status="$reservation->status" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Space</dt>
                        <dd class="mt-1 text-sm text-text">{{ $reservation->facility->name }} <span class="text-text-muted">({{ $reservation->facility->facilityType->name }})</span></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Guests</dt>
                        <dd class="mt-1 text-sm tabular-nums text-text">{{ $reservation->guest_count }} persons</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Stay</dt>
                        <dd class="mt-1"><x-admin.date-range :from="$reservation->check_in" :to="$reservation->check_out" :time="false" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Guest on the account</dt>
                        <dd class="mt-1 text-sm text-text">{{ $reservation->guestName() }}</dd>
                    </div>
                </dl>
            </x-admin.panel>
            @elseif ($group)
            <x-admin.panel title="Related booking group">
                <x-slot:actions>
                    <a href="{{ route('admin.reservation-groups.show', $group) }}" class="rounded text-sm font-semibold text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                        View booking group
                    </a>
                </x-slot:actions>

                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Group code</dt>
                        <dd class="mt-1 font-mono text-sm font-semibold text-text">{{ $group->group_code }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Guest</dt>
                        <dd class="mt-1 text-sm text-text">{{ $group->guest_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Reservations</dt>
                        <dd class="mt-1 text-sm tabular-nums text-text">{{ $group->reservations->count() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Email</dt>
                        <dd class="mt-1 text-sm">
                            @if ($groupEmail = ($group->guest_email ?: $accountUser?->email))
                                <a href="mailto:{{ $groupEmail }}" class="rounded font-medium text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                    {{ $groupEmail }}
                                </a>
                            @else
                                <span class="text-text-muted">Not recorded</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-admin.panel>
            @endif
        </div>

        <div class="order-1 space-y-6 lg:sticky lg:top-24 lg:order-2">
            <x-admin.panel
                title="Payment actions"
                :description="$isPending ? 'This payment is awaiting verification against the receiving account.' : null"
            >
                @if ($isPending)
                    <div class="space-y-3">
                        <x-admin.form-dialog
                            :action="route('admin.payments.verify', $payment)"
                            method="PATCH"
                            label="Verify payment"
                            title="Verify this payment?"
                            :consequence="$payment->source === 'customer_deposit' && ($group?->isPending() ?? ($reservation?->status === \App\Enums\ReservationStatus::Pending)) ? 'Verifying the required deposit also confirms this booking if the booking checks pass.' : 'It counts as paid, the guest is notified, and the remaining balance drops by the amount recorded.'"
                            confirm="Verify payment"
                            tone="primary"
                        >
                            <x-slot:details>
                                <p><span class="font-semibold">Amount:</span> <x-amount :value="$payment->amount" /></p>
                                <p><span class="font-semibold">Method:</span> {{ $payment->method->label() }}</p>
                                <p><span class="font-semibold">Target:</span> {{ $reservation ? $reservation->reservation_code : ($group ? 'Group ' . $group->group_code : 'N/A') }}</p>
                            </x-slot:details>

                            @if ($payment->source === 'customer_deposit')
                                <label class="flex items-start gap-2 text-sm text-text">
                                    <input type="checkbox" name="funds_received" value="1" required
                                           class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-primary">
                                    <span>I matched this transaction and amount against the resort's receiving account. <span class="text-danger" aria-hidden="true">*</span></span>
                                </label>

                                <div>
                                    <label for="verify_overpayment_note" class="admin-label">Duplicate-reference or other reconciliation note</label>
                                    <textarea id="verify_overpayment_note" name="overpayment_note" rows="3" maxlength="500"
                                              class="admin-input mt-1">{{ old('overpayment_note') }}</textarea>
                                </div>
                            @elseif ($overpays)
                                <div class="rounded-lg bg-warning-light p-3">
                                    <label for="overpayment_note" class="block text-sm font-semibold text-text">Overpayment reconciliation note <span class="text-danger" aria-hidden="true">*</span></label>
                                    <p class="mt-1 text-sm text-text">This amount exceeds the remaining balance. Verify only after confirming the funds were received, then record the excess and the follow-up needed.</p>
                                    <textarea id="overpayment_note" name="overpayment_note" required rows="3" maxlength="500" class="admin-input mt-2">{{ old('overpayment_note') }}</textarea>
                                </div>
                            @endif
                        </x-admin.form-dialog>

                        <x-admin.form-dialog
                            :action="route('admin.payments.reject', $payment)"
                            method="PATCH"
                            label="Decline proof"
                            title="Decline this proof?"
                            :consequence="'The proof is marked declined and the guest is told why. The space stays held until a valid payment is verified.'"
                            confirm="Decline proof"
                            tone="danger"
                        >
                            @if ($payment->source === 'customer_deposit')
                                <label class="flex items-start gap-2 text-sm text-text">
                                    <input type="checkbox" name="funds_not_received" value="1" required
                                           class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-primary">
                                    <span>I checked the receiving account and confirmed these funds were not received. If the transfer is uncertain, leave this proof pending and contact the guest instead. <span class="text-danger" aria-hidden="true">*</span></span>
                                </label>
                            @endif

                            <div>
                                <label for="reject_reason" class="admin-label">Why it is being declined <span class="text-danger" aria-hidden="true">*</span></label>
                                <textarea name="reason" id="reject_reason" rows="3" required aria-required="true"
                                          placeholder="e.g. No matching credit in the GCash account for this reference."
                                          class="admin-input mt-1"></textarea>
                                <p class="admin-help">The guest sees this wording, so state what was wrong and what they should send instead.</p>
                            </div>
                        </x-admin.form-dialog>
                    </div>
                @else
                    <p class="text-sm text-text-muted">
                        This payment has been <span class="font-semibold">{{ strtolower($payment->status->label()) }}</span>. No further action is available.
                    </p>
                @endif
            </x-admin.panel>
            <x-admin.panel title="Balance summary">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-text-muted">Reservation total</dt>
                        <dd class="font-medium text-text"><x-amount :value="$reservationTotal" /></dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="font-medium text-success">Verified paid</dt>
                        <dd class="font-semibold text-success"><x-amount :value="$verifiedPaid" /></dd>
                    </div>
                    @if ($pendingVerification > 0)
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="font-medium text-warning">Under review</dt>
                            <dd class="font-semibold text-warning"><x-amount :value="$pendingVerification" /></dd>
                        </div>
                    @endif
                    <div class="flex items-baseline justify-between gap-3 border-t border-border pt-3">
                        <dt class="font-semibold {{ ($pendingVerification > 0 ? $balanceIfVerified : $balance) > 0 ? 'text-danger' : 'text-success' }}">
                            {{ $pendingVerification > 0 ? 'Balance if verified' : 'Remaining' }}
                        </dt>
                        <dd class="font-semibold {{ ($pendingVerification > 0 ? $balanceIfVerified : $balance) > 0 ? 'text-danger' : 'text-success' }}">
                            <x-amount :value="$pendingVerification > 0 ? $balanceIfVerified : max(0, $balance)" />
                        </dd>
                    </div>
                </dl>

                @if ($balance < 0)
                    <div class="mt-4">
                        <x-admin.notice variant="warning" title="This reservation is overpaid" :dismissible="false">
                            Verified payments exceed the charges by <x-amount :value="abs($balance)" />.
                            Record how the excess will be handled before closing this reservation.
                        </x-admin.notice>
                    </div>
                @endif
            </x-admin.panel>

            @if ($otherPayments->isNotEmpty())
                <x-admin.panel title="Other payments on this reservation" :badge="$otherPayments->count()" :padding="false">
                    <ul class="divide-y divide-border">
                        @foreach ($otherPayments as $otherPayment)
                            <li>
                                <a href="{{ route('admin.payments.show', $otherPayment) }}" class="flex items-center justify-between gap-3 px-5 py-3 transition-colors duration-150 hover:bg-primary-light/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-text"><x-amount :value="$otherPayment->amount" /></span>
                                        <span class="block text-xs text-text-muted">
                                            {{ $otherPayment->method->label() }} · <x-admin.date-time :value="$otherPayment->payment_date" format="date" />
                                        </span>
                                    </span>
                                    <x-badge :status="$otherPayment->status" variant="compact" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-admin.panel>
            @endif

        </div>
    </div>
</x-admin-layout>
