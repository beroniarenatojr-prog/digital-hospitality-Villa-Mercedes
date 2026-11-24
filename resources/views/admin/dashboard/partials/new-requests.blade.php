@php
    // One fingerprint of every fact this panel prints, including the order the
    // requests are read in. The browser compares it between polls, so a poll
    // that found nothing new leaves the list alone instead of rebuilding it
    // under the reader — no flicker, and nothing to re-read. It is hashed only
    // to keep the attribute short; nothing reads its contents.
    $signature = md5($requestsToday->map(fn ($reservation) => implode(':', [
        $reservation->id,
        $reservation->status->value,
        $reservation->updated_at?->getTimestamp() ?? '',
        $reservation->guestName(),
        $reservation->guest_name && $reservation->guest_name !== $reservation->user?->name ? $reservation->user?->name : '',
        $reservation->facility?->name,
        $reservation->check_in?->toDateTimeString() ?? '',
        $reservation->check_out?->toDateTimeString() ?? '',
        $reservation->guest_count,
    ]))->implode('|'));
@endphp

{{-- The refresh target: everything a poll replaces, and nothing it does not.
     The count rides along in the same answer as the rows, so the number beside
     the panel title is never a poll behind the list under it. --}}
<div
    data-live-panel-body
    data-panel-count="{{ $requestsToday->count() }}"
    data-panel-signature="{{ $signature }}"
>
    @forelse ($requestsToday as $reservation)
        @php
            $requestPayload = [
                'id' => $reservation->id,
                'code' => $reservation->reservation_code,
                'guest' => $reservation->guestName(),
                'email' => $reservation->guestEmail() ?? 'None on file',
                'phone' => $reservation->guestPhone() ?? 'None on file',
                'bookedBy' => ($reservation->guest_name && $reservation->guest_name !== $reservation->user?->name) ? $reservation->user?->name : null,
                'facility' => $reservation->facility->name,
                'facilityType' => $reservation->facility->facilityType?->name ?? 'Accommodation',
                'checkIn' => $reservation->check_in->format('M j, Y g:i A'),
                'checkOut' => $reservation->check_out->format('M j, Y g:i A'),
                'guests' => $reservation->guest_count,
                'total' => \App\Support\Money::format($reservation->total_price),
                'specialRequests' => $reservation->special_requests,
                'status' => $reservation->status->label(),
                'statusValue' => $reservation->status->value,
                'statusTone' => $reservation->status->tone(),
                'approveUrl' => route('admin.reservations.approve', $reservation),
                'rejectUrl' => route('admin.reservations.reject', $reservation),
                'showUrl' => route('admin.reservations.show', $reservation),
            ];
        @endphp
        <a
            href="{{ route('admin.reservations.show', $reservation) }}"
            data-live-row="{{ $reservation->id }}"
            @click.prevent="$dispatch('open-request-drawer', @js($requestPayload))"
            class="flex items-center gap-3 border-b border-border px-5 py-3.5 transition-colors duration-150 last:border-b-0 hover:bg-primary-light/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary"
        >
            <span class="w-16 shrink-0 text-sm font-semibold tabular-nums text-primary">{{ $reservation->created_at->copy()->timezone(\App\Support\ResortTime::timezone())->format('g:i A') }}</span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold text-text">
                    {{ $reservation->guestName() }}@if ($reservation->guest_name && $reservation->guest_name !== $reservation->user?->name) · booked by {{ $reservation->user->name }}@endif
                </span>
                <span class="block truncate text-xs text-text-muted">
                    {{ $reservation->facility->name }} · <x-admin.date-range :from="$reservation->occupancyStart()" :to="$reservation->occupancyEnd()" :time="false" /> · {{ $reservation->guest_count }} {{ \Illuminate\Support\Str::plural('guest', $reservation->guest_count) }}
                </span>
            </span>

            {{-- Rendered with every row and revealed only for a request that
                 arrived while this screen was open, so "new" means new to the
                 person reading rather than new to the list. --}}
            <span data-live-row-new class="hidden shrink-0 rounded-full bg-primary-light px-2 py-0.5 text-xs font-semibold text-primary">New</span>

            <span class="hidden sm:inline-flex items-center rounded border border-border px-2 py-0.5 text-xs font-semibold text-text hover:bg-muted">
                Review
            </span>

            <x-badge :status="$reservation->status" variant="compact" />
        </a>
    @empty
        <p class="px-5 py-4 text-sm text-text-muted">No new requests today</p>
    @endforelse
</div>
