{{-- Executive: the headline figures first, then what explains them. Each tile
     names the date its number is measured on — a booking total and a cash total
     are different money, and reading one as the other is the expensive mistake
     this screen exists to prevent. --}}
<section aria-labelledby="executive-kpis" class="mb-8">
    <h2 id="executive-kpis" class="sr-only">Executive key performance indicators</h2>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat
            label="Booking requests"
            :value="number_format($bookingSummary['total'])"
            basis="Reservation creation date"
            definition="Recorded booking requests in the selected period."
            note="All reservation requests created during the selected dates"
            :change="$reservationChange"
            :comparison-missing="$comparison !== null"
        />

        <x-admin.stat
            label="Value of bookings"
            :basis="$metricDefinitions['booking_value']['basis']"
            :definition="$metricDefinitions['booking_value']['definition']"
            note="The full agreed price, whether paid or not"
            :change="$valueChange"
            :comparison-missing="$comparison !== null"
        ><x-amount :value="$revenueSummary['booking_value']" /></x-admin.stat>

        <x-admin.stat
            label="Payments received"
            :basis="$metricDefinitions['cash_collected']['basis']"
            :definition="$metricDefinitions['cash_collected']['definition']"
            note="Only payments verified by staff as received"
        ><x-amount :value="$revenueSummary['verified_payment_revenue']" /></x-admin.stat>

        <x-admin.stat
            label="Still unpaid"
            :basis="$metricDefinitions['outstanding_balance']['basis']"
            :definition="$metricDefinitions['outstanding_balance']['definition']"
            note="Amount guests still owe on active bookings"
        ><x-amount :value="$revenueSummary['outstanding_balance']" /></x-admin.stat>
    </div>
</section>

{{-- Alerts are the reason to look at a report twice: each one names the records
     that need a person, and links straight to them rather than to a list the
     reader then has to filter by hand. --}}
<x-admin.panel
    class="mb-8"
    title="Items that need attention"
    :badge="$alerts['total']"
    description="Situations that need a staff decision; a zero here is the healthy state."
>
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
        <div>
            <p class="text-sm font-semibold text-text">Completed stays still unpaid</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-text">{{ $alerts['outstanding_completed']->count() }}</p>
            @forelse ($alerts['outstanding_completed']->take(3) as $reservation)
                <a href="{{ route('admin.reservations.show', $reservation) }}" class="mt-1 block rounded text-xs font-medium text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">{{ $reservation->reservation_code }}</a>
            @empty
                <p class="mt-1 text-xs text-text-muted">Nothing to review.</p>
            @endforelse
        </div>

        <div>
            <p class="text-sm font-semibold text-text">Payment references used more than once</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-text">{{ $alerts['duplicate_references']->count() }}</p>
            @if ($alerts['duplicate_references']->isEmpty())
                <p class="mt-1 text-xs text-text-muted">Nothing to review.</p>
            @else
                <a href="{{ route('admin.payments.index') }}" class="mt-1 inline-block rounded text-xs font-medium text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">Review payment records</a>
            @endif
        </div>

        <div>
            <p class="text-sm font-semibold text-text">Payments on closed bookings</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-text">{{ $alerts['verified_cancelled']->count() }}</p>
            @forelse ($alerts['verified_cancelled']->take(3) as $payment)
                <a href="{{ route('admin.payments.show', $payment) }}" class="mt-1 block rounded text-xs font-medium text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">Payment #{{ $payment->id }}</a>
            @empty
                <p class="mt-1 text-xs text-text-muted">Nothing to review.</p>
            @endforelse
        </div>

        <div>
            <p class="text-sm font-semibold text-text">Extra charges missing a category</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-text">{{ number_format($alerts['unclassified_ancillary_count']) }}</p>
            <p class="mt-1 text-xs text-text-muted">Reservations with additional charges that lack line-item categories.</p>
        </div>
    </div>
</x-admin.panel>

<div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-3">
    <x-admin.stat
        label="Cancellation rate"
        :value="$metricValue($demandMetrics['cancellation_rate'], '%')"
        :note="number_format($demandMetrics['cancelled']).' cancelled of '.number_format($demandMetrics['total']).' created'"
    />

    <x-admin.stat
        label="Returning online guests"
        :value="$metricValue($demandMetrics['repeat_guest_rate'], '%')"
        note="Stable online account IDs only; walk-ins are not guessed"
    />

    <x-admin.stat
        label="Average guest rating"
        :value="$demandMetrics['average_rating'] === null ? 'Not enough data' : number_format($demandMetrics['average_rating'], 1).' / 5'"
        :note="number_format($demandMetrics['review_count']).' recorded rating responses; not NPS'"
    />
</div>

<div class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
    <x-admin.report-chart :chart="$charts['reservations']" />
    <x-admin.report-chart :chart="$charts['sources']" />
</div>

{{-- Seasonality and deposit health are the two decision-making reads that no
     other panel covers: when demand lands, and whether the down payment the
     guest flow promises is actually being collected. --}}
@include('admin.reports.partials.seasonality')
@include('admin.reports.partials.deposit-health')

<div class="mb-8 grid grid-cols-1 gap-6 xl:grid-cols-3">
    {{-- Three panels, one per question: what state are the bookings in, what did
         we sell, and what sold on its own. The last two are also shown by the
         Financial view, so they come from a shared partial. --}}
    @include('admin.reports.partials.status-breakdown')
    @include('admin.reports.partials.popularity')
</div>

<x-admin.panel
    title="Payments by status"
    description="How many payment records are waiting, verified, or rejected, using the date each payment was recorded."
    :padding="false"
>
    <x-admin.table plain>
        <x-admin.table-head>
            <x-admin.table-cell :isHeader="true">Status</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true" numeric>Count</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true" numeric>Amount</x-admin.table-cell>
        </x-admin.table-head>
        <x-admin.table-body>
            @foreach (\App\Enums\PaymentStatus::cases() as $status)
                @php($row = $paymentSummary['by_status'][$status->value] ?? ['count' => 0, 'total_amount' => 0])
                <x-admin.table-row>
                    <x-admin.table-cell><x-badge :status="$status" /></x-admin.table-cell>
                    <x-admin.table-cell numeric>{{ number_format($row['count']) }}</x-admin.table-cell>
                    <x-admin.table-cell numeric><x-amount :value="$row['total_amount']" /></x-admin.table-cell>
                </x-admin.table-row>
            @endforeach
        </x-admin.table-body>
    </x-admin.table>
</x-admin.panel>
