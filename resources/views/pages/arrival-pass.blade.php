@php
    // A saved pass link outlives the state it was issued in: it stays valid until
    // two weeks after check-out. So the page asks the same question the customer's
    // own Arrival tab asks, and answers "no pass" with the same words rather than
    // printing a slip for a booking that is not confirmed and paid.
    $passReady = $reservation->canShowArrivalPass();

    [$noticeTitle, $noticeBody] = $reservation->arrivalPassNotice();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ $passReady ? 'Check in pass '.$reservation->reservation_code : 'No check in pass yet' }} · {{ config('resort.short_name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            @media print {
                .no-print { display: none !important; }
                body { background: white !important; }
            }
        </style>
    </head>
    <body class="min-h-screen bg-background font-sans text-text antialiased">
        <div class="max-w-lg mx-auto px-4 py-8 sm:py-12">
            <div class="no-print mb-6 flex items-center justify-between gap-3">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-sm font-medium text-text-muted hover:text-primary">
                    <img src="{{ asset('images/logo-villa-mercedes.png') }}" alt="" class="h-8 w-auto">
                    {{ config('resort.short_name') }}
                </a>
                @if($passReady)
                    <button type="button" onclick="window.print()" class="text-sm font-semibold text-primary hover:underline">
                        Print or save
                    </button>
                @endif
            </div>

            @if(! $passReady)
                {{-- Nothing to print and nothing to show at the desk, so the page
                     is a notice rather than a document with a warning on it. --}}
                <article class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
                    <div class="bg-muted px-6 py-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-text-muted">Check in pass</p>
                        <p class="mt-2 font-heading text-3xl font-semibold text-text">{{ $noticeTitle }}</p>
                    </div>

                    <div class="px-6 py-6 space-y-4">
                        <p class="text-sm text-text">{{ $noticeBody }}</p>
                        <p class="text-sm text-text-muted">Your reservation code is in your confirmation email, and staff can look the booking up with its last 6 characters.</p>
                        @if(config('resort.phone'))
                            <p class="text-sm text-text-muted">
                                Need help? <a href="{{ config('resort.phone_href') }}" class="font-semibold text-primary hover:underline">{{ config('resort.phone') }}</a>
                            </p>
                        @endif
                    </div>
                </article>
            @else
                <article class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
                    <div class="bg-primary text-white px-6 py-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/70">Check in pass</p>
                        <p class="mt-2 font-heading text-3xl font-semibold">Show this at the desk</p>
                        <p class="mt-2 text-sm text-white/80">No sign-in needed. Staff will look up your reservation code.</p>
                    </div>

                    <div class="px-6 py-6 text-center border-b border-border">
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Reservation code</p>
                        <p class="mt-2 font-mono text-2xl sm:text-3xl font-bold tracking-wide text-primary">{{ $reservation->reservation_code }}</p>
                        <p class="mt-2 text-sm text-text-muted">Last 6 characters are enough: <span class="font-mono font-semibold text-text">{{ substr($reservation->reservation_code, -6) }}</span></p>
                        <div class="mt-4">
                            <x-badge :status="$reservation->status" />
                            <p class="mt-2 text-sm font-semibold text-text">Status: {{ $reservation->status->label() }}</p>
                        </div>
                    </div>

                    <dl class="px-6 py-5 space-y-4">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Guest</dt>
                            <dd class="mt-1 text-base font-medium text-text">{{ $reservation->guestName() }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Stay</dt>
                            <dd class="mt-1 text-base font-medium text-text">{{ $reservation->facility->name }}</dd>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Check-in</dt>
                                <dd class="mt-1 text-sm text-text">{{ $reservation->check_in->format('D, M j') }}<br>{{ $reservation->check_in->format('g:i A') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Check-out</dt>
                                <dd class="mt-1 text-sm text-text">{{ $reservation->check_out->format('D, M j') }}<br>{{ $reservation->check_out->format('g:i A') }}</dd>
                            </div>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Guests</dt>
                            <dd class="mt-1 text-sm text-text">{{ $reservation->guest_count }}</dd>
                        </div>
                        @if($reservation->maskedGuestId())
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Valid ID type</dt>
                            <dd class="mt-1 text-sm text-text">{{ $reservation->maskedGuestId() }}</dd>
                        </div>
                        @endif
                        @if(count($reservation->otherGuests()) > 0)
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Other guests</dt>
                            <dd class="mt-1 text-sm text-text">
                                {{ collect($reservation->otherGuests())->map(function (array $companion): string {
                                    return $companion['valid_id_label']
                                        ? $companion['name'].' ('.$companion['valid_id_label'].')'
                                        : $companion['name'];
                                })->implode(', ') }}
                            </dd>
                        </div>
                        @endif
                    </dl>
                </article>

                <p class="no-print mt-6 text-center text-sm text-text-muted">
                    Save this page or take a screenshot before you travel.
                    @if(config('resort.phone'))
                        Questions? <a href="{{ config('resort.phone_href') }}" class="text-primary font-medium hover:underline">{{ config('resort.phone') }}</a>
                    @endif
                </p>
            @endif
        </div>
    </body>
</html>
