@php
    $staffDiscussion = $staffDiscussion ?? false;
    $groupWorkspace = $groupWorkspace ?? false;
    $bookingRecord = $reservation->reservationGroup ?? $reservation;
    $workflow = $bookingRecord->booking_workflow ?? [];
    $discussionRoute = $staffDiscussion ? 'admin.reservations.discussion' : 'customer.reservations.discussion';
    $arrived = $bookingRecord instanceof \App\Models\ReservationGroup
        ? $bookingRecord->reservations()->whereNotNull('checked_in_at')->exists() : $reservation->checked_in_at !== null;
    $verified = $bookingRecord instanceof \App\Models\ReservationGroup ? $bookingRecord->verifiedPaymentsTotal() : $reservation->getVerifiedTotalPaid();
    $total = $bookingRecord instanceof \App\Models\ReservationGroup ? $bookingRecord->total_amount : $reservation->total_price;
    $lines = $bookingRecord instanceof \App\Models\ReservationGroup ? $bookingRecord->reservations : collect([$reservation]);
    $rescheduleFailed = $groupWorkspace && old('action') === 'propose_change' && $errors->any();
    $hasChange = $lines->contains(fn($line) => isset($line->booking_workflow['change']));
    $hasPendingChange = $lines->contains(fn($line) => in_array($line->booking_workflow['change']['status'] ?? null, ['proposed', 'accepted'], true));
    $eligibleLines = $arrived || isset($workflow['refund']) ? collect() : $lines->filter(
        fn($line) => in_array($line->status, [\App\Enums\ReservationStatus::Pending, \App\Enums\ReservationStatus::Approved], true)
    );
    $canProposeChange = $eligibleLines->isNotEmpty();
    $activeRoomId = $reservation->id;

    if ($groupWorkspace) {
        $failedLine = $rescheduleFailed ? $lines->firstWhere('id', (int) old('reschedule_line')) : null;
        $pendingLine = $lines->first(fn($line) => in_array($line->booking_workflow['change']['status'] ?? null, ['proposed', 'accepted'], true));
        $activeRoomId = ($failedLine ?? $pendingLine ?? $eligibleLines->first() ?? $reservation)->id;
    }
@endphp

@if($bookingRecord->policy_version === config('resort.policy.confirmed_version'))
    <section class="space-y-6" aria-label="Booking discussions">
        {{-- Cancellation and Refund flow --}}
        @include('customer.reservations.partials.refund-flow')

        {{-- Rescheduling and In-Flight Changes Container --}}
        @if($groupWorkspace)
            <details id="group-reschedule" class="scroll-mt-24 rounded-xl border border-border bg-surface" @if($rescheduleFailed || $hasPendingChange) open @endif>
        <summary class="cursor-pointer rounded-xl px-5 py-4 font-heading text-lg font-bold text-text focus-visible:outline-primary">Reschedule</summary>
        <div class="space-y-4 border-t border-border p-5">
                @if($canProposeChange || $hasPendingChange)
                    <p class="mt-1 text-sm text-text-muted">Choose a service and propose new dates. A change takes effect after the
                        guest accepts the revised quote and staff confirm it.</p>
                @else
                    <p class="mt-1 text-sm text-text-muted">No services are currently eligible for rescheduling.</p>
                @endif
                @if($canProposeChange || $hasChange)
                    <section class="mt-4 border-t border-border">
                        <div class="px-4 py-3 text-sm font-semibold text-text">Rescheduling options</div>
                @endif
        @endif
                @if(!$groupWorkspace || $canProposeChange || $hasChange)
                    <div x-data="{ activeRoomId: {{ $activeRoomId }} }" class="space-y-4">
                        {{-- Multi-Room Group Navigation Tabs (if group has > 1 service line) --}}
                        @if($lines->count() > 1)
                            <div role="group" aria-label="Service to reschedule"
                                class="flex flex-wrap items-center gap-2 border-b border-border pb-3">
                                <span
                                    class="text-xs font-bold uppercase tracking-wider text-text-muted shrink-0 mr-1 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                    <span>Choose service</span>
                                </span>
                                @foreach($lines as $line)
                                    @php
                                        $lineChange = $line->booking_workflow['change'] ?? null;
                                    @endphp
                                    <button type="button" @click="activeRoomId = {{ $line->id }}"
                                        :aria-pressed="activeRoomId === {{ $line->id }}"
                                        :class="activeRoomId === {{ $line->id }} ? 'bg-primary text-white shadow-2xs font-bold' : 'bg-surface hover:bg-muted text-text font-medium border border-border/80'"
                                        class="min-h-11 px-3 rounded-lg text-sm transition-colors flex items-center gap-2">
                                        <span>{{ $line->facility->name }}</span>
                                        @if($lineChange)
                                            <span class="text-xs font-medium">
                                                {{ $lineChange['status'] }}
                                            </span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        @foreach($lines as $currentLine)
                            @php
                                $change = $currentLine->booking_workflow['change'] ?? null;
                                $restoreDraft = $rescheduleFailed && $activeRoomId === $currentLine->id;
                            @endphp
                            <div x-show="activeRoomId === {{ $currentLine->id }}" class="space-y-6" x-cloak>
                                {{-- In-Flight Change Comparison (if proposed/accepted) --}}
                                @if($change)
                                        <div class="rounded-xl border border-border bg-surface p-5 sm:p-6 shadow-xs space-y-5">
                                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border/70 pb-3">
                                                <div>
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <span
                                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-primary/10 text-primary border border-primary/20">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                            </svg>
                                                            Stay Modification
                                                        </span>
                                                        <span
                                                            class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-{{ $change['status'] === 'confirmed' ? 'success' : ($change['status'] === 'accepted' ? 'primary' : 'warning') }}-light text-{{ $change['status'] === 'confirmed' ? 'success' : ($change['status'] === 'accepted' ? 'primary' : 'warning') }}">
                                                            {{ ucfirst($change['status']) }}
                                                        </span>
                                                    </div>
                                                    <h4 class="font-heading text-lg font-bold text-text">Proposed Stay &amp; Quote
                                                        Comparison &ndash; {{ $currentLine->facility->name }}</h4>
                                                </div>
                                            </div>

                                            {{-- Side-by-Side Comparison Cards --}}
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                {{-- Current Stay --}}
                                                <div class="rounded-xl border border-border/80 bg-muted/20 p-4 space-y-1.5">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-[11px] font-bold uppercase tracking-wider text-text-muted">Current
                                                            Stay (PST)</span>
                                                        <span
                                                            class="text-xs font-semibold px-2 py-0.5 rounded bg-muted text-text-muted">Active
                                                            Booking</span>
                                                    </div>
                                                    <p class="text-sm font-bold text-text pt-1">
                                                        {{ $currentLine->check_in->format('M j, Y g:i A') }} &ndash;
                                                        {{ $currentLine->check_out->format('M j, Y g:i A') }}</p>
                                                    <p class="text-xs text-text-muted">{{ $currentLine->facility->name }} ·
                                                        {{ $currentLine->guest_count }} guests</p>
                                                </div>

                                                {{-- Proposed Stay --}}
                                                @if($change['status'] !== 'confirmed')
                                                    <div
                                                        class="rounded-xl border border-primary/30 bg-primary/[0.04] p-4 space-y-1.5 shadow-2xs">
                                                        <div class="flex items-center justify-between">
                                                            <span class="text-[11px] font-bold uppercase tracking-wider text-primary">Proposed
                                                                Stay (PST)</span>
                                                            <span
                                                                class="text-xs font-bold px-2 py-0.5 rounded bg-primary-light text-primary">Proposed</span>
                                                        </div>
                                                        <p class="text-sm font-bold text-text pt-1">
                                                            {{ \Carbon\Carbon::parse($change['selection']['check_in'])->format('M j, Y g:i A') }}
                                                            &ndash;
                                                            {{ \Carbon\Carbon::parse($change['selection']['check_out'])->format('M j, Y g:i A') }}
                                                        </p>
                                                        @php
                                                            $proposedFacility = \App\Models\Facility::find($change['selection']['facility_id']);
                                                        @endphp
                                                        <p class="text-xs text-text-muted">{{ $proposedFacility?->name ?? 'Selected Service' }}
                                                            · {{ $change['selection']['guest_count'] }} guests</p>
                                                    </div>
                                                @endif
                                            </div>

                                            {{-- Financial Variance Banner --}}
                                            <div
                                                class="rounded-xl border border-border/70 bg-muted/10 p-3.5 flex flex-wrap items-center justify-between gap-4 text-xs font-medium">
                                                <div class="flex flex-wrap items-center gap-3">
                                                    <div>
                                                        <span class="text-text-muted text-[11px] uppercase font-semibold block">Revised
                                                            Quote:</span>
                                                        <span class="text-text font-bold text-sm"><x-amount :value="$change['quote_cents'] / 100" /></span>
                                                    </div>
                                                    <span class="text-border">|</span>
                                                    <div>
                                                        <span class="text-text-muted text-[11px] uppercase font-semibold block">Rate
                                                            Difference:</span>
                                                        @php $diff = ($change['quote_cents'] - $change['old_total_cents']) / 100; @endphp
                                                        <span
                                                            class="font-bold text-sm {{ $diff > 0 ? 'text-primary' : ($diff < 0 ? 'text-success' : 'text-text') }}">
                                                            {{ $diff > 0 ? '+' : '' }}<x-amount :value="$diff" />
                                                        </span>
                                                    </div>
                                                    <span class="text-border">|</span>
                                                    <div>
                                                        <span
                                                            class="text-text-muted text-[11px] uppercase font-semibold block">Status:</span>
                                                        <span class="font-bold text-sm text-text uppercase">{{ $change['status'] }}</span>
                                                    </div>
                                                </div>

                                                <p class="text-xs text-text-muted">{{ match ($change['status']) {
                                        'proposed' => 'The guest reviews the revised quote next. Current stay remains active.',
                                        'accepted' => 'The guest accepted the quote. Staff verifies live room availability before final confirmation.',
                                        'confirmed' => 'Staff confirmed the change. The stay has been updated with the new schedule.',
                                        default => '',
                                    } }}</p>
                                            </div>

                                            {{-- Action Buttons --}}
                                            @if(!$staffDiscussion && $change['status'] === 'proposed' && !$arrived)
                                                <form method="POST" action="{{ route($discussionRoute, $currentLine) }}" class="pt-1">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="action" value="accept_change">
                                                    <input type="hidden" name="amount" value="{{ $change['quote_cents'] / 100 }}">
                                                    <button type="submit"
                                                        class="px-5 py-2.5 rounded-lg bg-primary text-white text-xs font-semibold hover:bg-primary-hover shadow-xs transition-colors duration-150 flex items-center gap-1.5">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        <span>Accept Revised Service &amp; Quote</span>
                                                    </button>
                                                </form>
                                            @elseif($staffDiscussion && $change['status'] === 'accepted' && !$arrived)
                                                <form method="POST" action="{{ route($discussionRoute, $currentLine) }}" class="pt-1">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="action" value="confirm_change">
                                                    <button type="submit"
                                                        class="px-5 py-2.5 rounded-lg bg-primary text-white text-xs font-semibold hover:bg-primary-hover shadow-xs transition-colors duration-150 flex items-center gap-1.5">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        <span>Confirm Accepted Reschedule (Finalize Dates)</span>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                @endif

                                {{-- Reschedule Proposal Form --}}
                                @if(!$arrived && in_array($currentLine->status, [\App\Enums\ReservationStatus::Pending, \App\Enums\ReservationStatus::Approved], true) && !isset($workflow['refund']))
                                    <div x-data="{ discarded: false }"
                                        class="rounded-xl border border-border bg-surface p-5 sm:p-6 shadow-xs space-y-5">
                                        @if(old('action') === 'propose_change' && $errors->any() && (!$groupWorkspace || (int) old('reschedule_line') === $currentLine->id))
                                            <div role="alert" tabindex="-1" autofocus
                                                class="rounded-xl bg-danger-light/70 border border-danger/30 p-4 text-sm text-danger flex items-center gap-2">
                                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                </svg>
                                                <span>{{ $errors->first() }}</span>
                                            </div>
                                        @endif

                                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border/70 pb-3">
                                            <div>
                                                <div class="flex items-center gap-2 mb-1">
                                                    <h4 class="font-heading text-xl font-bold text-text">
                                                        {{ $staffDiscussion ? 'Reschedule Stay' : 'Request Rescheduling' }}</h4>
                                                    <span
                                                        class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-muted text-text-muted border border-border/60">
                                                        No Notice Penalty
                                                    </span>
                                                </div>
                                                <p class="text-xs text-text-muted mt-0.5">
                                                    Currently booked: <strong
                                                        class="text-text font-bold">{{ $currentLine->facility->name }}</strong>
                                                    · {{ $currentLine->check_in->format('M j, Y g:i A') }} &ndash;
                                                    {{ $currentLine->check_out->format('M j, Y g:i A') }} (PST)
                                                </p>
                                            </div>
                                        </div>

                                        <form method="POST" action="{{ route($discussionRoute, $currentLine) }}" class="space-y-5">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="action" value="propose_change">
                                            @if($groupWorkspace)
                                                <input type="hidden" name="reschedule_line" value="{{ $currentLine->id }}">
                                            @endif

                                            {{-- Sub-section 1: Facility & Guests --}}
                                            <fieldset
                                                class="rounded-xl border border-border/80 bg-muted/15 min-w-0 p-4 sm:p-5 space-y-4">
                                                <legend class="px-2 text-sm font-semibold text-text">Service &amp; Guest Capacity
                                                </legend>

                                                <div class="grid gap-4 sm:grid-cols-2">
                                                    <div>
                                                        <label for="change_facility_{{ $currentLine->id }}" class="admin-label">Service
                                                            / Room <span class="text-danger">*</span></label>
                                                        <select id="change_facility_{{ $currentLine->id }}" name="facility_id" required
                                                            class="admin-input mt-1">
                                                            @foreach(\App\Models\Facility::listed()->with('facilityType')->get() as $option)
                                                                <option value="{{ $option->id }}" @selected($option->id === ($restoreDraft ? (int) old('facility_id') : $currentLine->facility_id))>
                                                                    {{ $option->name }} ({{ $option->facilityType?->name }})</option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    <div>
                                                        <label for="change_guests_{{ $currentLine->id }}" class="admin-label">Guests
                                                            Aged 1 &amp; Above <span class="text-danger">*</span></label>
                                                        <input id="change_guests_{{ $currentLine->id }}" name="guest_count"
                                                            type="number" min="1" required
                                                            value="{{ $restoreDraft ? old('guest_count') : $currentLine->guest_count }}"
                                                            class="admin-input mt-1 tabular-nums">
                                                    </div>

                                                    <div>
                                                        <label for="change_pax_{{ $currentLine->id }}" class="admin-label">Package Guest
                                                            Tier (optional)</label>
                                                        <input id="change_pax_{{ $currentLine->id }}" name="pax_tier" type="number"
                                                            min="1" value="{{ $restoreDraft ? old('pax_tier') : '' }}"
                                                            class="admin-input mt-1" placeholder="e.g. 10, 20 pax tier">
                                                    </div>

                                                    <div>
                                                        <label for="change_session_{{ $currentLine->id }}" class="admin-label">Cottage
                                                            Swimming Session</label>
                                                        <select id="change_session_{{ $currentLine->id }}" name="swimming_session_id"
                                                            class="admin-input mt-1">
                                                            <option value="">Not a cottage stay</option>
                                                            @foreach(\App\Models\SwimmingSession::where('is_active', true)->get() as $session)
                                                                <option value="{{ $session->id }}" @selected($restoreDraft && (int) old('swimming_session_id') === $session->id)>{{ $session->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </fieldset>

                                            {{-- Sub-section 2: Proposed Dates & Times --}}
                                            <fieldset
                                                class="rounded-xl border border-border/80 bg-muted/15 min-w-0 p-4 sm:p-5 space-y-4">
                                                <legend class="px-2 text-sm font-semibold text-text">Proposed Schedule</legend>

                                                <div class="grid gap-4 sm:grid-cols-2">
                                                    <div>
                                                        <label for="change_check_in_{{ $currentLine->id }}" class="admin-label">Proposed
                                                            Check-In <span class="text-danger">*</span></label>
                                                        <input id="change_check_in_{{ $currentLine->id }}" name="check_in"
                                                            type="datetime-local" required
                                                            value="{{ $restoreDraft ? old('check_in') : '' }}" class="admin-input mt-1">
                                                    </div>

                                                    <div>
                                                        <label for="change_check_out_{{ $currentLine->id }}"
                                                            class="admin-label">Proposed Check-Out <span
                                                                class="text-danger">*</span></label>
                                                        <input id="change_check_out_{{ $currentLine->id }}" name="check_out"
                                                            type="datetime-local" required
                                                            value="{{ $restoreDraft ? old('check_out') : '' }}"
                                                            class="admin-input mt-1">
                                                    </div>

                                                    <div class="sm:col-span-2">
                                                        <label for="change_early_hours_{{ $currentLine->id }}"
                                                            class="admin-label">Online Early Check-In Hours (Overnight rooms only, max
                                                            2h)</label>
                                                        <input id="change_early_hours_{{ $currentLine->id }}"
                                                            name="early_check_in_hours" type="number" min="0" max="2"
                                                            value="{{ $restoreDraft ? old('early_check_in_hours') : ($currentLine->early_check_in_hours ?? 0) }}"
                                                            class="admin-input mt-1 sm:max-w-xs tabular-nums">
                                                        <p class="admin-help">Standard overnight check-in starts at 2:00 PM; check-out
                                                            is at 12:00 PM noon.</p>
                                                    </div>
                                                </div>
                                            </fieldset>

                                            <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                                                <p class="text-xs text-text-muted leading-relaxed max-w-xl">
                                                    The system checks availability and calculates a new quote before sending it for
                                                    guest review.
                                                </p>
                                                <div class="flex flex-wrap items-center gap-3">
                                                    <button type="button"
                                                        class="px-3.5 py-2 text-xs font-medium text-text-muted hover:text-text transition-colors"
                                                        @click="$el.form.reset(); discarded = true">
                                                        Reset form
                                                    </button>
                                                    <button type="submit"
                                                        class="px-5 py-2.5 rounded-lg bg-primary text-white text-xs font-semibold hover:bg-primary-hover shadow-xs transition-colors duration-150 flex items-center gap-1.5">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                        <span>Send revised quote for guest review</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                        <p role="status" class="text-xs text-text-muted font-medium"
                                            x-text="discarded ? 'Draft fields reset. Current stay remains booked.' : ''"></p>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                @endif
                @if($groupWorkspace)
                            @if($canProposeChange || $hasChange)
                                </section>
                            @endif
                    </div>
                </details>
                @endif

        {{-- Child Charge Discussions (if applicable) --}}
        @if(isset($workflow['child_charge']) || ($staffDiscussion && ($workflow['children_count'] ?? 0) > 0 && !isset($workflow['refund']) && !$arrived))
            <div class="rounded-xl border border-border bg-surface p-5 sm:p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-border/70 pb-3">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-primary">Guest Composition</span>
                        <h4 class="font-heading text-lg font-bold text-text mt-0.5">Child Charge Decision</h4>
                    </div>
                </div>

                @if(isset($workflow['child_charge']))
                    <div class="rounded-xl bg-muted/20 border border-border p-4 text-xs space-y-1.5">
                        <p class="text-sm font-semibold text-text">Status: <span
                                class="uppercase text-primary">{{ $workflow['child_charge']['status'] }}</span> · <x-amount
                                :value="$workflow['child_charge']['amount_cents'] / 100" /> ·
                            {{ $workflow['child_charge']['reason'] }}</p>
                        <p class="text-text-muted">A dispute holds approval and check-in for staff discussion.</p>
                    </div>
                    @if(!$staffDiscussion && $workflow['child_charge']['status'] === 'owner_approved')
                        <form method="POST" action="{{ route($discussionRoute, $reservation) }}" class="space-y-3 pt-2">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="action" value="dispute_child">
                            <div>
                                <label class="admin-label">Discussion reason <span class="text-danger">*</span></label>
                                <input name="reason" required class="admin-input mt-1">
                            </div>
                            <button type="submit"
                                class="px-4 py-2.5 rounded-lg border border-primary text-primary text-xs font-semibold hover:bg-primary hover:text-white transition">Dispute
                                child charge &ndash; hold for discussion</button>
                        </form>
                    @elseif($staffDiscussion && $workflow['child_charge']['status'] === 'disputed')
                        <form method="POST" action="{{ route($discussionRoute, $reservation) }}" class="space-y-3 pt-2">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="action" value="resolve_child">
                            <div>
                                <label class="admin-label">Guest-agreed discussion outcome <span class="text-danger">*</span></label>
                                <input name="reason" required class="admin-input mt-1">
                            </div>
                            <button type="submit"
                                class="px-4 py-2.5 rounded-lg bg-primary text-white text-xs font-semibold hover:bg-primary-hover transition">Record
                                agreed discussion outcome</button>
                        </form>
                    @endif
                @elseif($staffDiscussion && ($workflow['children_count'] ?? 0) > 0 && !isset($workflow['child_charge']) && !isset($workflow['refund']) && !$arrived)
                    <form method="POST" action="{{ route($discussionRoute, $reservation) }}" class="space-y-3 pt-2">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="action" value="child_charge">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="admin-label">Owner-approved child charge (₱) <span
                                        class="text-danger">*</span></label>
                                <input name="amount" type="number" step="0.01" min="0" required
                                    class="admin-input mt-1 tabular-nums">
                            </div>
                            <div>
                                <label class="admin-label">Owner instruction and reason <span class="text-danger">*</span></label>
                                <input name="reason" required class="admin-input mt-1">
                            </div>
                        </div>
                        <button type="submit"
                            class="px-4 py-2.5 rounded-lg border border-primary text-primary text-xs font-semibold hover:bg-primary hover:text-white transition">Record
                            owner child-charge decision</button>
                    </form>
                @endif
            </div>
        @endif
    </section>
@else
    <p class="text-sm text-text-muted">Accepted policy: {{ $bookingRecord->policy_version ?? 'Historical terms' }}. This
        historical booking retains its saved prices and terms; contact staff to review changes.</p>
@endif