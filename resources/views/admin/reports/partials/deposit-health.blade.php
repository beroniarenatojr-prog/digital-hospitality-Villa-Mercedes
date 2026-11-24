{{-- Deposit health exists because the guest flow promises "the 50% deposit opens
     the moment the resort approves", and it is that approval which raises the
     obligation. This panel is the other half of the promise: of the bookings
     that have been approved, who has not paid the half yet, and whose deadline
     has already passed. A request still in Review pending owes nothing and is
     counted in the review queue, not here. --}}
@php
    $outstandingNote = number_format($depositHealth['outstanding_count']).' '
        .\Illuminate\Support\Str::plural('booking', $depositHealth['outstanding_count'])
        .' short of the half';
@endphp

<x-admin.panel
    class="mb-8"
    title="50% down payment health"
    description="Every booking approved in the selected period, measured against the deposit the guest portal actually requires: {{ $depositHealth['bookings'] }} {{ \Illuminate\Support\Str::plural('booking', $depositHealth['bookings']) }} as of {{ $depositHealth['as_of']->format('M j, Y g:i A') }}."
>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat
            label="Deposits collected"
            :value="$depositHealth['collection_rate'] === null ? 'Not enough data' : number_format($depositHealth['collection_rate'], 1).'%'"
            basis="Verified payments against the deposit each booking requires"
            note="Verified payments only; receipts still under review are counted separately"
        />

        <x-admin.stat
            label="Still owed"
            basis="Across approved bookings that have not met the half"
            :note="$outstandingNote"
        ><x-amount :value="$depositHealth['outstanding_total']" /></x-admin.stat>

        <x-admin.stat
            label="Past the deadline"
            :value="number_format($depositHealth['overdue'])"
            basis="Deposit due date has passed and the half is still not verified"
            note="The first list to work through; every one of these is holding a space"
        />

        <x-admin.stat
            label="Half met"
            basis="Guest owes nothing further on the deposit"
            :note="number_format($depositHealth['short']).' still short · '.number_format($depositHealth['unverified']).' bookings with a receipt awaiting verification'"
        >{{ number_format($depositHealth['met']) }}</x-admin.stat>
    </div>

    {{-- The amounts sit outside the tiles because the tile props are plain text and
         money is written once, by the component that owns the symbol. --}}
    <p class="mt-4 flex flex-wrap items-baseline gap-x-2 gap-y-1 text-sm text-text-muted">
        <x-amount :value="$depositHealth['deposit_collected']" /> collected of
        <x-amount :value="$depositHealth['deposit_expected']" /> required ·
        <x-amount :value="$depositHealth['awaiting_verification']" /> awaiting verification ·
        <x-amount :value="$depositHealth['outstanding_total']" /> still owed.
    </p>

    @if ($depositHealth['outstanding'] === [])
        <p class="mt-6 text-sm text-text-muted">
            Every pending and approved booking in this period has met its 50% down payment. Nothing to chase.
        </p>
    @else
        <h3 class="mt-6 text-sm font-semibold text-text">Deposits still open</h3>
        <p class="mt-1 text-sm text-text-muted">Overdue bookings first, then the largest shortfall. Values are the amount still needed on the deposit.</p>

        <x-admin.table class="mt-3">
            <x-admin.table-head>
                <x-admin.table-cell :isHeader="true">Reservation</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Guest</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Space</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Visit</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Deposit due</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true" numeric>Required</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true" numeric>Verified</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true" numeric>Still needed</x-admin.table-cell>
            </x-admin.table-head>
            <x-admin.table-body>
                @foreach ($depositHealth['outstanding'] as $row)
                    <x-admin.table-row>
                        <x-admin.table-cell class="font-medium">
                            <a href="{{ route('admin.reservations.show', $row['reservation_id']) }}" class="rounded text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                {{ $row['reservation_code'] }}
                            </a>
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            {{ $row['guest_name'] }}
                            @if ($row['guest_phone'])
                                <span class="block text-xs text-text-muted">{{ $row['guest_phone'] }}</span>
                            @endif
                        </x-admin.table-cell>
                        <x-admin.table-cell>{{ $row['facility_name'] }}</x-admin.table-cell>
                        <x-admin.table-cell>{{ $row['check_in']->format('M j, Y') }}<span class="block text-xs text-text-muted">{{ $row['status_label'] }}</span></x-admin.table-cell>
                        <x-admin.table-cell>
                            @if ($row['deposit_due_at'] === null)
                                Not set
                            @elseif ($row['is_overdue'])
                                <span class="font-semibold text-danger">{{ $row['deposit_due_at']->format('M j, Y') }}</span>
                                <span class="block text-xs font-semibold text-danger">Overdue</span>
                            @else
                                {{ $row['deposit_due_at']->format('M j, Y') }}
                            @endif
                        </x-admin.table-cell>
                        <x-admin.table-cell numeric><x-amount :value="$row['required']" /></x-admin.table-cell>
                        <x-admin.table-cell numeric><x-amount :value="$row['verified']" /></x-admin.table-cell>
                        <x-admin.table-cell numeric>
                            <span class="font-semibold {{ $row['is_overdue'] ? 'text-danger' : 'text-text' }}"><x-amount :value="$row['short']" /></span>
                            @if ($row['pending_verification'] > 0)
                                <span class="block text-xs text-text-muted"><x-amount :value="$row['pending_verification']" /> under review</span>
                            @endif
                        </x-admin.table-cell>
                    </x-admin.table-row>
                @endforeach
            </x-admin.table-body>
        </x-admin.table>
    @endif
</x-admin.panel>
