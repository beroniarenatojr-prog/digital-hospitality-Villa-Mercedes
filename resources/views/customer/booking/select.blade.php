<x-customer-layout>
    @php
        $services = [
            'room' => 'Rooms & suites',
            'function_hall' => 'Function halls',
            'resort_package' => 'Resort packages',
            'cottage' => 'Cottages',
        ];

        $isCottage = $type === 'cottage';
        $guestCount = isset($selection['guests']) ? (int) $selection['guests'] : null;

        // The dates a guest arrives with are the ones the wizard prefills, so a
        // request carrying precise times is read the same way here as there.
        $arrival = $selection['arrival'] ?? $selection['check_in'] ?? null;
        $departure = $selection['departure'] ?? $selection['check_out'] ?? null;
        $visitDate = $selection['date'] ?? null;
        $preciseTimes = ! empty($selection['arrival']) && ! empty($selection['departure']);

        $datesApplied = $period !== null;
        $partyApplied = $guestCount !== null;
        $filtered = $datesApplied || $partyApplied;

        $dayLabel = fn (?string $value): ?string => $value ? \Carbon\Carbon::parse($value)->format('D, j M Y') : null;

        // Anything the guest already chose — on the public availability bar, or on
        // a space page they came back from — is announced here, never asked for
        // again, and travels on to the wizard, which prefills it.
        $carried = collect([
            ! $preciseTimes && $arrival ? 'Date In '.$dayLabel($arrival) : null,
            ! $preciseTimes && $departure ? 'Date Out '.$dayLabel($departure) : null,
            $isCottage && $visitDate ? 'Visit date '.$dayLabel($visitDate) : null,
            $partyApplied ? $guestCount.' '.Str::plural('guest', $guestCount) : null,
        ])->filter()->implode(' · ');

        // A GET form, so the fields show the query the guest is currently standing
        // on: what the filter did is visible, and adjusting it does not retype it.
        $day = fn (?string $value): string => $value ? \Carbon\Carbon::parse($value)->format('Y-m-d') : '';
        $filterValue = fn (mixed $value): string => $value === null ? '' : (string) $value;

        // The dates the form opens on. A request that carried precise times (a
        // space page handing back its window) has no calendar dates of its own, so
        // its day is offered here rather than an empty field that hides the filter.
        $checkInField = $filterValue($selection['check_in'] ?? null) ?: $day($arrival);
        $checkOutField = $filterValue($selection['check_out'] ?? null) ?: $day($departure);
        $visitField = $filterValue($visitDate ?? null) ?: $day($arrival);

        $selectionForTab = fn (string $key): array => $selectionByService[$key] ?? $selection;
        $spaceCount = $facilities->count();
    @endphp

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">

            <x-customer.page-header
                eyebrow="New reservation"
                title="Choose a space"
                :description="$datesApplied
                    ? ($spaceCount.' '.Str::plural('space', $spaceCount).' free for your dates. Pool entrance fees are separate and paid at the resort.')
                    : ($spaceCount.' '.Str::plural('space', $spaceCount).' to reserve online. Pool entrance fees are separate and paid at the resort. Ordered by lowest published rate first.')"
            >
                <x-slot name="actions">
                    <x-customer.step-pill :step="2" label="Choose space" />
                </x-slot>
            </x-customer.page-header>

            {{--
                Horizontal availability bar — same handshake as the Figma search
                strip: equal field tiles in a row, then the action on the right.
                Cottage swaps check-in/out for visit date + swimming session but
                keeps the row layout so the page never drops into a stacked form.
            --}}
            <section aria-labelledby="availability-filter-heading">
                <h2 id="availability-filter-heading" class="sr-only">
                    @if($datesApplied)
                        Spaces free for your dates
                    @elseif($partyApplied)
                        Spaces for your party
                    @else
                        Check what is free first
                    @endif
                </h2>

                <form
                    method="GET"
                    action="{{ route('customer.reservations.new') }}"
                    x-data="{
                        serviceType: @js($type),
                        get isCottage() { return this.serviceType === 'cottage'; },
                        get partyLabel() { return this.serviceType === 'function_hall' ? 'Attendees' : 'Guests'; },
                    }"
                    class="flex flex-col gap-3 lg:flex-row lg:items-stretch"
                >
                    <input type="hidden" name="type" value="{{ $type }}" x-bind:value="serviceType">

                    <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-3">
                        {{-- Cottage: visit date + session --}}
                        <label
                            @if(! $isCottage) x-cloak @endif
                            x-show="isCottage"
                            for="picker-date"
                            class="flex min-h-[4.5rem] cursor-pointer items-center gap-3 rounded-xl border border-border bg-surface px-4 py-3 shadow-sm focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20"
                        >
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-primary-light text-primary" aria-hidden="true">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[10px] font-semibold uppercase tracking-[0.14em] text-text-muted">Visit date</span>
                                <input
                                    id="picker-date"
                                    name="date"
                                    type="date"
                                    class="mt-0.5 w-full border-0 bg-transparent p-0 text-sm font-semibold text-text shadow-none focus:border-0 focus:ring-0"
                                    value="{{ $visitField }}"
                                    min="{{ now()->format('Y-m-d') }}"
                                    @disabled(! $isCottage)
                                    x-bind:disabled="!isCottage"
                                >
                            </span>
                        </label>

                        <label
                            @if(! $isCottage) x-cloak @endif
                            x-show="isCottage"
                            for="picker-session"
                            class="flex min-h-[4.5rem] cursor-pointer items-center gap-3 rounded-xl border border-border bg-surface px-4 py-3 shadow-sm focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20"
                        >
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-primary-light text-primary" aria-hidden="true">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[10px] font-semibold uppercase tracking-[0.14em] text-text-muted">Swimming session</span>
                                <select
                                    id="picker-session"
                                    name="session"
                                    @disabled(! $isCottage)
                                    x-bind:disabled="!isCottage"
                                    class="mt-0.5 w-full border-0 bg-transparent p-0 text-sm font-semibold text-text shadow-none focus:border-0 focus:ring-0"
                                >
                                    <option value="">Any session</option>
                                    @foreach(\App\Models\SwimmingSession::query()->where('is_active', true)->orderBy('sort_order')->get() as $swimmingSession)
                                        <option value="{{ $swimmingSession->slug }}" @selected(($selection['session'] ?? null) === $swimmingSession->slug)>{{ $swimmingSession->name }}</option>
                                    @endforeach
                                </select>
                            </span>
                        </label>

                        {{-- Overnight / halls / packages: check-in + check-out --}}
                        <label
                            @if($isCottage) x-cloak @endif
                            x-show="!isCottage"
                            for="picker-check-in"
                            class="flex min-h-[4.5rem] cursor-pointer items-center gap-3 rounded-xl border border-border bg-surface px-4 py-3 shadow-sm focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20"
                        >
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-primary-light text-primary" aria-hidden="true">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[10px] font-semibold uppercase tracking-[0.14em] text-text-muted">Check-in</span>
                                <input
                                    id="picker-check-in"
                                    name="check_in"
                                    type="date"
                                    class="mt-0.5 w-full border-0 bg-transparent p-0 text-sm font-semibold text-text shadow-none focus:border-0 focus:ring-0"
                                    value="{{ $checkInField }}"
                                    min="{{ now()->format('Y-m-d') }}"
                                    @disabled($isCottage)
                                    x-bind:disabled="isCottage"
                                >
                            </span>
                        </label>

                        <label
                            @if($isCottage) x-cloak @endif
                            x-show="!isCottage"
                            for="picker-check-out"
                            class="flex min-h-[4.5rem] cursor-pointer items-center gap-3 rounded-xl border border-border bg-surface px-4 py-3 shadow-sm focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20"
                        >
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-primary-light text-primary" aria-hidden="true">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[10px] font-semibold uppercase tracking-[0.14em] text-text-muted">Check-out</span>
                                <input
                                    id="picker-check-out"
                                    name="check_out"
                                    type="date"
                                    class="mt-0.5 w-full border-0 bg-transparent p-0 text-sm font-semibold text-text shadow-none focus:border-0 focus:ring-0"
                                    value="{{ $checkOutField }}"
                                    @disabled($isCottage)
                                    x-bind:disabled="isCottage"
                                >
                            </span>
                        </label>

                        <label
                            for="picker-guests"
                            class="flex min-h-[4.5rem] cursor-pointer items-center gap-3 rounded-xl border border-border bg-surface px-4 py-3 shadow-sm focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20"
                        >
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-primary-light text-primary" aria-hidden="true">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[10px] font-semibold uppercase tracking-[0.14em] text-text-muted" x-text="partyLabel">{{ $type === 'function_hall' ? 'Attendees' : 'Guests' }}</span>
                                <input
                                    id="picker-guests"
                                    name="guests"
                                    type="number"
                                    min="1"
                                    max="500"
                                    inputmode="numeric"
                                    placeholder="2"
                                    value="{{ $filterValue($guestCount) }}"
                                    class="mt-0.5 w-full border-0 bg-transparent p-0 text-sm font-semibold text-text shadow-none focus:border-0 focus:ring-0"
                                >
                            </span>
                        </label>
                    </div>

                    <div class="flex shrink-0 flex-col gap-2 lg:w-48">
                        <x-primary-button type="submit" class="min-h-[4.5rem] w-full justify-center uppercase tracking-[0.1em]">
                            {{ $filtered ? 'Modify search' : 'Check availability' }}
                        </x-primary-button>
                        @if($filtered)
                            <a href="{{ route('customer.reservations.new', ['type' => $type]) }}"
                               class="inline-flex min-h-11 items-center justify-center text-sm font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                                Show every space
                            </a>
                        @endif
                    </div>
                </form>

                <p class="mt-3 text-xs italic text-text-muted">
                    Dates are enough here. The exact check in and check-out times are settled on the next step.
                </p>
            </section>

            @if($carried !== '' && ! $filtered)
                <p class="border-l border-primary py-2 pl-4 text-sm text-text-muted">
                    Carrying over <strong class="font-semibold text-text">{{ $carried }}</strong>. You can change these on the next step.
                </p>
            @endif

            {{--
                Four services, and with dates in hand each one says how much it
                has free: a guest whose tab is empty is pointed at the next one
                instead of having to try all four.
            --}}
            <nav class="-mx-4 border-y border-border px-4 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8" aria-label="Reservation services">
                <ul class="flex flex-wrap gap-x-1 gap-y-1">
                    @foreach($services as $key => $label)
                        @php $free = $openByService[$key] ?? null; @endphp
                        <li>
                            <a href="{{ route('customer.reservations.new', [...$selectionForTab($key), 'type' => $key]) }}"
                               @class([
                                   'inline-flex min-h-11 items-center gap-2 border-b-2 px-3 py-3 text-xs font-semibold uppercase tracking-[0.12em] transition -mb-px focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1',
                                   'border-primary text-primary' => $type === $key,
                                   'border-transparent text-text-muted hover:border-border hover:text-text' => $type !== $key,
                               ])
                               @if($type === $key) aria-current="page" @endif>
                                {{ $label }}
                                @if($free !== null)
                                    {{-- On the tab the guest is standing on the chip is
                                         chrome; on the others it is a pointer, so a
                                         service with nothing to show reads at a glance.
                                         "None free" rather than "full": the service may
                                         simply have nothing listed, and the chip must not
                                         claim the resort is booked out. --}}
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-[10px] font-bold normal-case tracking-normal',
                                        'bg-primary/10 text-primary' => $type === $key,
                                        'bg-secondary-light text-text' => $type !== $key && $free > 0,
                                        'bg-danger-light text-danger' => $type !== $key && $free === 0,
                                    ])>
                                        @if($free === 0)
                                            none free
                                        @else
                                            {{ $free }} free
                                        @endif
                                    </span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <section aria-labelledby="spaces-heading" class="space-y-5">
                <h2 id="spaces-heading" class="sr-only">Available spaces</h2>

                @if($facilities->isEmpty())
                    @php
                        // Where a guest with no results should go: the services that
                        // do have something, so "nothing here" is never a dead end.
                        $elsewhere = collect($openByService)
                            ->filter(fn (int $count, string $key): bool => $key !== $type && $count > 0)
                            ->sortDesc();

                        // ...and the dates that do have something, since a guest who
                        // picked one busy weekend off a full calendar is the common
                        // case, not the exception. Each suggestion keeps the tab, the
                        // party and the swimming session they asked with, so clicking
                        // one is the same question asked of different days.
                        $nearby = collect($nearbyDates)->map(function (array $idea) use ($isCottage, $guests, $selection, $type, $dayLabel): array {
                            $shifted = $idea['period'];
                            $days = abs($idea['shift']);

                            return [
                                'label' => $isCottage
                                    ? 'Visit '.$dayLabel($shifted->checkInDate)
                                    : ($shifted->checkInDate === $shifted->checkOutDate
                                        ? $dayLabel($shifted->checkInDate)
                                        : $dayLabel($shifted->checkInDate).' – '.$dayLabel($shifted->checkOutDate)),
                                'note' => $days.' '.Str::plural('day', $days).($idea['shift'] > 0 ? ' later' : ' earlier'),
                                'count' => $idea['count'],
                                'url' => route('customer.reservations.new', array_filter([
                                    'type' => $type,
                                    'check_in' => $shifted->checkInDate,
                                    'check_out' => $shifted->checkOutDate,
                                    'session' => $selection['session'] ?? null,
                                    'guests' => $guests,
                                ], fn ($value): bool => $value !== null && $value !== '')),
                            ];
                        });
                    @endphp

                    <x-empty-state
                        icon="calendar"
                        :title="$datesApplied ? 'Nothing is free for those dates' : ($partyApplied ? 'Nothing here fits that party size' : 'Nothing is listed under this service')"
                        :description="! $filtered
                            ? 'Try another service above. If you expected something here, contact the resort and staff will help you find the right space.'
                            : ($nearby->isNotEmpty()
                                ? 'The nearest dates that are free are below, with the same party. The services underneath are checked against your dates too.'
                                : ($nearbyChecked
                                    ? 'The '.$nearbyRadius.' days either side are taken too. Try a different date or a smaller party, or look at the services below — they are checked against the same dates.'
                                    : 'Try a different date or a smaller party, or look at the services below — they are checked against the same dates.'))"
                        class="rounded-2xl border border-border border-solid bg-surface py-16 shadow-sm"
                    >
                        <x-slot name="action">
                            @if($filtered)
                                @if($nearby->isNotEmpty())
                                    <div class="mb-5 w-full">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Nearby dates with space</p>
                                        <ul class="mt-3 flex flex-wrap items-center justify-center gap-3">
                                            @foreach($nearby as $idea)
                                                <li>
                                                    <a href="{{ $idea['url'] }}"
                                                       class="inline-flex min-h-[44px] flex-wrap items-center justify-center gap-2 rounded-lg border border-primary bg-surface px-4 py-2 text-sm font-semibold text-primary transition hover:bg-primary-light focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                                                        {{ $idea['label'] }}
                                                        <span class="text-xs font-normal text-text-muted">{{ $idea['note'] }} · {{ $idea['count'] }} free</span>
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div class="flex flex-wrap items-center justify-center gap-3">
                                    <x-secondary-button tag="a" href="{{ route('customer.reservations.new', ['type' => $type]) }}">
                                        Show every space instead
                                    </x-secondary-button>

                                    @foreach($elsewhere->take(2) as $key => $count)
                                        <x-primary-button tag="a" href="{{ route('customer.reservations.new', [...$selectionForTab($key), 'type' => $key]) }}">
                                            {{ $services[$key] }} · {{ $count }} free
                                        </x-primary-button>
                                    @endforeach
                                </div>
                            @endif
                        </x-slot>
                    </x-empty-state>
                @else
                    <ul class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 lg:gap-8">
                        @foreach($facilities as $facility)
                            <li>
                                {{-- Same card chrome as the public services listings linked from the landing page. --}}
                                <x-facility-card
                                    :facility="$facility"
                                    :query="$selection"
                                    :available="$datesApplied"
                                    :show-description="true"
                                />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <x-customer.trust-strip />
        </div>
    </div>
</x-customer-layout>
