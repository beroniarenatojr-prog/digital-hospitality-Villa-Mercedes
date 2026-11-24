{{--
    The price of the booking in hand, and how it got there.

    Both places the wizard quotes a total — the breakdown on the confirm step and
    the summary beside the form — render this, so the two can never describe the
    same total two different ways. It carries more than a figure:

    - one line per component, with the arithmetic it is made of when that
      arithmetic is exact, because a total a guest can check is a total they will
      not ring about. The wording and the figures both come from
      `resources/js/price-change.js`, so this file invents none of them;
    - whether the number is *our* estimate from the published rates or the resort's
      confirmed total, since the two can differ and a guest is owed the difference
      before they pay a deposit, not after;
    - and, whenever the total moves, what it was, what it is now, and what moved it.
      The reasons come from the guest's own edits, plus the resort's rate when a
      component moved that the guest never touched — which is the one change that
      would otherwise happen in silence.

    `deposit` adds the 50% deposit line the confirm step states; `heading` sizes
    the total for the space it sits in.
--}}
@props(['deposit' => false, 'compact' => false])

<div class="space-y-3 text-sm">
    <template x-for="term in terms" :key="term.key">
        <div class="flex justify-between items-start gap-3">
            <span class="text-text-muted">
                <span x-text="term.label"></span>
                <span class="block text-xs text-text-muted" x-show="term.detail" x-cloak x-text="term.detail"></span>
            </span>
            <span class="font-medium text-text tabular-nums"><span x-text="pesosFromCents(term.amount)"></span></span>
        </div>
    </template>

    <div class="flex justify-between items-center pt-3 border-t border-border">
        <span class="font-display font-bold text-text text-lg">Total</span>
        <span class="font-display font-extrabold text-primary tabular-nums {{ $compact ? 'text-xl' : 'text-2xl' }}"><span x-text="pesosFromCents(displayPrice.total_amount)"></span></span>
    </div>

    <p class="text-xs text-text-muted" x-show="!quoted" x-cloak>Estimate from published rates. The confirmed total appears after you check availability.</p>
    <p class="text-xs text-success" x-show="quoted" x-cloak>Confirmed total for these dates, guests and hours.</p>

    <div class="rounded-lg border border-border bg-background px-3 py-2" x-show="changeNote" x-cloak>
        <p class="text-xs font-semibold text-accent" x-text="changeNoteHeadline"></p>
        <p class="text-xs text-text-muted mt-0.5" x-show="changeNoteReasons" x-cloak x-text="changeNoteReasons"></p>
    </div>

    @if($deposit)
        <div class="flex justify-between items-center text-sm">
            <span class="text-text-muted">Required deposit for standard bookings (50%)</span>
            <span class="font-medium text-text tabular-nums"><span x-text="pesosFromCents(suggestedDown)"></span></span>
        </div>
    @endif
</div>
