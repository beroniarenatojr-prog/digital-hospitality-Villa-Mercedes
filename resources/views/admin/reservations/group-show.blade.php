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

    <div class="space-y-6 pb-20">

        {{-- Guest Profile & Primary Decisions Bar --}}
        <div class="p-6 rounded-xl border border-border bg-surface shadow-xs flex flex-wrap items-center justify-between gap-6">
            <div class="space-y-1">
                <span class="text-xs font-bold uppercase tracking-wider text-primary">Lead Guest Information</span>
                <h2 class="text-xl font-bold text-text">{{ $group->guest_name }}</h2>
                <p class="text-sm text-text-muted">
                    {{ $group->guest_email }} · {{ $group->guest_phone }}
                    @if($group->guest_place_of_origin) · {{ $group->guest_place_of_origin }} @endif
                </p>
            </div>
            
            <div class="flex items-center gap-3">
                @if($group->isPending())
                    <form method="POST" action="{{ route('admin.reservation-groups.approve', $group) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="px-5 py-2.5 rounded-lg bg-success text-white font-semibold hover:bg-success/90 transition shadow-sm text-sm">
                            Approve Entire Group
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.reservation-groups.reject', $group) }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        @method('PATCH')
                        <label class="sr-only">Rejection reason</label>
                        <input type="text" name="reason" placeholder="Reason for rejection…" required maxlength="500" class="admin-input text-xs" value="{{ old('reason') }}">
                        <button type="submit" class="px-4 py-2.5 rounded-lg border border-danger text-danger font-semibold hover:bg-danger-light transition text-sm">
                            Reject Request
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Overall Visit Window --}}
        @if($group->reservations->isNotEmpty())
            <div class="rounded-xl border border-border bg-surface p-4 flex items-center justify-between shadow-xs">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-text-muted">Overall Visit Window (Asia/Manila)</span>
                    <p class="text-base font-semibold text-text mt-0.5">
                        {{ $group->earliestCheckIn()?->format('F d, Y g:i A') }} &ndash; {{ $group->latestCheckOut()?->format('F d, Y g:i A') }}
                    </p>
                </div>
                <span class="text-xs text-text-muted font-medium">All service lines fall inside this resort stay</span>
            </div>
        @endif

        {{-- Financial Metrics Bar --}}
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
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
                <p class="text-xs text-text-muted mt-1">{{ $group->payments->where('status', \App\Enums\PaymentStatus::Verified)->count() }} verified payments</p>
            </div>
            <div class="p-5 rounded-xl border border-border bg-surface shadow-xs">
                <span class="text-xs font-bold uppercase tracking-wider text-text-muted">Remaining Balance</span>
                <p class="text-2xl font-extrabold text-text mt-1"><x-amount :value="$group->outstandingBalance()" /></p>
                <p class="text-xs text-text-muted mt-1">Payable on or before arrival</p>
            </div>
        </div>

        {{-- Service Line Cards Grid --}}
        <div class="rounded-xl border border-border bg-surface shadow-xs overflow-hidden">
            <div class="p-5 border-b border-border bg-muted/20 flex items-center justify-between">
                <div>
                    <h3 class="font-heading text-base font-bold text-text">Service Lines &amp; Operations</h3>
                    <p class="text-xs text-text-muted mt-0.5">Physical facilities operate independently for check-in/out and session attendance.</p>
                </div>
                <span class="text-xs font-semibold text-text-muted">{{ $group->reservations->count() }} service lines</span>
            </div>

            <div class="divide-y divide-border">
                @foreach($group->reservations as $reservation)
                    <div class="p-5 flex flex-col md:flex-row md:items-center justify-between gap-5 hover:bg-muted/10 transition">
                        <div class="flex items-start gap-4">
                            @if($reservation->facility->cover_image_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($reservation->facility->cover_image_path) }}" alt="{{ $reservation->facility->name }}" class="w-20 h-20 rounded-xl object-cover border border-border shrink-0">
                            @else
                                <div class="w-20 h-20 rounded-xl bg-muted border border-border flex items-center justify-center text-xs text-text-muted shrink-0">No photo</div>
                            @endif

                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs font-bold text-primary">{{ $reservation->reservation_code }}</span>
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-{{ $reservation->status->tone() }}-light text-{{ $reservation->status->tone() }} font-semibold">
                                        {{ $reservation->status->label() }}
                                    </span>
                                    <span class="text-xs text-text-muted uppercase font-semibold">
                                        {{ $reservation->facility->facilityType?->name }}
                                    </span>
                                </div>
                                <h4 class="font-heading text-lg font-bold text-text">{{ $reservation->facility->name }}</h4>
                                <p class="text-xs text-text-muted">
                                    {{ $reservation->check_in->format('M d, Y g:i A') }} &ndash; {{ $reservation->check_out->format('M d, Y g:i A') }}
                                    · {{ $reservation->guest_count }} guests
                                    @if($reservation->early_check_in_hours > 0) · +{{ $reservation->early_check_in_hours }}h early @endif
                                </p>
                                @if($reservation->checked_in_at)
                                    <p class="text-xs text-success font-medium">Checked in at {{ $reservation->checked_in_at->format('M d, Y g:i A') }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-4 self-end md:self-center">
                            <div class="text-right">
                                <span class="text-[11px] text-text-muted uppercase font-medium">Line Quote</span>
                                <p class="font-heading text-base font-extrabold text-text"><x-amount :value="$reservation->total_price" /></p>
                            </div>

                            <div class="flex items-center gap-2">
                                @if($reservation->status === \App\Enums\ReservationStatus::Approved && ! $reservation->checked_in_at)
                                    <form method="POST" action="{{ route('admin.reservations.check-in', $reservation) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-primary text-white font-semibold hover:bg-primary-hover text-xs">
                                            Check-in
                                        </button>
                                    </form>
                                @endif

                                @if($reservation->checked_in_at && ! $reservation->checked_out_at)
                                    <form method="POST" action="{{ route('admin.reservations.check-out', $reservation) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-text text-white font-semibold hover:bg-text/80 text-xs">
                                            Check-out
                                        </button>
                                    </form>
                                @endif

                                <a href="{{ route('admin.reservations.show', $reservation) }}" class="px-3 py-1.5 rounded-lg border border-border text-xs font-semibold text-text hover:bg-muted">
                                    Line details &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Group Payment Panel --}}
        <div class="rounded-xl border border-border bg-surface p-6 shadow-xs space-y-4">
            <h3 class="font-heading text-base font-bold text-text">Group Payment Allocation</h3>
            @if($group->payments->isEmpty())
                <p class="text-sm text-text-muted">No payments or transaction proofs filed against this group yet.</p>
            @else
                <div class="divide-y divide-border border border-border rounded-xl overflow-hidden text-xs">
                    @foreach($group->payments as $payment)
                        <div class="p-3.5 flex items-center justify-between bg-surface">
                            <div>
                                <span class="font-bold text-text">Payment #{{ $payment->id }} · {{ $payment->payment_method ?? 'Proof upload' }}</span>
                                <p class="text-text-muted mt-0.5">Reference: {{ $payment->reference_number ?? 'None' }} · {{ $payment->created_at->format('M d, Y g:i A') }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-bold text-sm text-text"><x-amount :value="$payment->amount" /></span>
                                <span class="px-2 py-0.5 rounded-full font-bold uppercase text-[10px] bg-{{ $payment->status === \App\Enums\PaymentStatus::Verified ? 'success' : 'warning' }}-light text-{{ $payment->status === \App\Enums\PaymentStatus::Verified ? 'success' : 'warning' }}">
                                    {{ $payment->status->value }}
                                </span>
                                <a href="{{ route('admin.payments.show', $payment) }}" class="text-primary hover:underline font-semibold">Review</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>
</x-admin-layout>
