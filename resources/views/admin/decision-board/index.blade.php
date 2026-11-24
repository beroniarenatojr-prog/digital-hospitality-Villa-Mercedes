<x-admin-layout>
    <x-slot name="header">Reservation Calendar</x-slot>
    <x-admin.page-header title="Reservation Calendar">
        <x-slot name="actions"><x-admin.button variant="secondary" :href="route('admin.decision-board')">Back to today</x-admin.button></x-slot>
    </x-admin.page-header>
    <x-admin.panel title="Date window" class="mb-8">
        <form method="GET" action="{{ route('admin.decision-board') }}">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div><label for="from" class="admin-label">From</label><input class="admin-input" id="from" type="date" name="from" value="{{ $from->toDateString() }}" required></div>
                <div><label for="to" class="admin-label">To</label><input class="admin-input" id="to" type="date" name="to" value="{{ $to->toDateString() }}" required></div>
                <div><label for="facility_type" class="admin-label">Show</label><select class="admin-input" id="facility_type" name="facility_type"><option value="">All rooms, halls, cottages, and packages</option>@foreach ($facilityTypes as $type)<option value="{{ $type->slug }}" @selected($facilityTypeSlug === $type->slug)>{{ $type->name }}</option>@endforeach</select></div>
            </div>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3"><p class="text-sm text-text-muted">Windows longer than 60 days are shortened to keep the timeline readable.</p><x-admin.button type="submit">Update view</x-admin.button></div>
            @if ($errors->any())<p class="mt-3 text-sm text-danger">{{ $errors->first() }}</p>@endif
        </form>
    </x-admin.panel>
    <!-- Unified timeline -->
    <section>
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <div>
                <h2 id="calendar-heading" class="text-lg font-semibold text-text">Property calendar</h2>
                <p class="text-sm text-text-muted">
                    {{ $from->format('M j, Y') }} – {{ $to->format('M j, Y') }}.
                    Bars cover the real hold time, including early check-in and late check-out.

                </p>
            </div>
            <div class="flex flex-wrap items-center gap-4 text-xs text-text">
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-primary"></span> Confirmed</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-warning"></span> Waiting (pending)</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border-2 border-warning bg-warning/20"></span> Also blocked by a package/link</span>
            </div>
        </div>

        @if (count($rows) && !collect($rows)->contains(fn ($row) => count($row['claims']) > 0))
            <p class="mb-3 text-sm text-text">No reservations shown for this date range and filter.</p>
        @endif
        <div data-timeline role="region" aria-labelledby="calendar-heading" tabindex="0"
             class="calendar-scroll overflow-auto rounded-xl border border-border bg-surface focus-visible:outline-2 focus-visible:outline-primary"
             style="--calendar-days: {{ count($days) }}">
          <div class="calendar-track">
            <!-- Day header -->
            <div class="sticky top-0 z-20 flex border-b border-border bg-muted">
                <div class="calendar-space sticky left-0 z-10 shrink-0 bg-muted px-3 py-2 text-xs font-semibold uppercase tracking-wider text-text-muted">
                    Space
                </div>
                <div data-day-track class="flex-1 relative h-10">
                    @foreach($days as $day)
                        <div class="absolute top-0 bottom-0 border-l border-border flex flex-col justify-center pl-1"
                             @style(['left: '.$day['offset'].'%', 'width: '.$day['width'].'%'])>
                            <span class="text-[10px] font-semibold text-text leading-tight">{{ $day['label'] }}</span>
                            <span class="text-[10px] text-text-muted leading-tight">{{ $day['sublabel'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            @php($currentType = null)
            @forelse($rows as $row)
                @php($typeName = $row['facility']->facilityType->name ?? 'Other')
                @if($currentType !== $typeName)
                    @php($currentType = $typeName)
                    <div class="flex border-b border-border bg-muted/50">
                        <div class="calendar-space sticky left-0 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-text">
                            {{ $typeName }}
                        </div>
                    </div>
                @endif

                <div class="flex border-b border-border/60 last:border-b-0 hover:bg-muted/70/60">
                    <div class="calendar-space sticky left-0 z-10 shrink-0 border-r border-border bg-surface px-3 py-3">
                        <p class="text-sm font-medium text-text break-words">{{ $row['facility']->name }}</p>
                        <p class="text-xs text-text-muted">
                            {{ $row['facility']->facilityType->name }}
                            @if ($row['facility']->cleaning_required_at)<span class="text-warning"> · Cleaning required now</span>@endif
                            @if ($row['facility']->status !== \App\Enums\FacilityStatus::Available)<span class="text-warning"> · {{ $row['facility']->status->label() }}</span>@endif
                            @unless($row['facility']->is_active)
                                <span class="text-warning">· retired (still has a hold)</span>
                            @endunless
                        </p>
                    </div>
                    <div class="flex-1 relative py-3 min-h-[3.5rem]">
                        @foreach($days as $day)
                            <div class="absolute top-0 bottom-0 border-l border-border/60" @style(['left: '.$day['offset'].'%'])></div>
                        @endforeach

                        @forelse($row['claims'] as $bar)
                            @php($claim = $bar['claim'])
                            @php($isPending = $claim->status === 'Pending')
                            @php($linked = $claim->isLinkedHold)
                            <a href="{{ $claim->url }}"
                               aria-label="{{ $row['facility']->name }} — {{ $claim->label }} — {{ $claim->durationLabel() }} ({{ $claim->status }}{{ $claim->note ? ', '.$claim->note : '' }})"
                               @class([
                                   'absolute top-2 bottom-2 rounded px-2 py-1 overflow-hidden text-[10px] font-medium transition hover:opacity-90',
                                   'bg-warning text-white' => $isPending && ! $linked,
                                   'bg-primary text-white' => ! $isPending && ! $linked,
                                   'border-2 border-warning bg-warning-light text-warning' => $isPending && $linked,
                                   'border-2 border-primary bg-primary-light text-primary' => ! $isPending && $linked,
                               ])
                               @style(['left: '.$bar['offset'].'%', 'width: '.$bar['width'].'%'])
                               title="{{ $claim->label }} — {{ $claim->durationLabel() }} ({{ $claim->status }}{{ $claim->note ? ', ' . $claim->note : '' }})">
                                <span class="block truncate">{{ $claim->label }}</span>
                                <span class="block truncate opacity-90">{{ $claim->durationLabel() }}</span>
                            </a>
                        @empty
                            <span class="absolute left-2 top-1/2 -translate-y-1/2 text-xs text-text-muted">No reservations shown</span>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-text-muted">No spaces match this filter.</div>
            @endforelse
          </div>
        </div>
    </section>
</x-admin-layout>
