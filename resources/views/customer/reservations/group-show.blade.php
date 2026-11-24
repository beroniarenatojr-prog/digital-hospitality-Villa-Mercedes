<x-customer-layout>
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-10 sm:px-6 lg:px-8">
        <x-customer.page-header title="Stay details" :description="$group->group_code" :breadcrumbs="[['label' => 'My reservations', 'href' => route('customer.reservations.index', request()->only('tab', 'page'))], ['label' => $group->group_code]]" />
        @include('customer.reservations.partials.group-tabs')
        <div class="overflow-hidden rounded-2xl border border-border bg-surface">
            <div class="border-b border-border px-5 py-4 sm:px-6">
                <h2 class="font-heading text-2xl font-semibold">Your booked services</h2>
                @if($group->special_requests)
                    <p class="mt-3 text-sm font-semibold">Requests for the whole booking</p>
                    <p class="whitespace-pre-wrap break-words text-sm">{{ $group->special_requests }}</p>
                @endif
            </div>
            <div class="divide-y divide-border">
                @foreach($group->reservations as $reservation)
                    <section class="space-y-3 px-5 py-5 sm:px-6">
                        <div class="flex flex-wrap items-center justify-between gap-2"><h3 class="font-heading text-xl font-semibold">{{ $reservation->facility->name }}</h3><x-badge :status="$reservation->status" /></div>
                        <p class="text-xs text-text-muted">{{ $reservation->reservation_code }}</p>
                        <dl class="grid gap-4 text-sm sm:grid-cols-3">
                            <div><dt class="text-text-muted">Check-in</dt><dd>{{ $reservation->check_in->format('M d, Y g:i A') }}</dd></div>
                            <div><dt class="text-text-muted">Check-out</dt><dd>{{ $reservation->check_out->format('M d, Y g:i A') }}</dd></div>
                            <div><dt class="text-text-muted">Guests</dt><dd>{{ $reservation->guest_count }}</dd></div>
                            <div><dt class="text-text-muted">Service total</dt><dd><x-amount :value="$reservation->total_price" /></dd></div>
                            @if($reservation->early_check_in_hours > 0)<div><dt class="text-text-muted">Early check-in</dt><dd>{{ $reservation->early_check_in_hours }} hour(s)</dd></div>@endif
                            @if($reservation->late_check_out_hours > 0)<div><dt class="text-text-muted">Approved check out</dt><dd>{{ $reservation->occupancyEnd()->format('M d, Y g:i A') }}</dd></div>@endif
                        </dl>
                        <a href="{{ route('customer.reservations.show', [$reservation, ...request()->only('tab', 'page')]) }}" class="inline-flex min-h-11 items-center font-semibold text-primary underline">View details for {{ $reservation->facility->name }}</a>
                        @if($reservation->special_requests && $reservation->special_requests !== $group->special_requests)
                            <div class="text-sm">
                                <p class="font-semibold">Special request</p>
                                <p class="whitespace-pre-wrap break-words">{{ $reservation->special_requests }}</p>
                            </div>
                        @endif
                        @if($reservation->canShowArrivalPass())<a href="{{ $reservation->arrivalPassUrl() }}" class="inline-flex min-h-11 items-center font-semibold text-primary underline">Check in pass for {{ $reservation->facility->name }}</a>@endif
                        @include('customer.reservations.partials.late-checkout', ['reservation' => $reservation])
                    </section>
                @endforeach
            </div>
        </div>
        @if($group->reservations->isNotEmpty())
            @include('customer.reservations.partials.booking-discussions', ['reservation' => $group->reservations->first()])
        @endif
    </div>
</x-customer-layout>
