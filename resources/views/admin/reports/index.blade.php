@php
    $reportNow = \App\Support\ResortTime::now();
    $snapshot = $reportView === 'balances';
    $quickRanges = [
        'Today' => [$reportNow->copy(), $reportNow->copy()],
        'This month' => [$reportNow->copy()->startOfMonth(), $reportNow->copy()],
        'Last month' => [$reportNow->copy()->subMonthNoOverflow()->startOfMonth(), $reportNow->copy()->subMonthNoOverflow()->endOfMonth()],
        'This year' => [$reportNow->copy()->startOfYear(), $reportNow->copy()],
    ];
@endphp
<x-admin-layout>
    <x-slot name="header">Reports and insights</x-slot>
    <x-admin.page-header title="Reports and insights">
        <x-slot:actions>
            <x-admin.button variant="secondary" :href="route('admin.reports.export.csv', $reportQuery)">Download CSV</x-admin.button>
            <x-admin.button :href="route('admin.reports.export.pdf', $reportQuery)" target="_blank" rel="noopener">Print / Save PDF</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <nav aria-label="Report views" class="mb-6 grid grid-cols-2 gap-1 rounded-xl border border-border bg-surface p-1.5 lg:grid-cols-4">
        @foreach (\App\Services\Admin\ManagementReports::VIEWS as $key => $label)
            <a href="{{ route('admin.reports.index', array_merge($reportQuery, ['view' => $key])) }}"
               @if ($reportView === $key) aria-current="page" @endif
               class="rounded-lg px-4 py-3 text-center text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary {{ $reportView === $key ? 'bg-primary text-white' : 'text-text-muted hover:bg-muted hover:text-text' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <x-admin.filter-bar :action="route('admin.reports.index')" :title="$snapshot ? 'Balance filters' : 'Report period'">
        <input type="hidden" name="view" value="{{ $reportView }}">
        @unless ($snapshot)
            <x-admin.filter-field label="Date from" for="start_date" :error="$errors->get('start_date')">
                <input class="admin-input mt-1" type="date" id="start_date" name="start_date" value="{{ old('start_date', $startDate?->format('Y-m-d')) }}" required @error('start_date') aria-invalid="true" @enderror>
            </x-admin.filter-field>
            <x-admin.filter-field label="Date until" for="end_date" :error="$errors->get('end_date')">
                <input class="admin-input mt-1" type="date" id="end_date" name="end_date" value="{{ old('end_date', $endDate?->format('Y-m-d')) }}" required @error('end_date') aria-invalid="true" @enderror>
            </x-admin.filter-field>
        @endunless
        @if (in_array($reportView, ['executive', 'services'], true))
            <x-admin.filter-field label="Service type" for="facility_type">
                <select id="facility_type" name="facility_type" class="admin-input mt-1">
                    <option value="">All services</option>
                    @foreach (\App\Enums\FacilityType::cases() as $type)
                        <option value="{{ $type->value }}" @selected($filters->facilityType === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </x-admin.filter-field>
        @endif
        <x-admin.filter-field label="Booking source" for="source">
            <select id="source" name="source" class="admin-input mt-1">
                <option value="">All sources</option>
                @foreach (\App\Enums\ReservationSource::cases() as $source)
                    <option value="{{ $source->value }}" @selected($filters->source === $source->value)>{{ $source->label() }}</option>
                @endforeach
            </select>
        </x-admin.filter-field>
        @unless ($snapshot)
            <x-admin.filter-field label="Chart interval" for="grouping">
                <select id="grouping" name="grouping" class="admin-input mt-1">
                    @foreach (['auto' => 'Automatic', 'day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year'] as $key => $label)
                        <option value="{{ $key }}" @selected($grouping === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-admin.filter-field>
            <x-slot:below>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($quickRanges as $label => [$from, $until])
                        <x-admin.button variant="secondary" size="sm" :href="route('admin.reports.index', array_merge($reportQuery, ['start_date' => $from->format('Y-m-d'), 'end_date' => $until->format('Y-m-d'), 'grouping' => 'auto']))">{{ $label }}</x-admin.button>
                    @endforeach
                </div>
            </x-slot:below>
        @endunless
        <x-slot:actions>
            @if ($filters->facilityType || $filters->source)
                <x-admin.button variant="secondary" size="sm" :href="route('admin.reports.index', array_diff_key($reportQuery, array_flip(['facility_type', 'source'])))">Clear filters</x-admin.button>
            @endif
        </x-slot:actions>
    </x-admin.filter-bar>

    <section aria-labelledby="report-heading" class="mb-6">
        <h2 id="report-heading" class="text-xl font-semibold text-text">{{ $report['title'] }}</h2>
        @unless ($snapshot)
            <p class="mt-1 text-sm font-medium">{{ $startDate->format('M j, Y') }} to {{ $endDate->format('M j, Y') }} · Philippine time</p>
        @endunless
        <p class="mt-1 text-sm text-text-muted">{{ $report['basis'] }}</p>
        <p class="mt-1 text-sm text-text-muted">{{ $report['note'] }}</p>
        @unless (collect($report['tables'])->contains(fn ($table) => count($table['rows']) > 0))
            <p class="mt-3 text-sm">
                {{ $snapshot ? 'No balance records match the current filters.' : 'No records match this period and the applied filters.' }}
                <a class="font-semibold text-primary underline" href="{{ $snapshot ? '#source' : '#start_date' }}">{{ $snapshot ? 'Change the booking source' : 'Change the dates or broaden the filters' }}</a> and select Apply.
            </p>
        @endunless
    </section>
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 {{ count($report['metrics']) > 2 ? 'xl:grid-cols-4' : '' }}">
        @foreach ($report['metrics'] as $metric)
            <x-admin.stat :label="$metric['label']" :note="$metric['note']">
                @if ($metric['money'])<x-amount :value="$metric['value']" />@else{{ number_format($metric['value']) }}@endif
            </x-admin.stat>
        @endforeach
    </div>
    @include('admin.reports.partials.management-findings')
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        @foreach ($report['charts'] as $chart)
            @include('admin.reports.partials.management-chart')
        @endforeach
    </div>
    @foreach ($report['tables'] as $table)
        @if ($table['detail'])
            <x-content-panel class="mb-6 rounded-xl border border-border bg-surface p-5">
                <x-slot:heading>{{ $table['title'] }} ({{ number_format(count($table['rows'])) }})</x-slot:heading>
                <div class="mt-4">@include('admin.reports.partials.management-table')</div>
            </x-content-panel>
        @else
            <x-admin.panel :title="$table['title']" class="mb-6">
                @include('admin.reports.partials.management-table')
            </x-admin.panel>
        @endif
    @endforeach
</x-admin-layout>
