<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>Arrival pass {{ $reservation->reservation_code }} · {{ config('resort.short_name') }}</title>
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
                <button type="button" onclick="window.print()" class="text-sm font-semibold text-primary hover:underline">
                    Print or save
                </button>
            </div>

            <article class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
                <div class="bg-primary text-white px-6 py-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/70">Arrival pass</p>
                    <p class="mt-2 font-heading text-3xl font-semibold">Show this at the desk</p>
                    <p class="mt-2 text-sm text-white/80">No sign-in needed. Staff will look up your reservation code.</p>
                </div>

                @if($reservation->status === \App\Enums\ReservationStatus::Pending)
                    <div class="px-6 py-4 bg-amber-50 border-b border-amber-200 text-amber-950">
                        <p class="text-sm font-semibold">Awaiting approval</p>
                        <p class="mt-1 text-sm">This is a reservation request, not a confirmed booking. Show it only after the resort confirms, or ask the desk to check the request.</p>
                    </div>
                @endif

                <div class="px-6 py-6 text-center border-b border-border">
                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Reservation code</p>
                    <p class="mt-2 font-mono text-2xl sm:text-3xl font-bold tracking-wide text-primary">{{ $reservation->reservation_code }}</p>
                    <p class="mt-2 text-sm text-text-muted">Last 6 characters are enough: <span class="font-mono font-semibold text-text">{{ substr($reservation->reservation_code, -6) }}</span></p>
                    <div class="mt-4">
                        <x-badge :status="$reservation->status->label()" />
                        <p class="mt-2 text-sm font-semibold text-text">
                            Status:
                            @if($reservation->status === \App\Enums\ReservationStatus::Pending)
                                Pending — awaiting approval
                            @else
                                {{ $reservation->status->label() }}
                            @endif
                        </p>
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
                    @if(count($reservation->otherGuestNames()) > 0)
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Other guests</dt>
                        <dd class="mt-1 text-sm text-text">{{ implode(', ', $reservation->otherGuestNames()) }}</dd>
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
        </div>
    </body>
</html>
