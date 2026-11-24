@php
    $reportNow = \App\Support\ResortTime::now();
    $opsView = $opsView ?? 'registry';

    // The quick ranges are a shift lead's one-click window. The bar shows the
    // dates they land on, which is what keeps the live in-house list from being
    // read as a filtered history query.
    $quickRanges = [
        'Today' => [$reportNow->copy()->startOfDay(), $reportNow->copy()->endOfDay()],
        'Last 7 days' => [$reportNow->copy()->subDays(6)->startOfDay(), $reportNow->copy()->endOfDay()],
        'This month' => [$reportNow->copy()->startOfMonth(), $reportNow->copy()->endOfMonth()],
        'This year' => [$reportNow->copy()->startOfYear(), $reportNow->copy()->endOfYear()],
    ];

    $opsTabs = [
        'registry' => ['label' => 'Guest registry', 'description' => 'Guest contacts, current stays and check-in history'],
        'action-lists' => ['label' => "Today's action lists", 'description' => 'Walk-ins, missed check ins, overdue check outs'],
    ];

@endphp

<x-admin-layout>
    <x-slot name="header">Front desk reports</x-slot>

    <x-admin.page-header
        title="Front desk reports"
    >
        <x-slot:actions>
            <x-admin.button variant="secondary" :href="route('admin.reports.index', $reportQuery)">Back to reports</x-admin.button>
            <x-admin.button :href="route('admin.reports.export.pdf', array_merge($reportQuery, ['view' => 'operations', 'section' => $opsView]))" target="_blank">Print report / Save PDF</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <nav aria-label="Operational views" class="mb-6 flex flex-col gap-1 rounded-xl border border-border bg-surface p-1.5 sm:flex-row">
        @foreach ($opsTabs as $key => $tab)
            @php($isActive = $opsView === $key)
            <a
                href="{{ route('admin.reports.operational', array_merge($reportQuery, ['ops' => $key])) }}"
                @if ($isActive) aria-current="page" @endif
                class="rounded-lg px-4 py-2.5 transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 sm:flex-1 {{ $isActive ? 'bg-primary text-white' : 'text-text-muted hover:bg-muted hover:text-text' }}"
            >
                <span class="block text-sm font-semibold">{{ $tab['label'] }}</span>
                <span class="mt-0.5 block text-xs {{ $isActive ? 'text-white/80' : 'text-text-muted' }}">{{ $tab['description'] }}</span>
            </a>
        @endforeach
    </nav>

    @if ($opsView === 'registry')
    <x-admin.filter-bar :action="route('admin.reports.operational')" title="Filters">
        <x-slot:actions>
            @if ($filters->startDate || $filters->endDate || $filters->facilityType || $filters->source)
                <x-admin.button
                    variant="secondary"
                    size="sm"
                    :href="route('admin.reports.operational', ['ops' => $opsView])"
                >Clear filters</x-admin.button>
            @endif
        </x-slot:actions>

        <input type="hidden" name="ops" value="{{ $opsView }}">

        <x-admin.filter-field label="Checked in from" for="start_date" :error="$errors->get('start_date')">
            <input
                id="start_date"
                name="start_date"
                type="date"
                value="{{ old('start_date', $startDate?->format('Y-m-d')) }}"
                @error('start_date') aria-invalid="true" @enderror
                class="admin-input mt-1"
            >
        </x-admin.filter-field>

        <x-admin.filter-field label="Checked in until" for="end_date" :error="$errors->get('end_date')">
            <input
                id="end_date"
                name="end_date"
                type="date"
                value="{{ old('end_date', $endDate?->format('Y-m-d')) }}"
                @error('end_date') aria-invalid="true" @enderror
                class="admin-input mt-1"
            >
        </x-admin.filter-field>

        <x-admin.filter-field label="Service type" for="facility_type">
            <select id="facility_type" name="facility_type" class="admin-input mt-1">
                <option value="">All services</option>
                @foreach (\App\Enums\FacilityType::cases() as $type)
                    <option value="{{ $type->value }}" @selected($filters->facilityType === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </x-admin.filter-field>

        <x-admin.filter-field label="Source" for="source">
            <select id="source" name="source" class="admin-input mt-1">
                <option value="">All sources</option>
                @foreach (\App\Enums\ReservationSource::cases() as $source)
                    <option value="{{ $source->value }}" @selected($filters->source === $source->value)>{{ $source->label() }}</option>
                @endforeach
            </select>
        </x-admin.filter-field>

        <x-slot:below>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-text-muted">Quick range</span>
                @foreach ($quickRanges as $label => [$presetStart, $presetEnd])
                    <x-admin.button
                        variant="secondary"
                        size="sm"
                        :href="route('admin.reports.operational', array_merge($reportQuery, ['ops' => $opsView, 'start_date' => $presetStart->format('Y-m-d'), 'end_date' => $presetEnd->format('Y-m-d')]))"
                    >{{ $label }}</x-admin.button>
                @endforeach
            </div>
        </x-slot:below>
    </x-admin.filter-bar>

    @endif

    @if ($opsView === 'action-lists')
        <p class="mb-4 text-sm text-text-muted">Live shift report as of {{ $reportNow->format('M j, Y g:i A T') }}. Use the Dashboard for daily work and the Reservation Calendar for availability.</p>
        @include('admin.reports.partials.operational-action-lists')
    @else
        @include('admin.reports.partials.operational-registry')
    @endif
</x-admin-layout>
