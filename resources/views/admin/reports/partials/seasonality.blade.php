{{-- Seasonality answers "when do we get busy", which pricing, staffing, and
     stock decisions turn on. The window is fixed at the last twelve whole
     months so a filtered range cannot make any month look like the peak. --}}
@php
    $peak = $seasonality['peak'];
    $quietest = $seasonality['quietest'];
    $maxGuests = collect($seasonality['series'])->max('guests') ?: 1;
@endphp

<x-admin.panel
    class="mb-8"
    title="Peak season and forward demand"
>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat
            label="Peak month"
            :value="$peak ? $peak['label'] : 'Not enough data'"
            :basis="$peak ? number_format($peak['guests']).' guests across '.number_format($peak['parties']).' parties' : 'No stays recorded'"
            :note="$seasonality['peak_lift'] !== null ? 'About '.number_format($seasonality['peak_lift']).'% above the monthly average of '.number_format($seasonality['average_guests']).' guests' : null"
        />

        <x-admin.stat
            label="Quietest month"
            :value="$quietest ? $quietest['label'] : 'Not enough data'"
            :basis="$quietest ? number_format($quietest['guests']).' guests across '.number_format($quietest['parties']).' parties' : 'No stays recorded'"
            note="The month to schedule maintenance, training, and promos in"
        />

        <x-admin.stat
            label="Weekend demand"
            :value="$seasonality['weekend_share'] === null ? 'Not enough data' : number_format($seasonality['weekend_share'], 1).'%'"
            basis="Share of parties arriving Friday to Sunday"
            :note="number_format($seasonality['weekend']['guests']).' weekend guests vs '.number_format($seasonality['weekday']['guests']).' on weekdays'"
        />

        <x-admin.stat
            label="Confirmed guests"
            basis="Stays with a check-in in the window: confirmed and completed only"
            :note="number_format($seasonality['confirmed_parties']).' parties in total'"
        >{{ number_format($seasonality['confirmed_guests']) }}</x-admin.stat>
    </div>

    <x-admin.table class="mt-6">
        <x-admin.table-head>
            <x-admin.table-cell :isHeader="true">Month</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Demand</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true" numeric>Parties</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true" numeric>Guests</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true" numeric>Room and space nights</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true" numeric>Booking value</x-admin.table-cell>
        </x-admin.table-head>
        <x-admin.table-body>
            @foreach ($seasonality['series'] as $month)
                <x-admin.table-row>
                    <x-admin.table-cell class="{{ $month['is_current'] ? 'font-semibold' : '' }}">
                        {{ $month['label'] }}
                        @if ($month['is_current'])
                            <span class="ml-1 text-xs font-normal text-text-muted">current</span>
                        @endif
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <span class="flex h-2 w-full max-w-40 overflow-hidden rounded-sm bg-muted" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) round(($month['guests'] / $maxGuests) * 100) }}" aria-label="{{ $month['label'] }} guests relative to the busiest month">
                            <span class="h-full rounded-sm bg-primary" style="width: {{ (int) round(($month['guests'] / $maxGuests) * 100) }}%"></span>
                        </span>
                    </x-admin.table-cell>
                    <x-admin.table-cell numeric>{{ number_format($month['parties']) }}</x-admin.table-cell>
                    <x-admin.table-cell numeric>{{ number_format($month['guests']) }}</x-admin.table-cell>
                    <x-admin.table-cell numeric>{{ number_format($month['nights']) }}</x-admin.table-cell>
                    <x-admin.table-cell numeric><x-amount :value="$month['booking_value']" /></x-admin.table-cell>
                </x-admin.table-row>
            @endforeach
        </x-admin.table-body>
    </x-admin.table>

    <div class="mt-6 border-t border-border pt-5">
        <h3 class="text-sm font-semibold text-text">Already on the books</h3>
        @if ($seasonality['on_books'] === [])
            <p class="mt-1 text-sm text-text-muted">
                No approved stay is on the books past today, so nothing ahead of you is guaranteed yet. Fill the next few
                weeks before buying stock or adding staff for them.
            </p>
        @else
            <p class="mt-1 text-sm text-text-muted">
                Approved stays that have not happened yet. This is committed demand, not a forecast: it is the floor the
                next months start from.
            </p>
            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
                @foreach ($seasonality['on_books'] as $month)
                    <div class="rounded-lg border border-border p-4">
                        <p class="text-sm font-medium text-text-muted">{{ $month['label'] }}</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums text-text">{{ number_format($month['guests']) }}</p>
                        <p class="text-xs text-text-muted">{{ number_format($month['parties']) }} {{ \Illuminate\Support\Str::plural('party', $month['parties']) }} booked</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-admin.panel>
