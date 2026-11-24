@php
    $refundRecord = $workflow['refund'] ?? null;
    $refundStage = $refundRecord['status'] ?? null;
    $refundStep = match ($refundStage) { 'requested' => 2, 'approved', 'returned' => 3, default => 1 };
    $refundPending = $bookingRecord instanceof \App\Models\ReservationGroup ? $bookingRecord->pendingPaymentsTotal() : $reservation->getPendingVerificationTotal();
@endphp
@if($groupWorkspace ?? false)
<details id="refund-flow" class="scroll-mt-24 rounded-xl border border-border bg-surface" @if($refundStage || ($errors->any() && str_contains((string) old('action'), 'refund'))) open @endif>
        <summary class="cursor-pointer rounded-xl px-5 py-4 font-heading text-lg font-bold text-text focus-visible:outline-primary">Cancellation &amp; refund</summary>
        <div class="space-y-4 border-t border-border p-5">
@else
<section id="refund-flow" aria-label="Cancellation and refund" class="scroll-mt-24 rounded-xl border border-border bg-surface p-5 sm:p-6 shadow-xs space-y-5">
@endif
    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border/70 pb-4">
        <div>
            <div class="flex flex-wrap items-center gap-2 mb-1.5">
                @unless($groupWorkspace ?? false)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-accent/10 text-accent border border-accent/20">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Pre-Check-in Policy
                </span>
                @endunless
                @if($refundStage)
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wide bg-{{ $refundStage === 'returned' ? 'success' : ($refundStage === 'approved' ? 'primary' : 'warning') }}-light text-{{ $refundStage === 'returned' ? 'success' : ($refundStage === 'approved' ? 'primary' : 'warning') }}">
                        {{ $refundStage === 'returned' ? 'Funds Returned' : ($refundStage === 'approved' ? 'Owner Approved' : 'Review Requested') }}
                    </span>
                @endif
            </div>
            @unless($groupWorkspace ?? false)
            <h3 class="font-heading text-xl font-bold text-text">Cancellation &amp; Full Refund</h3>
            @endunless
            <p class="text-xs text-text-muted mt-0.5">{{ ($groupWorkspace ?? false) ? 'Before physical check-in, staff can request cancellation of the entire group booking. A full refund covers all verified payments.' : 'A full refund returns 100% of verified payments received for this booking before physical check-in.' }}</p>
        </div>
    </div>

    {{-- Active Stage Alerts --}}
    @if($refundStage || $arrived)
        <div class="rounded-xl border p-4.5 space-y-2.5 {{ $refundStage === 'returned' ? 'border-success/30 bg-success/[0.04]' : ($refundStage === 'approved' ? 'border-primary/30 bg-primary/[0.04]' : ($refundStage === 'requested' ? 'border-warning/30 bg-warning/[0.04]' : 'border-border bg-muted/20')) }}">
            @if($refundStage === 'returned')
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-success text-white font-bold text-xs shrink-0 shadow-2xs">✓</span>
                    <div>
                        <h4 id="refund-status" class="font-bold text-sm text-success">Funds returned — refund complete</h4>
                        <p class="text-xs text-text-muted mt-0.5">The resort has recorded the return of payment. The booking is cancelled and no balance is due.</p>
                    </div>
                </div>
                @unless($staffDiscussion)
                    <p class="text-xs text-text-muted pl-9.5">You do not need to take any further action. Keep the return reference for your records.</p>
                @endunless
                <div class="mt-2 pl-9.5 flex flex-wrap items-center gap-2 text-xs">
                    <span class="font-semibold text-text-muted">Return Reference:</span>
                    <code class="px-2.5 py-1 rounded-md bg-surface border border-border/80 font-mono text-xs font-bold text-text">{{ $refundRecord['reference'] }}</code>
                </div>
            @elseif($refundStage === 'approved')
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-primary text-white font-bold text-xs shrink-0 shadow-2xs">✓</span>
                    <div>
                        <h4 id="refund-status" class="font-bold text-sm text-text">Owner approved — awaiting return of funds</h4>
                        <p class="text-xs text-text-muted mt-0.5">Your booking is cancelled and no balance is due. Approval does not send money. Staff have not yet recorded a payment back to you.</p>
                    </div>
                </div>
                <p class="text-xs font-medium text-text pl-9.5">
                    {{ $staffDiscussion ? 'Next: return the approved amount through the resort’s payment method, then record it below. This system does not transfer money.' : 'The resort handles the disbursement. Staff will post the transfer reference here once money is returned.' }}
                </p>
            @elseif($refundStage === 'requested')
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-warning text-white font-bold text-xs shrink-0 shadow-2xs">!</span>
                    <div>
                        <h4 id="refund-status" class="font-bold text-sm text-warning-dark">Refund requested — awaiting owner approval</h4>
                        <p class="text-xs text-text-muted mt-0.5">The request alone does not cancel the booking. No refund payment has been recorded yet.</p>
                    </div>
                </div>
                <div class="pl-9.5 text-xs text-text-muted space-y-1">
                    @if($refundPending > 0)
                        <p class="font-medium text-warning">{{ $staffDiscussion ? 'Next: verify or reject all pending payment proofs before recording owner approval.' : 'First, staff check your payment proofs. Then the owner decides on your request.' }}</p>
                    @else
                        <p>{{ $staffDiscussion ? 'Next: obtain the owner’s approval, then record their instruction below.' : 'Staff will ask the owner to decide on your request and record the decision here.' }}</p>
                    @endif
                    @unless($staffDiscussion)
                        <p>You do not need to do anything while the resort reviews your request.</p>
                    @endunless
                </div>
            @elseif($arrived)
                <div class="flex items-center gap-2.5 text-text-muted">
                    <svg class="w-5 h-5 text-text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-xs">Physical check-in has started. This cancellation and full-refund option is no longer available.</p>
                </div>
            @endif
        </div>
    @endif

    {{-- Connected 3-Step Progress Track --}}
    @if(($groupWorkspace ?? false) && (! $arrived || $refundRecord))
        <ol class="grid gap-2 text-sm sm:grid-cols-3" aria-label="Refund progress">
            @foreach (['Request cancellation', 'Record owner approval', 'Record returned funds'] as $step)
                @php
                    $complete = $loop->iteration < $refundStep || $refundStage === 'returned';
                    $current = $loop->iteration === $refundStep && ! $complete;
                @endphp
                <li @if($current) aria-current="step" @endif class="border-l-2 px-3 py-1 {{ $current ? 'border-primary bg-primary/5' : 'border-border' }}">
                    <span class="block font-semibold text-text">{{ $step }}</span>
                    <span class="text-xs text-text-muted">{{ $complete ? 'Complete' : ($current ? ($refundStage ? 'Current step' : 'Ready to start') : 'Upcoming') }}</span>
                </li>
            @endforeach
        </ol>
    @elseif(! $arrived || $refundRecord)
        <div class="space-y-2">
            <span class="text-[11px] font-bold uppercase tracking-wider text-text-muted block">Workflow Progress</span>
            <ol class="grid grid-cols-1 sm:grid-cols-3 gap-3" aria-label="Refund progress" @if(in_array($refundStage, ['requested', 'approved', 'returned'], true)) aria-describedby="refund-status" @endif>
                @php
                    $steps = [
                        ['title' => 'Request Cancellation', 'desc' => 'Customer or administrator', 'role' => 'Step 1'],
                        ['title' => 'Owner Approval', 'desc' => 'Owner decides; staff records', 'role' => 'Step 2'],
                        ['title' => 'Return Funds', 'desc' => 'Resort returns money to guest', 'role' => 'Step 3'],
                    ];
                @endphp
                @foreach($steps as $idx => $s)
                    @php
                        $stepNum = $idx + 1;
                        $stepDone = $stepNum < $refundStep || $refundStage === 'returned';
                        $stepActive = $stepNum === $refundStep && $refundStage !== 'returned';
                    @endphp
                    <li @if($stepActive) aria-current="step" @endif class="relative rounded-xl border p-4 transition-all duration-150 {{ $stepDone ? 'border-success/30 bg-success/[0.04]' : ($stepActive ? 'border-primary bg-primary/[0.03] ring-1 ring-primary/20 shadow-xs' : 'border-border/80 bg-surface') }}">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold {{ $stepDone ? 'bg-success text-white' : ($stepActive ? 'bg-primary text-white shadow-2xs' : 'bg-muted text-text-muted') }}">
                                    @if($stepDone)
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    @else
                                        {{ $stepNum }}
                                    @endif
                                </span>
                                <span class="text-[10px] font-bold uppercase tracking-wider {{ $stepDone ? 'text-success' : ($stepActive ? 'text-primary' : 'text-text-muted') }}">
                                    {{ $s['role'] }}
                                </span>
                            </div>
                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $stepDone ? 'bg-success-light text-success' : ($stepActive ? 'bg-primary-light text-primary font-bold' : 'bg-muted/80 text-text-muted') }}">
                                {{ $stepDone ? 'Complete' : ($stepActive ? 'In Progress' : 'Upcoming') }}
                            </span>
                        </div>
                        <p class="text-sm font-bold text-text">{{ $s['title'] }}</p>
                        <p class="text-[11px] text-text-muted mt-0.5 leading-snug">{{ $s['desc'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    {{-- Financial Breakdown Banner (3-Metric Card) --}}
    <div class="rounded-xl border border-border bg-muted/15 p-4 sm:p-5">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {{-- Metric 1: Verified Received --}}
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl bg-success-light text-success flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-text-muted block">Verified Money Received</span>
                    <p class="text-xl font-extrabold text-text mt-0.5 tabular-nums"><x-amount :value="$verified" /></p>
                    <span class="text-[11px] font-medium text-success flex items-center gap-1 mt-0.5">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Verified in system</span>
                    </span>
                </div>
            </div>

            {{-- Metric 2: Refund Amount / Eligible Total --}}
            <div class="flex items-start gap-3 border-t sm:border-t-0 sm:border-l border-border/70 pt-3 sm:pt-0 sm:pl-4">
                <div class="w-10 h-10 rounded-xl bg-primary-light text-primary flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-text-muted block">
                        {{ isset($refundRecord['amount_cents']) ? ($refundStage === 'returned' ? 'Amount Returned' : 'Approved Refund Amount') : 'Eligible Refund Amount' }}
                    </span>
                    <p class="text-xl font-extrabold text-primary mt-0.5 tabular-nums">
                        @if(isset($refundRecord['amount_cents']))
                            <x-amount :value="$refundRecord['amount_cents'] / 100" />
                        @else
                            <x-amount :value="$verified" />
                        @endif
                    </p>
                    <span class="text-[11px] text-text-muted mt-0.5 block">100% pre-check-in return</span>
                </div>
            </div>

            {{-- Metric 3: Disbursement / Proof Status --}}
            <div class="flex items-start gap-3 border-t sm:border-t-0 sm:border-l border-border/70 pt-3 sm:pt-0 sm:pl-4">
                <div class="w-10 h-10 rounded-xl {{ $refundPending > 0 ? 'bg-warning-light text-warning' : 'bg-surface text-text-muted border border-border' }} flex items-center justify-center shrink-0 mt-0.5">
                    @if($refundPending > 0)
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    @else
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    @endif
                </div>
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-text-muted block">
                        {{ $refundPending > 0 ? 'Proofs Under Review' : 'Disbursement Route' }}
                    </span>
                    @if($refundPending > 0)
                        <p class="text-xl font-extrabold text-warning mt-0.5 tabular-nums"><x-amount :value="$refundPending" /></p>
                        <span class="text-[11px] text-warning-dark font-medium mt-0.5 block">Review proofs before approval</span>
                    @else
                        <p class="text-sm font-bold text-text mt-1">Manual Payout / GCash</p>
                        <span class="text-[11px] text-text-muted mt-0.5 block">Recorded upon actual transfer</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Stored Reasons & Notes --}}
    @if($refundRecord)
        <div class="rounded-xl bg-surface border border-border p-4 text-xs space-y-1.5 shadow-2xs">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-danger"></span>
                <span class="font-bold text-text text-xs uppercase tracking-wide">Cancellation Reason</span>
            </div>
            <p class="text-text-muted break-words leading-relaxed pl-4">{{ $refundRecord['reason'] }}</p>
        </div>
    @endif

    @if($staffDiscussion && isset($refundRecord['owner_approval_note']))
        <div class="rounded-xl bg-surface border border-border p-4 text-xs space-y-1.5 shadow-2xs">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-primary"></span>
                <span class="font-bold text-text text-xs uppercase tracking-wide">Recorded Owner Instruction Note</span>
            </div>
            <p class="text-text-muted break-words leading-relaxed pl-4">{{ $refundRecord['owner_approval_note'] }}</p>
        </div>
    @endif

    {{-- Stage 1: Request Cancellation Form --}}
    @if(! $refundRecord && ! $arrived)
        <div class="rounded-xl border border-danger/30 bg-danger/[0.02] p-5 space-y-4">
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-danger/10 text-danger flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-text">Initiate Full Cancellation Request</h4>
                    <p class="text-xs text-text-muted mt-0.5">Submitting initiates an owner-approved refund workflow for the whole booking. Eligible before physical check-in.</p>
                </div>
            </div>

            <form method="POST" action="{{ route($discussionRoute, $reservation) }}" class="space-y-4 pt-1" onsubmit="return confirm('Are you sure you want to request cancellation and a full refund for this booking? This will submit the request for owner approval.');">
                @csrf
                @method('PATCH')
                <input type="hidden" name="action" value="request_refund">
                <div>
                    <label for="cancellation_reason_{{ $reservation->id }}" class="admin-label flex items-center justify-between">
                        <span>Cancellation Reason (applies to whole booking) <span class="text-danger">*</span></span>
                        <span class="text-[11px] text-text-muted font-normal">Max 1,000 characters</span>
                    </label>
                    <textarea id="cancellation_reason_{{ $reservation->id }}" name="reason" required maxlength="1000" rows="3" class="admin-input mt-1.5 w-full text-sm placeholder:text-text-muted" placeholder="Please describe the detailed reason for cancellation (e.g. guest emergency, medical, date conflict)..."></textarea>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                    <p class="text-[11px] text-text-muted">Verified funds received (<x-amount :value="$verified" />) will be prepared for disbursement upon owner approval.</p>
                    <button type="submit" class="px-5 py-2.5 rounded-lg border border-danger/40 bg-danger/10 text-danger font-semibold text-xs hover:bg-danger hover:text-white transition-all duration-150 shadow-xs flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        <span>Request cancellation and full refund</span>
                    </button>
                </div>
            </form>
        </div>

    {{-- Stage 2: Staff Owner Approval Form --}}
    @elseif($staffDiscussion && $refundStage === 'requested' && $refundPending <= 0)
        <div class="rounded-xl border border-primary/30 bg-primary/[0.02] p-5 space-y-4">
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-text">Record Owner Approval</h4>
                    <p class="text-xs text-text-muted mt-0.5">Saving approval cancels the booking and authorizes full refund of verified funds (<x-amount :value="$verified" />). Actual money return is recorded next.</p>
                </div>
            </div>

            <form method="POST" action="{{ route($discussionRoute, $reservation) }}" class="space-y-4 pt-1">
                @csrf
                @method('PATCH')
                <input type="hidden" name="action" value="approve_refund">
                <div>
                    <label for="owner_instruction_{{ $reservation->id }}" class="admin-label flex items-center justify-between">
                        <span>Owner approval / instruction <span class="text-danger">*</span></span>
                        <span class="text-[11px] text-text-muted font-normal">Max 1,000 characters</span>
                    </label>
                    <textarea id="owner_instruction_{{ $reservation->id }}" name="reason" required maxlength="1000" rows="3" class="admin-input mt-1.5 w-full text-sm placeholder:text-text-muted" placeholder="Record what the owner approved, authorization date, and any special instructions…"></textarea>
                </div>

                <div class="flex items-center justify-end pt-1">
                    <button type="submit" class="px-5 py-2.5 rounded-lg bg-primary text-white font-semibold text-xs hover:bg-primary-hover transition-colors duration-150 shadow-xs flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Record owner-approved full refund</span>
                    </button>
                </div>
            </form>
        </div>

    {{-- Stage 3: Staff Return Funds Form --}}
    @elseif($staffDiscussion && $refundStage === 'approved')
        @if($refundPending > 0 || (int) round($verified * 100) !== $refundRecord['amount_cents'])
            <div class="rounded-xl border border-danger/30 bg-danger/5 p-4 flex items-center gap-3">
                <svg class="w-5 h-5 text-danger shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <p class="text-xs font-semibold text-danger">Payments have changed since approval. Reconcile payment records before recording returned funds.</p>
            </div>
        @else
            <div class="rounded-xl border border-secondary/30 bg-secondary/[0.04] p-5 space-y-4">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-secondary/20 text-secondary flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-text">Record Returned Funds to Guest</h4>
                        <p class="text-xs text-text-muted mt-0.5">Save only after money has actually been disbursed. The returned amount must match the approved refund.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route($discussionRoute, $reservation) }}" class="space-y-4 pt-1">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="action" value="return_funds">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label for="return_amount_{{ $reservation->id }}" class="admin-label">Amount Actually Returned (₱) <span class="text-danger">*</span></label>
                            <div class="relative mt-1">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-text-muted text-sm font-semibold">₱</span>
                                <input id="return_amount_{{ $reservation->id }}" name="amount" type="number" step="0.01" min="0" required class="admin-input pl-7 text-sm font-bold tabular-nums" value="{{ $refundRecord['amount_cents'] / 100 }}">
                            </div>
                        </div>
                        <div>
                            <label for="return_ref_{{ $reservation->id }}" class="admin-label">Return Reference / Receipt No. <span class="text-danger">*</span></label>
                            <input id="return_ref_{{ $reservation->id }}" name="reason" required maxlength="1000" class="admin-input mt-1 text-sm" placeholder="e.g. GCash Ref #123456789 or Bank Ref">
                        </div>
                    </div>

                    <div class="flex items-center justify-end pt-1">
                        <button type="submit" class="px-5 py-2.5 rounded-lg bg-primary text-white font-semibold text-xs hover:bg-primary-hover transition-colors duration-150 shadow-xs flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>Record returned funds</span>
                        </button>
                    </div>
                </form>
            </div>
        @endif
    @endif

    {{-- Final Audit Note --}}
    @if($refundStage === 'returned')
        <div class="border-t border-border/70 pt-3 flex items-center gap-2 text-[11px] text-text-muted">
            <svg class="w-3.5 h-3.5 text-success shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>Original payments remain in the audit ledger as funds received. This refund confirms the separate returned disbursement.</span>
        </div>
    @endif
@if($groupWorkspace ?? false)
        </div>
    </details>
@else
</section>
@endif
