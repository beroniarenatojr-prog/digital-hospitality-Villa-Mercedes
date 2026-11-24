@php
    $fdLines = $lines->filter(fn ($line) => $line->status === \App\Enums\ReservationStatus::Approved && ! $line->checked_in_at && ! isset($workflow['refund']));
@endphp
@if ($fdLines->isNotEmpty())
@if($groupWorkspace ?? false)
    <details id="front-desk-adjustments" class="scroll-mt-24 rounded-xl border border-border bg-surface" @if($errors->any() && in_array(old('action'), ['review_arrival', 'arrival_discount'], true)) open @endif>
        <summary class="cursor-pointer rounded-xl px-5 py-4 font-heading text-lg font-bold text-text focus-visible:outline-primary">Front desk adjustments</summary>
        <div class="space-y-4 border-t border-border p-5">
@else
    <section id="front-desk-adjustments" aria-labelledby="front-desk-adjustments-title" class="mb-6 scroll-mt-24 rounded-xl border border-border bg-surface p-5">
        <h2 id="front-desk-adjustments-title" class="font-heading text-lg font-bold text-text">Front desk adjustments</h2>
@endif
        <p class="mt-1 text-sm text-text-muted">Review early check-in charges or record a discount for a service before check-in.</p>

        <div class="mt-4 divide-y divide-border border-y border-border">
            @foreach ($fdLines as $fdLine)
                @php
                    $discountFailed = old('action') === 'arrival_discount' && (int) old('adjustment_line') === $fdLine->id && $errors->any();
                @endphp
                <section >
                    <div class="px-4 py-3 text-sm font-semibold text-text">
                        {{ $fdLine->facility->name }}
                        <span class="ml-1 text-xs font-normal text-text-muted">
                            {{ $fdLine->check_in->format('g:i A') }}
                        </span>
                    </div>

                    <div class="space-y-4 px-2 pb-4">
                        <form method="POST" action="{{ route('admin.reservations.discussion', $fdLine) }}" class="space-y-2">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="action" value="review_arrival">
                            <p class="text-xs text-text-muted">Early check-in on this booking: {{ $fdLine->early_check_in_hours ?? 0 }} hr</p>
                            <x-admin.button type="submit" variant="secondary">Review early check-in charge</x-admin.button>
                        </form>

                        <form method="POST" action="{{ route('admin.reservations.discussion', $fdLine) }}" class="space-y-3 border-t border-border pt-4">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="action" value="arrival_discount">
                            <input type="hidden" name="adjustment_line" value="{{ $fdLine->id }}">
                            @if($discountFailed)
                                <x-input-error :messages="$errors->all()" />
                            @endif
                            <div>
                                <label for="discount-amount-{{ $fdLine->id }}" class="admin-label">Discount amount (₱)</label>
                                <input id="discount-amount-{{ $fdLine->id }}" name="amount" type="number" step="0.01" min="0.01" required class="admin-input mt-1"
                                    value="{{ $discountFailed ? old('amount') : '' }}">
                            </div>
                            <div>
                                <label for="discount-reason-{{ $fdLine->id }}" class="admin-label">Reason for discount</label>
                                <input id="discount-reason-{{ $fdLine->id }}" name="reason" maxlength="1000" required class="admin-input mt-1"
                                    value="{{ $discountFailed ? old('reason') : '' }}">
                            </div>
                            <x-admin.button type="submit">Record discount</x-admin.button>
                        </form>
                    </div>
                </section>
            @endforeach
        </div>
@if($groupWorkspace ?? false)
        </div>
    </details>
@else
    </section>
@endif
@endif
