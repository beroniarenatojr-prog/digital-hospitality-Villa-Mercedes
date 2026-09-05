<x-admin-layout>
    <x-slot name="header">
        Owner Decision Board
    </x-slot>

    <p class="text-sm text-gray-500 mb-6 max-w-3xl">
        One calendar for rooms, halls, packages and TESDA training, with the peso consequence of
        each pending request spelled out. Every figure below comes from a configured rate and
        names the claim it came from.
    </p>

    <!-- Window controls -->
    <form method="GET" action="{{ route('admin.decision-board') }}"
          class="mb-6 bg-white p-4 rounded-lg shadow-sm border border-gray-200 grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div>
            <label for="from" class="block text-xs font-medium text-gray-700 mb-1">From</label>
            <input type="date" name="from" id="from" value="{{ $from->toDateString() }}"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
        </div>
        <div>
            <label for="to" class="block text-xs font-medium text-gray-700 mb-1">To</label>
            <input type="date" name="to" id="to" value="{{ $to->toDateString() }}"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
        </div>
        <div>
            <label for="facility_type" class="block text-xs font-medium text-gray-700 mb-1">Resource type</label>
            <select name="facility_type" id="facility_type"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                <option value="">All resources</option>
                @foreach($facilityTypes as $type)
                    <option value="{{ $type->slug }}" @selected($facilityTypeSlug === $type->slug)>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="flex-1 inline-flex justify-center items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-hover transition">
                Update view
            </button>
            <a href="{{ route('admin.decision-board') }}" class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                Today
            </a>
        </div>
    </form>

    <!-- Headline numbers -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Waiting on you</p>
            <p class="mt-2 text-2xl font-bold text-gray-900">{{ $summary['pending_count'] }}</p>
            <p class="mt-1 text-xs text-gray-500">₱{{ number_format($summary['pending_value'], 2) }} of booking value unanswered</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Frozen by pending holds</p>
            <p class="mt-2 text-2xl font-bold {{ $summary['frozen_list_value'] > 0 ? 'text-warning' : 'text-gray-900' }}">
                ₱{{ number_format($summary['frozen_list_value'], 2) }}
            </p>
            <p class="mt-1 text-xs text-gray-500">List value of linked space taken off the market</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Claims in this window</p>
            <p class="mt-2 text-2xl font-bold text-gray-900">{{ $summary['claim_count'] }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ $summary['training_claim_count'] }} from TESDA training</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Nothing booked yet</p>
            <p class="mt-2 text-2xl font-bold text-gray-900">{{ $summary['unclaimed_facilities'] }} / {{ $summary['bookable_facilities'] }}</p>
            <p class="mt-1 text-xs text-gray-500">Resources with no claim in this window</p>
        </div>
    </div>

    <!-- Decisions waiting -->
    <section class="mb-10">
        <h2 class="text-lg font-semibold text-gray-900 mb-1">Decisions waiting on you</h2>
        <p class="text-sm text-gray-500 mb-4">Pending requests already block their dates. Holding one is a decision too.</p>

        @forelse($queue as $item)
            @php($reservation = $item['reservation'])
            @php($impact = $item['impact'])

            <div class="bg-white rounded-lg border border-gray-200 shadow-sm mb-4 overflow-hidden"
                 x-data="{ rejecting: false }">
                <div class="p-5 flex flex-col lg:flex-row lg:items-start gap-4">
                    <div class="flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('admin.reservations.show', $reservation) }}" class="font-mono text-sm font-semibold text-primary hover:underline">
                                {{ $reservation->reservation_code }}
                            </a>
                            <x-badge :status="$reservation->status->value" />
                            @if($impact->hasConflicts())
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-danger-light text-danger">
                                    Conflict detected
                                </span>
                            @endif
                            @if($impact->isNetNegative())
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-warning-light text-warning">
                                    Blocks more value than it earns
                                </span>
                            @endif
                        </div>

                        <p class="mt-2 text-sm text-gray-900">
                            <span class="font-semibold">{{ $reservation->guestName() }}</span>
                            &middot; {{ $reservation->facility->name }}
                            &middot; {{ $reservation->guest_count }} pax
                        </p>
                        <p class="text-sm text-gray-500">
                            {{ $reservation->occupancyStart()->format('D, M j, g:i A') }}
                            &rarr;
                            {{ $reservation->occupancyEnd()->format('D, M j, g:i A') }}
                        </p>

                        <ul class="mt-4 space-y-2">
                            @foreach($impact->explanations as $explanation)
                                <li class="flex gap-2 text-sm text-gray-700">
                                    <span class="text-primary mt-0.5">&bull;</span>
                                    <span>{{ $explanation }}</span>
                                </li>
                            @endforeach
                        </ul>

                        @if($impact->frozenResources !== [])
                            <div class="mt-4 rounded-md bg-gray-50 border border-gray-200 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Space this booking freezes</p>
                                <ul class="space-y-1 text-sm text-gray-700">
                                    @foreach($impact->frozenResources as $frozen)
                                        <li class="flex justify-between gap-4">
                                            <span>{{ $frozen['name'] }} <span class="text-gray-500">— {{ $frozen['reason'] }}</span></span>
                                            <span class="font-medium whitespace-nowrap">₱{{ number_format($frozen['list_value'], 2) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                    <div class="lg:w-64 flex-shrink-0 space-y-3">
                        <div class="rounded-md border border-gray-200 p-3 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Booking value</span>
                                <span class="font-semibold text-gray-900">₱{{ number_format($impact->reservationValue, 2) }}</span>
                            </div>
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Space frozen</span>
                                <span class="font-semibold text-gray-900">₱{{ number_format($impact->opportunityCost, 2) }}</span>
                            </div>
                            <div class="flex justify-between mt-2 pt-2 border-t">
                                <span class="font-medium text-gray-900">Net if approved</span>
                                <span class="font-bold {{ $impact->isNetNegative() ? 'text-danger' : 'text-success' }}">
                                    {{ $impact->isNetNegative() ? '-' : '+' }}₱{{ number_format(abs($impact->netValue()), 2) }}
                                </span>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('admin.reservations.approve', $reservation) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="w-full px-4 py-2 bg-success text-white rounded-md text-sm font-semibold hover:bg-green-700 transition">
                                Approve
                            </button>
                        </form>

                        <button type="button" @click="rejecting = !rejecting"
                                class="w-full px-4 py-2 border border-danger text-danger bg-white rounded-md text-sm font-semibold hover:bg-danger hover:text-white transition">
                            Reject
                        </button>

                        <form method="POST" action="{{ route('admin.reservations.reject', $reservation) }}" x-show="rejecting" x-cloak class="space-y-2">
                            @csrf
                            @method('PATCH')
                            <textarea name="reason" rows="2" required placeholder="Reason shown to the guest"
                                      class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm"></textarea>
                            <button type="submit" class="w-full px-4 py-2 bg-danger text-white rounded-md text-sm font-semibold hover:bg-red-700 transition">
                                Confirm rejection
                            </button>
                        </form>

                        <a href="{{ route('admin.reservations.show', $reservation) }}"
                           class="block text-center text-sm font-medium text-primary hover:underline">
                            Open reservation
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-8 text-center text-gray-500">
                No pending requests. Nothing is frozen waiting for an answer.
            </div>
        @endforelse
    </section>

    <!-- Unified timeline -->
    <section>
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">One calendar</h2>
                <p class="text-sm text-gray-500">
                    {{ $from->format('M j, Y') }} – {{ $to->format('M j, Y') }}. Bars show real occupancy, including paid extra hours.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-4 text-xs text-gray-600">
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-primary"></span> Approved stay</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-warning"></span> Pending hold</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-info"></span> TESDA training</span>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
            <!-- Day header -->
            <div class="flex border-b border-gray-200 bg-gray-50">
                <div class="w-40 sm:w-56 flex-shrink-0 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">
                    Resource
                </div>
                <div class="flex-1 relative h-10">
                    @foreach($days as $day)
                        <div class="absolute top-0 bottom-0 border-l border-gray-200 flex flex-col justify-center pl-1"
                             @style(['left: '.$day['offset'].'%', 'width: '.$day['width'].'%'])>
                            <span class="text-[10px] font-semibold text-gray-600 leading-tight">{{ $day['label'] }}</span>
                            <span class="text-[10px] text-gray-400 leading-tight">{{ $day['sublabel'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            @forelse($rows as $row)
                <div class="flex border-b border-gray-100 last:border-b-0 hover:bg-gray-50/60">
                    <div class="w-40 sm:w-56 flex-shrink-0 px-4 py-3">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ $row['facility']->name }}</p>
                        <p class="text-xs text-gray-500">{{ $row['facility']->facilityType->name }}</p>
                    </div>
                    <div class="flex-1 relative py-3 min-h-[3.5rem]">
                        @foreach($days as $day)
                            <div class="absolute top-0 bottom-0 border-l border-gray-100" @style(['left: '.$day['offset'].'%'])></div>
                        @endforeach

                        @forelse($row['claims'] as $bar)
                            @php($claim = $bar['claim'])
                            @php($isTraining = $claim->isTraining())
                            @php($isPending = $claim->status === 'Pending')
                            <a href="{{ $claim->url }}"
                               class="absolute top-2 bottom-2 rounded px-2 py-1 overflow-hidden text-[10px] font-medium text-white shadow-sm transition hover:opacity-90 {{ $isTraining ? 'bg-info' : ($isPending ? 'bg-warning' : 'bg-primary') }}"
                               @style(['left: '.$bar['offset'].'%', 'width: '.$bar['width'].'%'])
                               title="{{ $claim->label }} — {{ $claim->durationLabel() }} ({{ $claim->status }}{{ $claim->note ? ', ' . $claim->note : '' }})">
                                <span class="block truncate">{{ $claim->label }}</span>
                                <span class="block truncate opacity-90">{{ $claim->durationLabel() }}</span>
                            </a>
                        @empty
                            <span class="absolute left-2 top-1/2 -translate-y-1/2 text-xs text-gray-400">Free</span>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-gray-500">No active resources match this filter.</div>
            @endforelse
        </div>
    </section>
</x-admin-layout>
