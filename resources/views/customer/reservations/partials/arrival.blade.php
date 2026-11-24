@php
    $codeReady = $reservation->canShowArrivalPass();

    // A record without a usable pass still has to say why, or the Arrival tab
    // reads as an empty page instead of "not yet". The answer lives on the model
    // so this tab and the printable page behind a saved link cannot disagree.
    [$blockedTitle, $blockedBody] = $reservation->arrivalPassNotice();
@endphp

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
    <div class="md:col-span-2 space-y-6">
        @if($codeReady)
            {{-- The one thing a guest needs at the desk, and the arrival time they
                 need on the way. Both are instructions, which is why they live on
                 this facet rather than in the stay's record. --}}
            <div class="bg-primary text-white rounded-2xl shadow-sm px-5 py-5 sm:px-6">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/70">For check in</p>
                <h2 class="mt-2 font-heading text-2xl font-semibold">Show this at the front desk</h2>
                <p class="mt-2 font-mono text-2xl sm:text-3xl font-bold tracking-wide">{{ $reservation->reservation_code }}</p>
                <p class="mt-2 text-sm text-white/80">You do not need to sign in at the resort. Staff can look this code up, or just the last 6 characters.</p>
                <p class="mt-4 text-sm font-semibold">
                    Arrive from {{ $reservation->check_in->format('g:i A') }} on {{ $reservation->check_in->format('D, M d, Y') }}.
                    <span class="font-normal text-white/80">Check-out is by {{ $reservation->check_out->format('g:i A') }} on {{ $reservation->check_out->format('D, M d, Y') }}.</span>
                </p>
                <a href="{{ $reservation->arrivalPassUrl() }}" class="mt-4 inline-flex items-center justify-center min-h-[44px] px-4 rounded-lg bg-white text-primary text-sm font-semibold hover:bg-white/90">
                    Open check in pass
                </a>
            </div>
        @else
            <div class="bg-surface border border-border rounded-2xl shadow-sm px-5 py-5 sm:px-6">
                <h2 class="font-heading text-2xl font-semibold text-text">{{ $blockedTitle }}</h2>
                <p class="mt-2 text-sm text-text-muted">{{ $blockedBody }}</p>
                <a href="{{ config('resort.phone_href') }}" class="mt-4 inline-flex font-semibold text-primary hover:underline">{{ config('resort.phone') }}</a>
            </div>
        @endif
    </div>

    <div class="space-y-6 self-start">
        <div class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 sm:px-6 border-b border-border">
                <h2 class="font-heading text-2xl font-semibold text-text">What to bring</h2>
            </div>
            <div class="p-5 sm:p-6 space-y-3 text-sm text-text-muted">
                @if($reservation->guest_valid_id)
                    <p>Bring the ID on this booking: <span class="font-semibold text-text">{{ $reservation->guest_valid_id->label() }}</span>.</p>
                @else
                    <p>Bring a government-issued ID. The front desk records it on your guest sheet.</p>
                @endif
                @if(count($reservation->otherGuests()) > 0)
                    <p>If a companion arrives before you, the ID type on your guest sheet is what lets staff check them in. Both are on the <a href="{{ \App\Enums\ReservationRecordView::Stay->url($reservation) }}" class="font-semibold text-primary hover:underline">Your stay</a> tab.</p>
                @endif
                <p>Anyone on the booking can be checked in at the desk with this reservation code.</p>
            </div>
        </div>

        <div class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 sm:px-6 border-b border-border">
                <h2 class="font-heading text-2xl font-semibold text-text">Need help?</h2>
            </div>
            <div class="p-5 sm:p-6 text-sm text-text-muted space-y-2">
                <p>Staff can look your booking up by code and change the details with you.</p>
                <p>
                    <a href="{{ config('resort.phone_href') }}" class="font-semibold text-primary hover:underline">{{ config('resort.phone') }}</a>
                </p>
            </div>
        </div>
    </div>
</div>
