@if ($payment->source === 'customer_deposit')
    @php
        $duplicates = ($payment->reference_number || $payment->proof_hash) ? \App\Models\Payment::where('id', '!=', $payment->id)->where(function ($q) use ($payment) {
            if ($payment->reference_number) {
                $q->where(fn($ref) => $ref->where('method', $payment->method->value)->where('reference_number', $payment->reference_number));
            }
            if ($payment->proof_hash) {
                $q->orWhere('proof_hash', $payment->proof_hash);
            }
        })->count() : 0;

        // One rule, stated in words here and counted for the sidebar badge by
        // LayoutBadges::overduePaymentCount().
        $overdue = $payment->isOverdueForReview();

        $res = $payment->reservation;
        $grp = $payment->reservationGroup;
        $canRequestTopUp = $res && in_array($res->status, [\App\Enums\ReservationStatus::Pending, \App\Enums\ReservationStatus::Approved], true)
            && $res->getPendingVerificationTotal() <= 0
            && $res->getDepositAvailableAmount() > 0;
        $requiredDeposit = $res ? $res->requiredDepositAmount() : ($grp ? $grp->deposit_required : 0);
        $depositDueAt = $res ? $res->deposit_due_at : ($grp ? $grp->deposit_due_at : null);
    @endphp

    <x-admin.panel title="Deposit review"
        description="This proof holds the guest's space until it is verified or rejected, so reconcile it against the receiving account before anything else.">
        @if ($overdue)
            <div class="mb-4">
                <x-admin.notice variant="danger" title="Overdue review — reconcile this transfer now" :dismissible="false">
                    The proof has been waiting longer than {{ config('payments.review_hours') }} hours and the guest's
                    availability stays held until it is resolved.
                </x-admin.notice>
            </div>
        @endif

        @if ($duplicates > 0)
            <div class="mb-4">
                <x-admin.notice variant="warning"
                    title="{{ $duplicates }} other {{ \Illuminate\Support\Str::plural('submission', $duplicates) }} use this reference or receipt"
                    :dismissible="false">
                    Check for duplicate funds before verifying. Two payments against one transfer must not both be marked
                    verified.
                </x-admin.notice>
            </div>
        @endif

        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Required deposit</dt>
                <dd class="mt-1 text-sm font-semibold text-text"><x-amount :value="$requiredDeposit" /></dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Proof received</dt>
                <dd class="mt-1"><x-admin.date-time :value="$payment->created_at" format="datetime" /></dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Deposit deadline</dt>
                <dd class="mt-1 text-sm text-text">
                    @if ($depositDueAt)
                        {{ $depositDueAt->copy()->timezone(\App\Support\ResortTime::timezone())->format('M j, Y g:i A') }}
                        Philippine time
                    @else
                        Staff arranged
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Account shown to the guest</dt>
                <dd class="mt-1 text-sm text-text">
                    {{ $payment->destination_snapshot['account_name'] ?? 'Not recorded' }}
                    @if (!empty($payment->destination_snapshot['account_number']))
                        <span
                            class="mt-0.5 block font-mono text-xs text-text-muted">{{ $payment->destination_snapshot['account_number'] }}</span>
                    @endif
                </dd>
            </div>
        </dl>

        @if ($canRequestTopUp)
            <form method="POST" action="{{ route('admin.payments.top-up', $payment->reservation) }}"
                class="mt-4 border-t border-border pt-4">
                @csrf

                <label for="top_up_reason" class="admin-label">Top-up instructions for the guest <span class="text-danger"
                        aria-hidden="true">*</span></label>
                <textarea id="top_up_reason" name="reason" rows="2" maxlength="500" required aria-required="true" {{--
                    vocabulary-guard: example wording inside a placeholder attribute, which cannot contain an element --}}
                    placeholder="e.g. The transfer covered ₱1,000 of the ₱3,000 deposit." class="admin-input mt-1"></textarea>
                <p class="admin-help">The guest sees this wording, so state the amount still owed and the deadline.</p>

                <div class="mt-3 flex justify-end">
                    <x-admin.button type="submit" variant="secondary">Request deposit top-up</x-admin.button>
                </div>
            </form>
        @endif
    </x-admin.panel>
@endif