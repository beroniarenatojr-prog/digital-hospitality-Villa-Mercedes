{{--
    One unfinished booking, on "My reservations".

    It has to read as *not* a reservation at a glance — dashed border, "Not
    submitted", no money, no deposit — because the guest is looking at a page full
    of bookings that are real, and this one is a form they left open. What it does
    carry is what they would recognise as their own work, and one button that puts
    them back on the step they left.
--}}
<article class="rounded-2xl border border-dashed border-primary/40 bg-surface p-5 shadow-sm sm:p-6">
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-4">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-accent">Not submitted</p>
            <h3 class="mt-1 font-heading text-xl font-semibold text-text">
                <a href="{{ $booking->resumeUrl() }}" class="hover:text-primary focus:outline-none focus:underline">
                    {{ $booking->facility->name }}
                </a>
            </h3>
            <p class="mt-1 text-sm font-medium text-text">{{ $booking->progressLabel() }}</p>

            <dl class="mt-4 flex flex-wrap gap-x-8 gap-y-3 text-sm">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Dates</dt>
                    <dd class="mt-0.5 text-text">
                        {{ $booking->checkIn->format('M d, Y g:i A') }}
                        <span class="text-text-muted">to</span>
                        {{ $booking->checkOut->format('M d, Y g:i A') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Guests</dt>
                    <dd class="mt-0.5 text-text">{{ $booking->guestCount }}</dd>
                </div>
                @if($booking->companionCount > 0)
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Companions saved</dt>
                        <dd class="mt-0.5 text-text">{{ $booking->companionCount }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        <div class="flex flex-col items-stretch gap-3 sm:items-end">
            <x-primary-button tag="a" :href="$booking->resumeUrl()" class="justify-center">
                {{ $booking->isReadyToConfirm() ? 'Finish this booking' : 'Continue this booking' }}
            </x-primary-button>

            {{--
                A discard throws away typing the guest did by hand, so it asks
                first: a mis-tap next to "Continue" must not cost them the booking.
            --}}
            <form
                method="POST"
                action="{{ route('customer.booking.draft.destroy', $booking->facility) }}"
                x-data
                @submit="if (! confirm('Discard this unfinished booking? Your dates and companion names will be cleared.')) $event.preventDefault()"
            >
                @csrf
                @method('DELETE')
                <x-secondary-button type="submit" class="w-full justify-center">
                    Discard
                </x-secondary-button>
            </form>
        </div>
    </div>
</article>
