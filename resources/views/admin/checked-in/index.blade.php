@php
    $stays = collect($currentOccupancy['occupied_list']);
    $bookings = $stays->groupBy(fn ($stay) => $stay['group_id'] ? 'group-'.$stay['group_id'] : 'reservation-'.$stay['reservation_id']);
    $now = \App\Support\ResortTime::databaseNow();
    $due = $stays->filter(fn ($stay) => $stay['check_out']->lte($now));
    $bookings = $bookings->sortBy(fn ($lines) => $lines->min('check_out'));
@endphp

<x-admin-layout>
    <x-slot name="header">Checked-in</x-slot>
    <x-admin.page-header title="Checked-in">
        <x-slot:actions>
            <x-admin.button variant="secondary" :href="route('admin.reports.export.pdf', array_merge($reportQuery, ['view' => 'operations']))">Print occupancy list</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <dl class="mb-6 grid grid-cols-2 gap-4 rounded-xl border border-border bg-surface p-5 lg:grid-cols-4">
        @foreach (['Bookings in house' => $bookings->count(), 'Services in use' => $stays->count(), 'Facilities occupied' => $currentOccupancy['occupied_facilities'], 'Services due for check-out' => $due->count()] as $label => $value)
            <div>
                <dt class="text-sm text-text-muted">{{ $label }}</dt>
                <dd class="mt-1 text-2xl font-bold tabular-nums text-text">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-heading text-xl font-semibold text-text">Guests currently staying</h2>
        <p class="text-sm text-text-muted">Earliest check-out first</p>
    </div>

    <div class="space-y-5">
        @forelse ($bookings as $lines)
            @php
                $lead = $lines->first();
                $bookingCode = $lead['group_code'] ?? $lead['reservation_code'];
                $bookingUrl = $lead['group_id'] ? route('admin.reservation-groups.show', $lead['group_id']) : route('admin.reservations.show', $lead['reservation_id']);
                $guestName = $lead['group_guest_name'] ?? $lead['guest_name'];
            @endphp
            <article class="overflow-hidden rounded-xl border border-border bg-surface">
                <header class="flex flex-wrap items-start justify-between gap-4 border-b border-border p-5">
                    <div class="min-w-0">
                        <h3 class="text-lg font-semibold text-text">{{ $guestName }}</h3>
                        <a href="{{ $bookingUrl }}" class="mt-1 inline-block text-sm font-medium text-primary hover:underline">{{ $bookingCode }}</a>
                        <p class="mt-1 text-sm text-text-muted">{{ $lead['guest_phone'] ?: 'Contact number not provided' }} · {{ $lines->count() }} {{ \Illuminate\Support\Str::plural('service', $lines->count()) }}</p>
                    </div>
                    <x-admin.button variant="secondary" :href="$bookingUrl">View booking</x-admin.button>
                </header>
                <ul class="divide-y divide-border">
                    @foreach ($lines as $stay)
                        <li class="grid items-center gap-4 p-5 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                            <div>
                                <p class="font-semibold text-text">{{ $stay['facility_name'] }}</p>
                                <p class="mt-1 text-sm text-text-muted">{{ $stay['facility_type'] }} · {{ $stay['guest_count'] }} guests</p>
                                <p class="mt-1 text-xs text-text-muted">Checked in <x-admin.date-time :value="$stay['checked_in_at']" /></p>
                            </div>
                            <div>
                                <p class="text-xs text-text-muted">Expected check-out</p>
                                <p class="mt-1 text-sm font-medium text-text"><x-admin.date-time :value="$stay['check_out']" /></p>
                                @if($stay['check_out']->lte($now))
                                    <p class="mt-1 text-sm font-semibold text-danger">Overdue checkout</p>
                                @endif
                                @if($stay['remaining_balance'] > 0)
                                    <p class="mt-1 text-sm text-danger">Remaining balance <x-amount :value="$stay['remaining_balance']" /></p>
                                @else
                                    <p class="mt-1 text-xs text-text-muted">Remaining balance <x-amount :value="0" /></p>
                                @endif
                            </div>
                            <x-admin.confirm-action
                                :action="route('admin.reservations.check-out', $stay['reservation_id'])"
                                size="sm"
                                tone="primary"
                                :label="$stay['check_out']->gt($now) ? 'Check out early' : 'Check out'"
                                :title="'Check out '.$guestName.' from '.$stay['facility_name'].'?'"
                                confirm="Confirm checkout"
                                consequence="Confirm once the guest has left this service. Rooms become available after staff complete cleaning."
                            />
                        </li>
                    @endforeach
                </ul>
            </article>
        @empty
            <x-admin.empty icon="reservations" title="No guests in house right now" description="Guests appear here after staff record their physical check-in." />
        @endforelse
    </div>
</x-admin-layout>
