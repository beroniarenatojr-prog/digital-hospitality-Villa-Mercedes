<x-customer-layout>
    {{--
        One reservation, one facet at a time.

        This page used to answer everything at once: the check in code, the stay,
        a 60-row guest sheet, a "what happens next" list, a pricing card and a
        payment panel, in two columns, in one scroll. A guest looking for the
        deposit deadline had to work out which of the two money panels was the
        real one. The facets are now separate addresses of the same record —
        what you booked, what to do when you arrive — the money keeps its own
        page, and the only thing shared by both facets is one line saying where
        the booking stands.
    --}}

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <x-customer.page-header
                :breadcrumbs="[
                    ['label' => 'My reservations', 'href' => route('customer.reservations.index', request()->only('tab', 'page'))],
                    ...($reservation->reservationGroup ? [['label' => $reservation->reservationGroup->group_code, 'href' => route('customer.reservation-groups.show', [$reservation->reservationGroup, ...request()->only('tab', 'page')])]] : []),
                    ['label' => $reservation->reservation_code],
                ]"
                :eyebrow="$reservation->facility->name"
                :title="$recordView->label()"
                :description="$recordView->description()"
            >
                <x-slot name="actions">
                    <x-badge :status="$reservation->status" />
                </x-slot>
            </x-customer.page-header>

            <x-customer.reservation-tabs :reservation="$reservation" :record-view="$recordView" />

            @include('customer.reservations.partials.standing', ['reservation' => $reservation])
            @include('customer.reservations.partials.booking-discussions', ['reservation' => $reservation])

            @if($recordView === \App\Enums\ReservationRecordView::Arrival)
                @include('customer.reservations.partials.arrival', ['reservation' => $reservation])
            @else
                @include('customer.reservations.partials.stay', ['reservation' => $reservation])
            @endif

        </div>
    </div>
</x-customer-layout>
