@php
    // Contact records and check-in history, with one row per service reservation.
    $guests = $registry['rows'];
    $guestTotal = $registry['guest_total'];
    $partyTotal = $registry['party_total'];
    $rangeLabel = $registry['range_label'];
@endphp

<x-admin.panel
    title="Guest check-in registry"
    :badge="$guestTotal"
    :description="'Guests ' . ($registry['is_live'] ? 'checked in right now' : 'who checked in during ' . $rangeLabel) . '. Lead guest contacts and recorded guest counts for staff reference.'"
>
    <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-admin.stat
            label="Guests"
            :value="number_format($guestTotal)"
            note="People counted across the checked-in stays below"
        />
        <x-admin.stat
            label="Stays"
            :value="number_format($partyTotal)"
            note="One row per service reservation; grouped bookings may span several rows"
        />
        <x-admin.stat
            label="Range"
            :value="$registry['is_live'] ? 'Live' : $rangeLabel"
            :note="$registry['is_live'] ? 'No date range chosen, so this is everyone in house' : 'Selected check-in range'"
        />
    </div>

    <p class="mb-4 text-sm text-text-muted">For payments and checkout, use <a href="{{ route('admin.checked-in.index') }}" class="font-semibold text-primary underline">Checked-in</a>. Counts reflect reservation records, not a verified headcount of everyone on site.</p>

    @if (count($guests) === 0)
        <x-admin.empty
            icon="reservations"
            :title="$registry['is_live'] ? 'No guests currently checked in' : 'No check-ins in this range'"
            description="Widen the check-in range, or clear the filters, to see records."
        />
    @else
        <div class="mt-4 overflow-x-auto rounded-lg border border-border bg-surface">
            <table class="min-w-full border-collapse text-sm">
                <thead class="bg-muted/70">
                    <tr>
                        <x-admin.table-cell :isHeader="true">Reservation</x-admin.table-cell>
                        <x-admin.table-cell :isHeader="true">Guest</x-admin.table-cell>
                        <x-admin.table-cell :isHeader="true">Space</x-admin.table-cell>
                        <x-admin.table-cell :isHeader="true">Type</x-admin.table-cell>
                        <x-admin.table-cell :isHeader="true" numeric>Guests</x-admin.table-cell>
                        <x-admin.table-cell :isHeader="true">Contact number</x-admin.table-cell>
                        <x-admin.table-cell :isHeader="true">Email</x-admin.table-cell>
                        <x-admin.table-cell :isHeader="true">Checked in</x-admin.table-cell>
                        <x-admin.table-cell :isHeader="true">Expected check out</x-admin.table-cell>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($guests as $guest)
                        <tr>
                            <x-admin.table-cell class="font-medium">
                                <a href="{{ route('admin.reservations.show', $guest['reservation_id']) }}" class="rounded text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                    {{ $guest['reservation_code'] }}
                                </a>
                            </x-admin.table-cell>
                            <x-admin.table-cell>{{ $guest['guest_name'] }}</x-admin.table-cell>
                            <x-admin.table-cell>{{ $guest['facility_name'] }}</x-admin.table-cell>
                            <x-admin.table-cell>{{ $guest['facility_type'] }}</x-admin.table-cell>
                            <x-admin.table-cell numeric>{{ $guest['guest_count'] }}</x-admin.table-cell>
                            <x-admin.table-cell>{{ $guest['guest_phone'] ?? 'Not provided' }}</x-admin.table-cell>
                            <x-admin.table-cell>
                                @if ($guest['guest_email'])
                                    <a href="mailto:{{ $guest['guest_email'] }}" class="rounded text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                        {{ $guest['guest_email'] }}
                                    </a>
                                @else
                                    <span class="text-text-muted">Not provided</span>
                                @endif
                            </x-admin.table-cell>
                            <x-admin.table-cell><x-admin.date-time :value="$guest['checked_in_at']" /></x-admin.table-cell>
                            <x-admin.table-cell>{{ $guest['expected_departure_label'] }}</x-admin.table-cell>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="mt-3 text-xs text-text-muted">
            {{ number_format($guestTotal) }} guests across {{ number_format($partyTotal) }} parties, checked in during {{ $rangeLabel }}.
        </p>
    @endif
</x-admin.panel>

@if ($registry['volume_by_day'] !== [])
    <x-admin.panel
        title="Check ins by day"
        description="How many parties arrived each day of the selected range, and how many people arrived in them."
    >
        <div class="mt-4 overflow-x-auto rounded-lg border border-border bg-surface">
            <table class="min-w-full border-collapse text-sm">
                <thead class="bg-muted/70">
                    <tr>
                        <x-admin.table-cell :isHeader="true">Date</x-admin.table-cell>
                        <x-admin.table-cell :isHeader="true" numeric>Parties</x-admin.table-cell>
                        <x-admin.table-cell :isHeader="true" numeric>Guests</x-admin.table-cell>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($registry['volume_by_day'] as $day)
                        <tr>
                            <x-admin.table-cell>{{ $day['label'] }}</x-admin.table-cell>
                            <x-admin.table-cell numeric>{{ number_format($day['parties']) }}</x-admin.table-cell>
                            <x-admin.table-cell numeric>{{ number_format($day['guests']) }}</x-admin.table-cell>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-admin.panel>
@endif
