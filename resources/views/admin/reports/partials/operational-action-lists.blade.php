@php
    $walkInCount = count($operationalPositions['walk_ins_today']);
    $missedArrivalCount = count($operationalPositions['missed_arrivals']);
    $overdueDepartureCount = count($operationalPositions['overdue_departures']);
    $arrivalsDue = $operations['arrivals'];
    $departuresDue = $operations['departures'];
    $maintenanceFacilities = $operations['maintenance_facilities'];
@endphp

<x-admin.panel
    title="Today's action lists"
    description="Positions that need a staff decision during the current shift."
>
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-admin.stat
            label="Guests awaiting check in"
            :value="number_format($arrivalsDue->count())"
            note="Guests due today who have not been checked in yet"
        />

        <x-admin.stat
            label="Check outs due"
            :value="number_format($departuresDue->count())"
            note="Stays ending today that have not been checked out"
        />

        <x-admin.stat
            label="Walk-ins recorded today"
            :value="number_format($walkInCount)"
            note="Walk-in reservations created during the current shift"
        />

        <x-admin.stat
            label="Missed check ins"
            :value="number_format($missedArrivalCount)"
            note="Approved stays whose check in date has passed with no check-in"
        />

        <x-admin.stat
            label="Overdue check outs"
            :value="number_format($overdueDepartureCount)"
            note="Checked-in guests past their scheduled check-out"
        />
    </div>

    @if ($arrivalsDue->isNotEmpty())
        <x-admin.panel
            title="Today's check ins not checked in yet"
            :badge="$arrivalsDue->count()"
            description="Approved guests due today. Check them in from the reservation record when they arrive."
        >
            <div class="mt-4 overflow-x-auto rounded-lg border border-border bg-surface">
                <table class="min-w-full border-collapse text-sm">
                    <thead class="bg-muted/70">
                        <tr>
                            <x-admin.table-cell :isHeader="true">Reservation</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true">Guest</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true">Space</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true" numeric>Guests</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true">Expected check in</x-admin.table-cell>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($arrivalsDue as $arrival)
                            <tr>
                                <x-admin.table-cell class="font-medium">
                                    <a href="{{ route('admin.reservations.show', $arrival) }}" class="rounded text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                        {{ $arrival->reservation_code }}
                                    </a>
                                </x-admin.table-cell>
                                <x-admin.table-cell>{{ $arrival->guestName() }}</x-admin.table-cell>
                                <x-admin.table-cell>{{ $arrival->facility?->name }}</x-admin.table-cell>
                                <x-admin.table-cell numeric>{{ $arrival->guest_count }}</x-admin.table-cell>
                                <x-admin.table-cell><x-admin.date-time :value="$arrival->check_in" /></x-admin.table-cell>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-admin.panel>
    @endif

    @if ($departuresDue->isNotEmpty())
        <x-admin.panel
            title="Check outs due today"
            :badge="$departuresDue->count()"
            description="Stays ending today. Settle any remaining balance before the guest leaves."
        >
            <div class="mt-4 overflow-x-auto rounded-lg border border-border bg-surface">
                <table class="min-w-full border-collapse text-sm">
                    <thead class="bg-muted/70">
                        <tr>
                            <x-admin.table-cell :isHeader="true">Reservation</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true">Guest</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true">Space</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true" numeric>Guests</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true">Scheduled check out</x-admin.table-cell>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($departuresDue as $departure)
                            <tr>
                                <x-admin.table-cell class="font-medium">
                                    <a href="{{ route('admin.reservations.show', $departure) }}" class="rounded text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                        {{ $departure->reservation_code }}
                                    </a>
                                </x-admin.table-cell>
                                <x-admin.table-cell>{{ $departure->guestName() }}</x-admin.table-cell>
                                <x-admin.table-cell>{{ $departure->facility?->name }}</x-admin.table-cell>
                                <x-admin.table-cell numeric>{{ $departure->guest_count }}</x-admin.table-cell>
                                <x-admin.table-cell><x-admin.date-time :value="$departure->check_out" /></x-admin.table-cell>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-admin.panel>
    @endif

    @if ($walkInCount > 0)
        <x-admin.panel
            title="Walk-ins recorded today"
            :badge="$walkInCount"
            description="Walk-in reservations created during the current shift. These are already approved and should be checked in like any other booking."
        >
            <div class="mt-4 overflow-x-auto rounded-lg border border-border bg-surface">
                <table class="min-w-full border-collapse text-sm">
                    <thead class="bg-muted/70">
                        <tr>
                            <x-admin.table-cell :isHeader="true">Reservation</x-admin-table-cell>
                            <x-admin.table-cell :isHeader="true">Guest</x-admin-table-cell>
                            <x-admin.table-cell :isHeader="true">Space</x-admin-table-cell>
                            <x-admin.table-cell :isHeader="true" numeric>Guests</x-admin-table-cell>
                            <x-admin.table-cell :isHeader="true">Recorded</x-admin-table-cell>
                            <x-admin.table-cell :isHeader="true"></x-admin-table-cell>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($operationalPositions['walk_ins_today'] as $walkIn)
                            <tr>
                                <x-admin.table-cell class="font-medium">
                                    <a href="{{ route('admin.reservations.show', $walkIn['reservation_id']) }}" class="rounded text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                        {{ $walkIn['reservation_code'] }}
                                    </a>
                                </x-admin.table-cell>
                                <x-admin.table-cell>{{ $walkIn['guest_name'] }}</x-admin.table-cell>
                                <x-admin.table-cell>{{ $walkIn['facility_name'] }}</x-admin-table-cell>
                                <x-admin.table-cell numeric>{{ $walkIn['guest_count'] }}</x-admin-table-cell>
                                <x-admin.table-cell><x-admin.date-time :value="$walkIn['created_at']" /></x-admin-table-cell>
                                <x-admin.table-cell>
                                    <a href="{{ route('admin.reservations.show', $walkIn['reservation_id']) }}" class="rounded text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                        Review check-in
                                    </a>
                                </x-admin.table-cell>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-admin.panel>
    @else
        <x-admin.panel title="Walk-ins recorded today" description="No walk-in reservations were created during the current shift.">
            <p class="mt-3 text-sm text-text-muted">Nothing to do here right now.</p>
        </x-admin.panel>
    @endif

    @if ($missedArrivalCount > 0)
        <x-admin.panel
            title="Missed check ins"
            :badge="$missedArrivalCount"
            description="Approved stays whose check in date has passed without a check-in. These need follow-up before the space can be safely released."
        >
            <div class="mt-4 overflow-x-auto rounded-lg border border-border bg-surface">
                <table class="min-w-full border-collapse text-sm">
                    <thead class="bg-muted/70">
                        <tr>
                            <x-admin.table-cell :isHeader="true">Reservation</x-admin-table-cell>
                            <x-admin.table-cell :isHeader="true">Guest</x-admin-table-cell>
                            <x-admin.table-cell :isHeader="true">Space</x-admin-table-cell>
                            <x-admin.table-cell :isHeader="true" numeric>Guests</x-admin-table-cell>
                            <x-admin.table-cell :isHeader="true">Expected check in</x-admin-table-cell>
                            <x-admin.table-cell :isHeader="true"></x-admin-table-cell>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($operationalPositions['missed_arrivals'] as $missed)
                            <tr>
                                <x-admin.table-cell class="font-medium">
                                    <a href="{{ route('admin.reservations.show', $missed['reservation_id']) }}" class="rounded text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                        {{ $missed['reservation_code'] }}
                                    </a>
                                </x-admin.table-cell>
                                <x-admin.table-cell>{{ $missed['guest_name'] }}</x-admin-table-cell>
                                <x-admin.table-cell>{{ $missed['facility_name'] }}</x-admin.table-cell>
                                <x-admin.table-cell numeric>{{ $missed['guest_count'] }}</x-admin-table-cell>
                                <x-admin.table-cell><x-admin.date-time :value="$missed['check_in']" /></x-admin-table-cell>
                                <x-admin.table-cell>
                                    <a href="{{ route('admin.reservations.show', $missed['reservation_id']) }}" class="rounded text-danger hover:text-danger-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-danger focus-visible:ring-offset-2">
                                        Follow up
                                    </a>
                                </x-admin.table-cell>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-admin.panel>
    @else
        <x-admin.panel title="Missed check ins" description="No approved stays are past their check in date without a check-in.">
            <p class="mt-3 text-sm text-text-muted">Nothing to follow up on right now.</p>
        </x-admin.panel>
    @endif

    @if ($overdueDepartureCount > 0)
        <x-admin.panel
            title="Overdue check outs"
            :badge="$overdueDepartureCount"
            description="Guests who are still checked in past their scheduled check-out. These may be a legitimate late stay or a checkout that was missed."
        >
            <div class="mt-4 overflow-x-auto rounded-lg border border-border bg-surface">
                <table class="min-w-full border-collapse text-sm">
                    <thead class="bg-muted/70">
                        <tr>
                            <x-admin.table-cell :isHeader="true">Reservation</x-admin-table-cell>
                            <x-admin.table-cell :isHeader="true">Guest</x-admin-table-cell>
                            <x-admin.table-cell :isHeader="true">Space</x-admin-table-cell>
                            <x-admin.table-cell :isHeader="true" numeric>Guests</x-admin-table-cell>
                            <x-admin.table-cell :isHeader="true">Expected check out</x-admin-table-cell>
                            <x-admin.table-cell :isHeader="true"></x-admin-table-cell>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($operationalPositions['overdue_departures'] as $overdue)
                            <tr>
                                <x-admin.table-cell class="font-medium">
                                    <a href="{{ route('admin.reservations.show', $overdue['reservation_id']) }}" class="rounded text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                        {{ $overdue['reservation_code'] }}
                                    </a>
                                </x-admin.table-cell>
                                <x-admin.table-cell>{{ $overdue['guest_name'] }}</x-admin-table-cell>
                                <x-admin.table-cell>{{ $overdue['facility_name'] }}</x-admin-table-cell>
                                <x-admin.table-cell numeric>{{ $overdue['guest_count'] }}</x-admin-table-cell>
                                <x-admin.table-cell><x-admin.date-time :value="$overdue['check_out']" /></x-admin.table-cell>
                                <x-admin.table-cell>
                                    <a href="{{ route('admin.reservations.show', $overdue['reservation_id']) }}" class="rounded text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                        Review check-out
                                    </a>
                                </x-admin.table-cell>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-admin.panel>
    @else
        <x-admin.panel title="Overdue check outs" description="No checked-in guests are past their scheduled check-out.">
            <p class="mt-3 text-sm text-text-muted">Nothing to follow up on right now.</p>
        </x-admin.panel>
    @endif

    <x-admin.panel
        title="Facilities under maintenance"
        :badge="$maintenanceFacilities->count()"
        description="Spaces that are off the market right now. They stay out of the walk-in recommendation until staff put them back."
    >
        @forelse ($maintenanceFacilities as $facility)
            <a
                href="{{ route('admin.facilities.show', $facility) }}"
                class="flex items-center justify-between gap-3 border-b border-border px-5 py-3 transition-colors duration-150 last:border-b-0 hover:bg-primary-light/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary"
            >
                <span class="min-w-0 truncate text-sm font-medium text-primary">{{ $facility->name }}</span>
                <span class="shrink-0 text-xs text-text-muted">{{ $facility->facilityType?->name }} · {{ $facility->status->label() }}</span>
            </a>
        @empty
            <p class="mt-3 text-sm text-text-muted">Every active facility is available for booking.</p>
        @endforelse
    </x-admin.panel>
</x-admin.panel>
