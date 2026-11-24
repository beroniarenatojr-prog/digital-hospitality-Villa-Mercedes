<x-admin-layout>
    <x-slot name="header">Group {{ $group->group_code }}</x-slot>

    <x-admin.page-header
        title="Group {{ $group->group_code }}"
        :breadcrumbs="[
            ['label' => 'Reservations', 'url' => route('admin.reservations.index')],
            ['label' => 'Group ' . $group->group_code],
        ]"
    >
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.reservation-groups.print', $group) }}" target="_blank" class="px-3 py-1.5 rounded-lg border border-border text-xs font-semibold text-text hover:bg-muted inline-flex items-center gap-1.5 shadow-xs">
                    <svg class="w-3.5 h-3.5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Print Summary
                </a>
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-{{ $group->status->tone() }}-light text-{{ $group->status->tone() }}">
                    {{ $group->status->label() }}
                </span>
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-muted text-text-muted">
                    {{ $group->source->label() }}
                </span>
            </div>
        </x-slot:actions>
    </x-admin.page-header>

    @php
        $arrivalStarted = $group->reservations->contains(fn ($line) => $line->checked_in_at !== null || $line->checked_out_at !== null);
        $showBookingChanges = ! $arrivalStarted
            && $group->reservations->isNotEmpty()
            && $group->policy_version === config('resort.policy.confirmed_version');
        $showAdjustments = $group->reservations->contains(fn ($line) => $line->status === \App\Enums\ReservationStatus::Approved && ! $line->checked_in_at)
            && ! isset($group->booking_workflow['refund']);
    @endphp

    <nav aria-label="On this page" class="mb-5 flex flex-wrap gap-2 text-sm">
        @foreach ([
            'group-services' => 'Services',
            'group-payments' => 'Payments',
            'refund-flow' => $showBookingChanges ? 'Cancellation & refund' : null,
            'group-reschedule' => $showBookingChanges ? 'Reschedule' : null,
            'front-desk-adjustments' => $showAdjustments ? 'Front desk adjustments' : null,
        ] as $section => $label)
            @if ($label)
                <a href="#{{ $section }}" class="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-3 font-medium text-primary hover:bg-muted">{{ $label }}</a>
            @endif
        @endforeach
    </nav>

    <div class="grid items-start gap-6 pb-20 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2 lg:col-start-1 lg:row-start-1">

        {{-- Guest Profile & Primary Decisions Bar --}}
        <div id="booking-decision" class="p-6 rounded-xl border border-border bg-surface shadow-xs space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <span class="text-xs font-bold uppercase tracking-wider text-primary">Lead Guest Information</span>
                @if($group->reservations->isNotEmpty())
                    <p class="text-xs font-medium text-text-muted">One booking for all selected services</p>
                @endif
            </div>

            <div class="flex flex-wrap items-center justify-between gap-6">
                <div class="space-y-1">
                    <h2 class="text-xl font-bold text-text">{{ $group->guest_name }}</h2>
                    <p class="text-sm text-text-muted">
                        {{ $group->guest_email }} · {{ $group->guest_phone }}
                        @if($group->guest_place_of_origin) · {{ $group->guest_place_of_origin }} @endif
                    </p>
                    @if($group->reservations->isNotEmpty())
                        <p class="pt-2 text-sm font-semibold text-text">
                            {{ $group->earliestCheckIn()?->format('F d, Y g:i A') }} &ndash; {{ $group->latestCheckOut()?->format('F d, Y g:i A') }}
                        </p>
                    @endif
                </div>

                @if($group->isPending())
                    <div class="flex items-center gap-3">
                        @php
                            $canApproveGroup = $group->source !== \App\Enums\ReservationSource::Online
                                || (! $group->requires_quote && $group->verifiedPaymentsTotal() >= (float) $group->deposit_required);
                        @endphp
                        @if($canApproveGroup)
                        <form method="POST" action="{{ route('admin.reservation-groups.approve', $group) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" @disabled(! $canApproveGroup) class="px-5 py-2.5 rounded-lg bg-success text-white font-semibold hover:bg-success/90 transition shadow-sm text-sm disabled:opacity-50" title="{{ $canApproveGroup ? 'Approve this group' : 'A quote and verified 50% deposit are required before approval' }}">
                                Approve Entire Group
                            </button>
                        </form>
                        @endif

                        <button type="button" onclick="document.getElementById('reject-dialog').classList.remove('hidden')" class="px-4 py-2.5 rounded-lg border border-danger text-danger font-semibold hover:bg-danger-light transition text-sm">
                            Decline Request
                        </button>
                    </div>
                @endif
            </div>
        </div>

        {{-- Quotation Request Panel if group requires a quote --}}
        @if($group->requires_quote || $group->quote_requested_at)
            <div id="booking-quote" class="rounded-xl border {{ $group->quoted_at ? 'border-border bg-surface' : 'border-primary/40 bg-primary/5' }} p-6 shadow-xs">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase {{ $group->quoted_at ? 'bg-success-light text-success' : 'bg-primary/20 text-primary' }}">
                                {{ $group->quoted_at ? 'Quote Provided' : 'Quotation Required' }}
                            </span>
                            @if($group->quote_requested_at)
                                <span class="text-xs text-text-muted">Requested {{ $group->quote_requested_at->format('M d, Y g:i A') }}</span>
                            @endif
                        </div>
                        <h3 class="font-heading text-lg font-bold text-text mt-1">Function Hall / Event Quotation</h3>
                        <p class="text-xs text-text-muted mt-0.5">
                            @if($group->quoted_at)
                                Staff supplied a total quotation of <strong class="text-text"><x-amount :value="$group->total_amount" /></strong> on {{ $group->quoted_at->format('M d, Y g:i A') }}.
                            @else
                                Guest requested an official rate quote for this group event. Review the requirements and enter the finalized total below.
                            @endif
                        </p>
                    </div>

                    @if(! $group->quoted_at && $group->isPending())
                        <form method="POST" action="{{ route('admin.reservation-groups.quote', $group) }}" class="flex flex-wrap items-center gap-3">
                            @csrf
                            @method('PATCH')
                            <div>
                                <label class="block text-[11px] font-bold text-text-muted uppercase">Total Quote (₱)</label>
                                <input type="number" step="0.01" min="0" name="quoted_total" required placeholder="0.00" class="admin-input text-sm font-bold w-36">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-text-muted uppercase">Notes (optional)</label>
                                <input type="text" name="notes" placeholder="Inclusions, terms…" class="admin-input text-xs w-48">
                            </div>
                            <button type="submit" class="mt-4 px-4 py-2 rounded-lg bg-primary text-white font-semibold hover:bg-primary-hover text-xs shadow-xs">
                                Submit Quote
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        {{-- Decline / Full Refund Information if Rejected --}}
        @if($group->isRejected())
            <div class="rounded-xl border border-danger/30 bg-danger/5 p-6 shadow-xs space-y-2">
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-danger-light text-danger">Declined</span>
                    <span class="text-xs text-text-muted">Declined on {{ $group->rejected_at?->format('M d, Y g:i A') }}</span>
                </div>
                <p class="text-sm font-semibold text-text">Reason: <span class="font-normal text-text-muted">{{ $group->rejection_reason ?? 'No reason recorded' }}</span></p>
                @if($group->refund_status)
                    <div class="mt-3 pt-3 border-t border-danger/20 flex flex-wrap items-center justify-between gap-4 text-xs">
                        <div>
                            <span class="font-bold text-text">Full Refund Status: <span class="uppercase text-primary">{{ $group->refund_status }}</span></span>
                            <p class="text-text-muted mt-0.5">Amount: <x-amount :value="$group->refund_amount" /> · Notes: {{ $group->refund_notes }}</p>
                        </div>
                        @if($group->refunded_at)
                            <span class="text-text-muted">Recorded: {{ $group->refunded_at->format('M d, Y g:i A') }}</span>
                        @endif
                    </div>
                @endif
                @if($group->refund_status === 'approved' && $group->pendingPaymentsTotal() == 0 && $group->verifiedPaymentsTotal() > 0)
                    <form method="POST" action="{{ route('admin.reservation-groups.refund', $group) }}" class="mt-4 grid gap-3 border-t border-border pt-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                        @csrf @method('PATCH')
                        <label class="text-xs font-semibold">Verified amount actually returned<input type="number" name="refund_amount" min="0.01" step="0.01" value="{{ $group->verifiedPaymentsTotal() }}" required class="admin-input mt-1 w-full"></label>
                        <label class="text-xs font-semibold">Refund transfer reference<input name="refund_reference" maxlength="255" required class="admin-input mt-1 w-full"></label>
                        <button type="submit" class="rounded-lg bg-primary px-4 py-2.5 text-xs font-semibold text-white">Record full refund</button>
                    </form>
                @endif
            </div>
        @endif
        {{-- Financial Metrics Bar --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="p-5 rounded-xl border border-border bg-surface shadow-xs">
                <span class="text-xs font-bold uppercase tracking-wider text-text-muted">Total Group Quoted</span>
                <p class="text-2xl font-extrabold text-primary mt-1"><x-amount :value="$group->total_amount" /></p>
                <p class="text-xs text-text-muted mt-1">{{ $group->reservations->count() }} services combined</p>
            </div>
            <div class="p-5 rounded-xl border border-border bg-surface shadow-xs">
                <span class="text-xs font-bold uppercase tracking-wider text-text-muted">Required 50% Deposit</span>
                <p class="text-2xl font-extrabold text-text mt-1"><x-amount :value="$group->deposit_required ?? ($group->total_amount / 2)" /></p>
                @if($group->deposit_due_at)
                    <p class="text-xs text-text-muted mt-1">Due: {{ $group->deposit_due_at->format('M d, Y g:i A') }}</p>
                @endif
            </div>
            <div class="p-5 rounded-xl border border-border bg-surface shadow-xs">
                <span class="text-xs font-bold uppercase tracking-wider text-text-muted">Verified Payments</span>
                <p class="text-2xl font-extrabold text-success mt-1"><x-amount :value="$group->verifiedPaymentsTotal()" /></p>
                <p class="text-xs text-text-muted mt-1">Received for this booking</p>
            </div>
            <div class="p-5 rounded-xl border border-border bg-surface shadow-xs">
                <span class="text-xs font-bold uppercase tracking-wider text-text-muted">Remaining Balance</span>
                <p class="text-2xl font-extrabold text-text mt-1"><x-amount :value="$group->outstandingBalance()" /></p>
                <p class="text-xs text-text-muted mt-1">Payable on or before check in</p>
            </div>
        </div>

        <section id="group-services" aria-labelledby="booked-services-title" class="scroll-mt-24 rounded-xl border border-border bg-surface overflow-hidden">
            <div class="p-5 border-b border-border">
                <h3 id="booked-services-title" class="font-heading text-lg font-bold text-text">Services included in this booking</h3>
                <p class="mt-1 text-sm text-text-muted">{{ $group->reservations->count() }} services · {{ $group->group_code }}</p>
                @if($group->special_requests)
                    <p class="mt-3 text-sm font-semibold">Requests for the whole booking</p>
                    <p class="whitespace-pre-wrap break-words text-sm text-text-muted">{{ $group->special_requests }}</p>
                @endif
            </div>
            <ul class="divide-y divide-border">
                @foreach($group->reservations as $reservation)
                    <li class="p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h4 class="font-semibold text-text">{{ $reservation->facility->name }}</h4>
                                <p class="mt-1 text-sm text-text-muted">
                                    {{ $reservation->check_in->format('M d, Y g:i A') }} &ndash; {{ $reservation->check_out->format('M d, Y g:i A') }}
                                    · {{ $reservation->guest_count }} guests
                                    @if($reservation->early_check_in_hours > 0) · {{ $reservation->early_check_in_hours }}h early check-in @endif
                                </p>
                                <p class="mt-1 text-xs text-text-muted">{{ $reservation->frontDeskStatusLabel() }}</p>
                            </div>
                            <span class="font-semibold text-text"><x-amount :value="$reservation->total_price" /></span>
                        </div>
                        @if($reservation->special_requests && $reservation->special_requests !== $group->special_requests)
                            <p class="mt-3 text-sm font-semibold">Special request</p>
                            <p class="whitespace-pre-wrap break-words text-sm text-text-muted">{{ $reservation->special_requests }}</p>
                        @endif
                        @if($reservation->late_checkout_request)
                            <div class="mt-3">@include('admin.reservations.late-checkout')</div>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if($group->reservations->contains('status', \App\Enums\ReservationStatus::Approved))
                <x-content-panel class="border-t border-border px-5">
                    <x-slot:heading>Service guest sheets</x-slot:heading>
                    <div class="mt-3 space-y-3">
                        @foreach($group->reservations as $reservation)
                            @if($reservation->status === \App\Enums\ReservationStatus::Approved)
                                <div>
                                    <p class="mb-2 text-sm font-medium">{{ $reservation->facility->name }}</p>
                                    @include('admin.reservations.partials.guest-sheet-actions')
                                </div>
                            @endif
                        @endforeach
                    </div>
                </x-content-panel>
            @endif
        </section>

        {{-- Group Payment Panel --}}
        @php
            $allGroupPayments = $group->directPayments->merge($group->payments)->unique('id');
        @endphp
        <div id="group-payments" class="scroll-mt-24 rounded-xl border border-border bg-surface p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-heading text-base font-bold text-text">Group payments</h3>
                    <p class="text-xs text-text-muted mt-0.5">Payments for all services in this booking.</p>
                </div>
            </div>

            @if($allGroupPayments->isEmpty())
                <p class="text-sm text-text-muted">No payments have been recorded for this booking.</p>
            @else
                @if($allGroupPayments->contains('status', \App\Enums\PaymentStatus::Pending))
                    <p class="text-sm font-semibold text-text">Awaiting verification · <x-amount :value="$group->pendingPaymentsTotal()" /></p>
                @endif
                <div class="divide-y divide-border border-y border-border">
                    @foreach($allGroupPayments->groupBy(fn ($payment) => ($payment->method?->label() ?? $payment->payment_method ?? 'Payment').'|'.$payment->status->value) as $payments)
                        @php $paymentSummary = $payments->first(); @endphp
                        <div class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                            <span>{{ $paymentSummary->method?->label() ?? $paymentSummary->payment_method ?? 'Payment' }} · {{ $paymentSummary->status->value }}</span>
                            <span class="font-semibold"><x-amount :value="$payments->sum('amount')" /></span>
                        </div>
                    @endforeach
                </div>
                <x-content-panel class="rounded-lg border border-border px-4">
                    <x-slot:heading>
                        {{ $allGroupPayments->contains('status', \App\Enums\PaymentStatus::Pending) ? 'Review supporting payment records' : 'Supporting payment records' }}

                    </x-slot:heading>
                    <div class="divide-y divide-border border-t border-border text-xs">
                        @foreach($allGroupPayments as $payment)
                            <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-surface hover:bg-muted/10 transition">
                                <div class="flex items-start gap-3">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-text text-sm">{{ $payment->method?->label() ?? $payment->payment_method ?? 'Payment' }}</span>
                                            <span class="px-2 py-0.5 rounded-full font-bold uppercase text-[10px] bg-{{ $payment->status === \App\Enums\PaymentStatus::Verified ? 'success' : ($payment->status === \App\Enums\PaymentStatus::Rejected ? 'danger' : 'warning') }}-light text-{{ $payment->status === \App\Enums\PaymentStatus::Verified ? 'success' : ($payment->status === \App\Enums\PaymentStatus::Rejected ? 'danger' : 'warning') }}">
                                                {{ $payment->status->value }}
                                            </span>
                                        </div>
                                        <p class="text-text-muted mt-1">
                                            Reference: <strong class="text-text">{{ $payment->reference_number ?? 'None' }}</strong>
                                            · Paid: {{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') : $payment->created_at->format('M d, Y') }}
                                        </p>
                                        @if($payment->destination_snapshot)
                                            <p class="text-[11px] text-text-muted mt-0.5">
                                                Destination: {{ $payment->destination_snapshot['bank'] ?? '' }} - {{ $payment->destination_snapshot['account_name'] ?? '' }} ({{ $payment->destination_snapshot['account_number'] ?? '' }})
                                            </p>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center flex-wrap gap-3 sm:justify-end">
                                    <span class="font-bold text-base text-text"><x-amount :value="$payment->amount" /></span>
                                    <div class="flex items-center gap-2">
                                        @if($payment->proof_path)
                                            <a href="{{ route('admin.payments.view', $payment) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-lg border border-border px-3 text-xs font-semibold text-text hover:bg-muted">
                                                View proof
                                            </a>
                                        @endif
                                        <a href="{{ route('admin.payments.show', $payment) }}" class="inline-flex min-h-11 items-center rounded-lg border border-border px-3 text-xs font-semibold text-primary hover:bg-muted">
                                            {{ $payment->status === \App\Enums\PaymentStatus::Pending ? 'Review payment' : 'View payment' }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-content-panel>
            @endif
        </div>

        {{-- Cancellation, Refund, and Rescheduling Section --}}
        @if ($showBookingChanges)
            <div id="cancellation-rescheduling" class="space-y-6">
                @include('customer.reservations.partials.booking-discussions', ['reservation' => $group->reservations->first(), 'staffDiscussion' => true, 'groupWorkspace' => true])
            </div>
        @endif
        @if ($showAdjustments)
            @include('admin.reservations.partials.front-desk-adjustments', ['lines' => $group->reservations, 'workflow' => $group->booking_workflow ?? [], 'groupWorkspace' => true])
        @endif
        </div>

        <aside aria-label="Group booking actions" class="min-w-0 lg:sticky lg:top-20 lg:col-start-3 lg:row-start-1 lg:max-h-[calc(100dvh-6rem)] lg:overflow-y-auto">
            @include('admin.reservations.partials.current-task', ['record' => $group])
        </aside>
    </div>

    {{-- Reject / Full-refund Modal Dialog --}}
    <div id="reject-dialog" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-xs" onclick="document.getElementById('reject-dialog').classList.add('hidden')"></div>
        <div class="relative min-h-screen flex items-center justify-center p-4">
            <div class="bg-surface rounded-2xl border border-border shadow-2xl max-w-lg w-full p-6 space-y-4">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="font-heading text-lg font-bold text-danger">Decline Group Reservation</h3>
                        <p class="text-xs text-text-muted mt-0.5">Verified payments qualify for full refund before physical check-in. A pending proof is not verified funds. Owner approval and actual return are recorded separately.</p>
                    </div>
                    <button type="button" onclick="document.getElementById('reject-dialog').classList.add('hidden')" class="text-text-muted hover:text-text font-bold">&times;</button>
                </div>

                <form method="POST" action="{{ route('admin.reservation-groups.reject', $group) }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label class="block text-xs font-bold uppercase text-text-muted mb-1">Reason for declining *</label>
                        <textarea name="reason" rows="3" required class="admin-input text-xs w-full" placeholder="Explain why the resort cannot honor this request…"></textarea>
                    </div>

                    @php
                        $paidTotal = $group->verifiedPaymentsTotal() + $group->pendingPaymentsTotal();
                    @endphp

                    @if($paidTotal > 0)
                        <div class="p-3.5 rounded-xl border border-warning/30 bg-warning/5 space-y-3">
                            <span class="text-xs font-bold text-warning uppercase tracking-wider block">Refund request · step 1 of 3</span>
                            <p class="text-xs text-text-muted">Verified received: <strong class="text-text"><x-amount :value="$group->verifiedPaymentsTotal()" /></strong>. Proofs still under review: <strong class="text-text"><x-amount :value="$group->pendingPaymentsTotal()" /></strong>. Declining starts a refund request. Next, review payment proofs and record owner approval in Cancellation and refund above. Money is returned separately.</p>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-text-muted uppercase">Refund Status</label>
                                    <select name="refund_status" class="admin-input text-xs w-full">
                                        <option value="pending">Request review — money not yet returned</option>

                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-text-muted uppercase">Verified amount for owner review</label>
                                    <input type="number" step="0.01" min="0" name="refund_amount" value="{{ $group->verifiedPaymentsTotal() }}" class="admin-input text-xs w-full font-bold" readonly>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-text-muted uppercase">Staff discussion notes for owner</label>
                                <input type="text" name="refund_notes" placeholder="Reason or information for the owner…" class="admin-input text-xs w-full">
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" onclick="document.getElementById('reject-dialog').classList.add('hidden')" class="px-4 py-2 rounded-lg border border-border text-xs font-semibold hover:bg-muted">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-danger text-white text-xs font-semibold hover:bg-danger/90">Confirm Decline</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
