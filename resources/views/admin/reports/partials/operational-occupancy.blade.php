@php
    $occupiedCount = count($currentOccupancy['occupied_list']);
@endphp

<x-admin.panel
    title="Current occupancy"
    :badge="$occupiedCount"
    description="Guests who have checked in and have not checked out. Open a booking to manage payments and checkout."
>
    <x-slot:actions>
        <x-admin.button variant="secondary" :href="route('admin.reports.operational')">
            Back to registry
        </x-admin.button>
        <x-admin.button :href="route('admin.reports.export.pdf', array_merge($reportQuery, ['view' => 'operations']))">
            Printable version
        </x-admin.button>
    </x-slot:actions>

    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
        <x-admin.stat
            label="Checked-in stays"
            :value="number_format($occupiedCount)"
            note="Checked-in stays that have not checked out"
        />

        <x-admin.stat
            label="Facilities in use"
            :value="number_format($currentOccupancy['occupied_facilities'])"
            note="Unique facilities with at least one checked-in stay"
        />

        <x-admin.stat
            label="Total active facilities"
            :value="number_format($currentOccupancy['total_facilities'])"
            note="Active facilities across all types"
        />
    </div>

    @if ($currentOccupancy['by_type'] !== [])
        <div class="mb-6 overflow-x-auto rounded-lg border border-border bg-surface">
            <table class="min-w-full border-collapse text-sm">
                <thead class="bg-muted/70">
                    <tr>
                        <x-admin.table-cell :isHeader="true">Service type</x-admin-table-cell>
                        <x-admin.table-cell :isHeader="true" numeric>In use</x-admin-table-cell>
                        <x-admin.table-cell :isHeader="true" numeric>Active facilities</x-admin-table-cell>
                        <x-admin.table-cell :isHeader="true" numeric>Share</x-admin-table-cell>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($currentOccupancy['by_type'] as $type)
                        <tr>
                            <x-admin.table-cell class="font-medium">{{ $type['label'] }}</x-admin-table-cell>
                            <x-admin.table-cell numeric>{{ number_format($type['occupied']) }}</x-admin-table-cell>
                            <x-admin.table-cell numeric>{{ number_format($type['inventory']) }}</x-admin-table-cell>
                            <x-admin.table-cell numeric>{{ $type['inventory'] > 0 ? number_format($type['pct']) . '%' : '—' }}</x-admin-table-cell>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($occupiedCount === 0)
        <x-admin.empty
            icon="reservations"
            title="No guests in house right now"
            description="The property is empty for this shift. The next check ins list is on the dashboard."
        />
    @else
        <div class="mt-4 overflow-x-auto rounded-lg border border-border bg-surface">
            <table class="min-w-full border-collapse text-sm">
                <thead class="bg-muted/70">
                    <tr>
                        <x-admin.table-cell :isHeader="true">Reservation</x-admin-table-cell>
                        <x-admin.table-cell :isHeader="true">Guest</x-admin-table-cell>
                        <x-admin.table-cell :isHeader="true">Space</x-admin-table-cell>
                        <x-admin.table-cell :isHeader="true">Type</x-admin-table-cell>
                        <x-admin.table-cell :isHeader="true" numeric>Guests</x-admin-table-cell>
                        <x-admin.table-cell :isHeader="true">Contact number</x-admin-table-cell>
                        <x-admin.table-cell :isHeader="true">Checked in</x-admin-table-cell>
                        <x-admin.table-cell :isHeader="true">Expected check out</x-admin-table-cell>
                        <x-admin.table-cell :isHeader="true" numeric>Remaining balance</x-admin.table-cell>
                        <x-admin.table-cell :isHeader="true">Actions</x-admin.table-cell>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($currentOccupancy['occupied_list'] as $guest)
                        <tr>
                            <x-admin.table-cell class="font-medium">
                                <a href="{{ route('admin.reservations.show', $guest['reservation_id']) }}" class="rounded text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                    {{ $guest['reservation_code'] }}
                                </a>
                            </x-admin.table-cell>
                            <x-admin.table-cell>{{ $guest['guest_name'] }}</x-admin.table-cell>
                            <x-admin.table-cell>{{ $guest['facility_name'] }}</x-admin-table-cell>
                            <x-admin.table-cell>{{ $guest['facility_type'] }}</x-admin-table-cell>
                            <x-admin.table-cell numeric>{{ $guest['guest_count'] }}</x-admin-table-cell>
                            <x-admin.table-cell>{{ $guest['guest_phone'] ?? 'Not provided' }}</x-admin-table-cell>
                            <x-admin.table-cell><x-admin.date-time :value="$guest['checked_in_at']" /></x-admin.table-cell>
                            <x-admin.table-cell>
                                {{ $guest['check_out']->format('M j, Y g:i A') }}
                                @if ($guest['check_out']->lt(\App\Support\ResortTime::databaseNow()))
                                    <span class="block font-semibold text-danger">Overdue checkout</span>
                                @endif
                            </x-admin.table-cell>
                            <x-admin.table-cell numeric><x-amount :value="$guest['remaining_balance']" /></x-admin.table-cell>
                            <x-admin.table-cell>
                                <div class="space-y-2">
                                    <x-admin.button variant="secondary" size="sm" :href="route('admin.reservations.show', $guest['reservation_id'])">View booking</x-admin.button>
                                    @if (request()->routeIs('admin.checked-in.index') || $guest['check_out']->lte(\App\Support\ResortTime::databaseNow()))
                                        <x-admin.confirm-action
                                            :action="route('admin.reservations.check-out', $guest['reservation_id'])"
                                            size="sm"
                                            tone="primary"
                                            :label="$guest['check_out']->gt(\App\Support\ResortTime::databaseNow()) ? 'Check out early' : 'Check out guest'"
                                            title="Check out {{ $guest['guest_name'] }} from {{ $guest['facility_name'] }}?"
                                            confirm="Confirm checkout"
                                            consequence="Confirm once the guest has left. This completes the reservation and removes the stay from this list. Rooms remain unavailable until cleaning is complete."
                                        />
                                    @endif
                                </div>
                            </x-admin.table-cell>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="mt-3 text-xs text-text-muted">
            This list is the same data used for the emergency roll call. Capacity-based facilities can host more than one stay, so the same room may appear more than once.
        </p>
    @endif
</x-admin.panel>
