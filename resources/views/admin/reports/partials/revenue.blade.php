@php
    // Three different kinds of money, all true at once: what was booked, what
    // was delivered, and what actually arrived in the account. They are summed
    // from the same rows the Financial chart above draws, so a figure on a tile
    // and its bar can never disagree.
    $financialRows = collect($charts['financial']['rows']);
@endphp

<div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-admin.stat
        label="Value of bookings"
        :basis="$metricDefinitions['booking_value']['basis']"
        :definition="$metricDefinitions['booking_value']['definition']"
        note="Full agreed price of bookings created in the selected dates"
    ><x-amount :value="$financialRows->sum('booking_value')" /></x-admin.stat>

    <x-admin.stat
        label="Value of completed stays"
        :basis="$metricDefinitions['earned_revenue']['basis']"
        :definition="$metricDefinitions['earned_revenue']['definition']"
        note="Booking value counted after the guest checks out"
    ><x-amount :value="$financialRows->sum('earned_revenue')" /></x-admin.stat>

    <x-admin.stat
        label="Payments received"
        :basis="$metricDefinitions['cash_collected']['basis']"
        :definition="$metricDefinitions['cash_collected']['definition']"
        note="Payments staff verified as received in the selected dates"
    ><x-amount :value="$financialRows->sum('cash_collected')" /></x-admin.stat>

    <x-admin.stat
        label="Still unpaid"
        :basis="$metricDefinitions['outstanding_balance']['basis']"
        :definition="$metricDefinitions['outstanding_balance']['definition']"
        note="Amount still owed after verified payments are deducted"
    ><x-amount :value="$revenueSummary['outstanding_balance']" /></x-admin.stat>
</div>

<x-admin.report-chart :chart="$charts['financial']" />
<x-admin.report-chart :chart="$charts['departments']" />

<section class="mb-8" aria-labelledby="room-performance-heading">
    <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0">
            <h2 id="room-performance-heading" class="text-sm font-semibold text-text">Room performance</h2>
            <p class="mt-0.5 max-w-[70ch] text-xs text-text-muted">
                @if ($roomPerformance['uses_today'])
                    All-time filters are selected, so these room figures describe today rather than a period.
                @else
                    Room figures cover <x-admin.date-range :from="$roomPerformance['period_start']" :to="$roomPerformance['period_end']" :time="false" />.
                @endif
                Actual occupancy is only counted where a check-in was recorded; on-books is approved future demand.
            </p>
        </div>

        <x-admin.data-status status="Historical classification incomplete" />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat
            label="Actual occupancy"
            :value="$roomPerformance['applicable'] ? $metricValue($roomPerformance['occupancy'], '%') : 'Not applicable'"
            :basis="$metricDefinitions['occupancy']['basis']"
            :definition="$metricDefinitions['occupancy']['definition']"
            :note="$metricDefinitions['occupancy']['completeness']"
        />

        {{-- A rate is only meaningful once a room night was actually occupied and
             a room rate was actually charged, so the unavailable state is named
             rather than rendered as a zero. --}}
        <x-admin.stat
            label="Average price per occupied room"
            :basis="$metricDefinitions['adr']['basis']"
            :definition="$metricDefinitions['adr']['definition']"
            :note="$metricDefinitions['adr']['completeness']"
        >
            @if (! $roomPerformance['applicable'])
                Not applicable
            @elseif ($roomPerformance['adr'] === null)
                Not enough data
            @else
                <x-amount :value="$roomPerformance['adr']" />
            @endif
        </x-admin.stat>

        <x-admin.stat
            label="Room income per available room"
            :basis="$metricDefinitions['revpar']['basis']"
            :definition="$metricDefinitions['revpar']['definition']"
            :note="$metricDefinitions['revpar']['completeness']"
        >
            @if (! $roomPerformance['applicable'])
                Not applicable
            @elseif ($roomPerformance['revpar'] === null)
                Not enough data
            @else
                <x-amount :value="$roomPerformance['revpar']" />
            @endif
        </x-admin.stat>

        <x-admin.stat
            label="Future rooms already booked"
            :value="$roomPerformance['applicable'] ? $metricValue($roomPerformance['on_books_occupancy'], '%') : 'Not applicable'"
            definition="Approved future room nights divided by currently available room nights."
            note="A forecast of future bookings, not guests currently checked in"
        />
    </div>

    <p class="mt-4 text-xs text-text-muted">
        {{ number_format($roomPerformance['occupied_room_nights']) }} actual occupied ·
        {{ number_format($roomPerformance['on_books_room_nights']) }} on-books ·
        {{ number_format($roomPerformance['available_room_nights']) }} available room nights.
        Historical out-of-service periods are not tracked, so the denominator uses current facility status.
    </p>
</section>

<div class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
    <x-admin.panel
        title="Booking pace indicators"
        description="How far ahead guests book, and how often they cancel."
        :padding="false"
    >
        <dl class="divide-y divide-border">
            <div class="flex items-center justify-between gap-3 px-5 py-3">
                <dt class="text-sm text-text-muted">Average lead time</dt>
                <dd class="text-sm font-semibold tabular-nums text-text">
                    {{ $demandMetrics['average_lead_time'] === null ? 'Not enough data' : number_format($demandMetrics['average_lead_time'], 1).' days' }}
                </dd>
            </div>

            <div class="flex items-center justify-between gap-3 px-5 py-3">
                <dt class="text-sm text-text-muted">Cancellation rate</dt>
                <dd class="text-sm font-semibold tabular-nums text-text">{{ $metricValue($demandMetrics['cancellation_rate'], '%') }}</dd>
            </div>

            @foreach ($demandMetrics['booking_windows'] as $window => $count)
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <dt class="text-sm text-text-muted">{{ $window }}</dt>
                    <dd class="text-sm font-semibold tabular-nums text-text">{{ number_format($count) }}</dd>
                </div>
            @endforeach
        </dl>
    </x-admin.panel>

    @include('admin.reports.partials.status-breakdown')
</div>

<div class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
    @include('admin.reports.partials.popularity')
</div>

<x-admin.report-chart :chart="$charts['sources']" />

