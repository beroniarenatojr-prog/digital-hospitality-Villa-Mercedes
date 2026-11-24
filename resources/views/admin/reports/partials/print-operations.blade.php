@php
    $guests = $registry['rows'];
    $rangeLabel = $registry['range_label'];
    $arrivalsDue = $operations['arrivals'];
    $departuresDue = $operations['departures'];
    $maintenanceFacilities = $operations['maintenance_facilities'];
    $operationsSections = $operationsSections ?? ['registry', 'action-lists'];
    $arrivalDays = array_values(array_filter(
        $registry['volume_by_day'],
        fn (array $day): bool => $day['parties'] > 0 || $day['guests'] > 0,
    ));
@endphp

<h1>Operations report</h1>
<p class="meta">
    Operations report generated {{ \App\Support\ResortTime::now()->format('M j, Y g:i A T') }}<br>
    Check-in range: {{ $startDate?->format('Y-m-d') ?? 'All' }} – {{ $endDate?->format('Y-m-d') ?? 'All' }}
    · Facility type: {{ $filters->facilityType ? \App\Enums\FacilityType::from($filters->facilityType)->label() : 'All' }}
    · Source: {{ $filters->source ? \App\Enums\ReservationSource::from($filters->source)->label() : 'All' }}<br>
    Current occupancy and today's action lists are live snapshots and ignore the check-in range above.
</p>

@if (in_array('registry', $operationsSections, true))
<section class="report-section">
<h2>Guest check-in registry</h2>
<p class="meta">
    {{ number_format($registry['guest_total']) }} guests across {{ number_format($registry['party_total']) }} parties,
    checked in during {{ $rangeLabel }}. Counts come from service reservations; they are not a verified on-site headcount.
</p>

<table>
    <thead>
        <tr>
            <th>Reservation</th><th>Guest</th><th>Space</th><th>Type</th><th class="num">Guests</th>
            <th>Contact number</th><th>Email</th><th>Checked in</th><th>Expected check out</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($guests as $guest)
            <tr>
                <td>{{ $guest['reservation_code'] }}</td>
                <td>{{ $guest['guest_name'] }}</td>
                <td>{{ $guest['facility_name'] }}</td>
                <td>{{ $guest['facility_type'] }}</td>
                <td class="num">{{ $guest['guest_count'] }}</td>
                <td>{{ $guest['guest_phone'] ?? 'Not provided' }}</td>
                <td>{{ $guest['guest_email'] ?? 'Not provided' }}</td>
                <td>{{ $guest['checked_in_at']->format('M j, Y g:i A') }}</td>
                <td>{{ $guest['expected_departure_label'] }}</td>
            </tr>
        @empty
            <tr><td colspan="9">No checked-in guests in this range.</td></tr>
        @endforelse
    </tbody>
</table>

@if ($arrivalDays !== [])
    <h2>Check ins by day</h2>
    <table>
        <thead><tr><th>Date</th><th class="num">Parties</th><th class="num">Guests</th></tr></thead>
        <tbody>
            @foreach ($arrivalDays as $day)
                <tr>
                    <td>{{ $day['label'] }}</td>
                    <td class="num">{{ number_format($day['parties']) }}</td>
                    <td class="num">{{ number_format($day['guests']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
</section>
@endif

@if (in_array('occupancy', $operationsSections, true))
<section class="report-section">
<h2>Current occupancy</h2>
<p class="meta">
    {{ number_format(count($currentOccupancy['occupied_list'])) }} stays in house ·
    {{ number_format($currentOccupancy['occupied_facilities']) }} of {{ number_format($currentOccupancy['total_facilities']) }} active facilities in use.
</p>

<table>
    <thead>
        <tr>
            <th>Reservation</th><th>Guest</th><th>Space</th><th>Type</th><th class="num">Guests</th>
            <th>Contact number</th><th>Checked in</th><th>Expected check out</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($currentOccupancy['occupied_list'] as $guest)
            <tr>
                <td>{{ $guest['reservation_code'] }}</td>
                <td>{{ $guest['guest_name'] }}</td>
                <td>{{ $guest['facility_name'] }}</td>
                <td>{{ $guest['facility_type'] }}</td>
                <td class="num">{{ $guest['guest_count'] }}</td>
                <td>{{ $guest['guest_phone'] ?? 'Not provided' }}</td>
                <td>{{ $guest['checked_in_at']->format('M j, Y g:i A') }}</td>
                <td>{{ $guest['check_out']->format('M j, Y g:i A') }}</td>
            </tr>
        @empty
            <tr><td colspan="8">No guests in house right now.</td></tr>
        @endforelse
    </tbody>
</table>

<h3>Occupancy by service type</h3>
<table>
    <thead><tr><th>Service type</th><th class="num">In use</th><th class="num">Active facilities</th><th class="num">Share</th></tr></thead>
    <tbody>
        @foreach ($currentOccupancy['by_type'] as $type)
            <tr>
                <td>{{ $type['label'] }}</td>
                <td class="num">{{ number_format($type['occupied']) }}</td>
                <td class="num">{{ number_format($type['inventory']) }}</td>
                <td class="num">{{ $type['inventory'] > 0 ? number_format($type['pct']).'%' : '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</section>
@endif

@if (in_array('action-lists', $operationsSections, true))
<section class="report-section">
<h2>Today's action lists</h2>
<p class="meta">
    Check ins due {{ number_format($arrivalsDue->count()) }} ·
    Check outs due {{ number_format($departuresDue->count()) }} ·
    Walk-ins {{ number_format(count($operationalPositions['walk_ins_today'])) }} ·
    Missed check ins {{ number_format(count($operationalPositions['missed_arrivals'])) }} ·
    Overdue check outs {{ number_format(count($operationalPositions['overdue_departures'])) }} ·
    Under maintenance {{ number_format($maintenanceFacilities->count()) }}
</p>

<h3>Guests awaiting check in today</h3>
<table>
    <thead><tr><th>Reservation</th><th>Guest</th><th>Space</th><th class="num">Guests</th><th>Expected check in</th></tr></thead>
    <tbody>
        @forelse ($arrivalsDue as $arrival)
            <tr>
                <td>{{ $arrival->reservation_code }}</td>
                <td>{{ $arrival->guestName() }}</td>
                <td>{{ $arrival->facility?->name }}</td>
                <td class="num">{{ $arrival->guest_count }}</td>
                <td>{{ $arrival->check_in->format('M j, Y g:i A') }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Every guest due today has been checked in.</td></tr>
        @endforelse
    </tbody>
</table>

<h3>Check outs due today</h3>
<table>
    <thead><tr><th>Reservation</th><th>Guest</th><th>Space</th><th class="num">Guests</th><th>Scheduled check out</th></tr></thead>
    <tbody>
        @forelse ($departuresDue as $departure)
            <tr>
                <td>{{ $departure->reservation_code }}</td>
                <td>{{ $departure->guestName() }}</td>
                <td>{{ $departure->facility?->name }}</td>
                <td class="num">{{ $departure->guest_count }}</td>
                <td>{{ $departure->check_out->format('M j, Y g:i A') }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Every stay ending today has been checked out.</td></tr>
        @endforelse
    </tbody>
</table>

<h3>Walk-ins recorded today</h3>
<table>
    <thead><tr><th>Reservation</th><th>Guest</th><th>Space</th><th class="num">Guests</th><th>Recorded</th></tr></thead>
    <tbody>
        @forelse ($operationalPositions['walk_ins_today'] as $walkIn)
            <tr>
                <td>{{ $walkIn['reservation_code'] }}</td>
                <td>{{ $walkIn['guest_name'] }}</td>
                <td>{{ $walkIn['facility_name'] }}</td>
                <td class="num">{{ $walkIn['guest_count'] }}</td>
                <td>{{ $walkIn['created_at']->format('M j, Y g:i A') }}</td>
            </tr>
        @empty
            <tr><td colspan="5">No walk-in reservations were recorded during the current shift.</td></tr>
        @endforelse
    </tbody>
</table>

<h3>Missed check ins</h3>
<table>
    <thead><tr><th>Reservation</th><th>Guest</th><th>Space</th><th class="num">Guests</th><th>Expected check in</th></tr></thead>
    <tbody>
        @forelse ($operationalPositions['missed_arrivals'] as $missed)
            <tr>
                <td>{{ $missed['reservation_code'] }}</td>
                <td>{{ $missed['guest_name'] }}</td>
                <td>{{ $missed['facility_name'] }}</td>
                <td class="num">{{ $missed['guest_count'] }}</td>
                <td>{{ $missed['check_in']->format('M j, Y g:i A') }}</td>
            </tr>
        @empty
            <tr><td colspan="5">No approved stay is past its check in date without a check-in.</td></tr>
        @endforelse
    </tbody>
</table>

<h3>Overdue check outs</h3>
<table>
    <thead><tr><th>Reservation</th><th>Guest</th><th>Space</th><th class="num">Guests</th><th>Expected check out</th></tr></thead>
    <tbody>
        @forelse ($operationalPositions['overdue_departures'] as $overdue)
            <tr>
                <td>{{ $overdue['reservation_code'] }}</td>
                <td>{{ $overdue['guest_name'] }}</td>
                <td>{{ $overdue['facility_name'] }}</td>
                <td class="num">{{ $overdue['guest_count'] }}</td>
                <td>{{ $overdue['check_out']->format('M j, Y g:i A') }}</td>
            </tr>
        @empty
            <tr><td colspan="5">No checked-in guest is past their scheduled check-out.</td></tr>
        @endforelse
    </tbody>
</table>

<h3>Facilities under maintenance</h3>
<table>
    <thead><tr><th>Facility</th><th>Type</th><th>Status</th></tr></thead>
    <tbody>
        @forelse ($maintenanceFacilities as $facility)
            <tr>
                <td>{{ $facility->name }}</td>
                <td>{{ $facility->facilityType?->name }}</td>
                <td>{{ $facility->status->label() }}</td>
            </tr>
        @empty
            <tr><td colspan="3">Every active facility is available for booking.</td></tr>
        @endforelse
    </tbody>
</table>

</section>
@endif
