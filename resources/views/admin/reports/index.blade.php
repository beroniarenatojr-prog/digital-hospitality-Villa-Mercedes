<x-admin-layout>
    <x-slot name="header">
        Reporting & Analytics
    </x-slot>

    @php
        $reportQuery = array_filter([
            'start_date' => $startDate?->format('Y-m-d'),
            'end_date' => $endDate?->format('Y-m-d'),
            'grouping' => $grouping,
        ]);
        $statusTotal = max(1, (int) $bookingSummary['total']);
        $hasTrendValues = collect($bookingTrends)->contains(fn (array $point) => ($point['value'] ?? 0) > 0);
        $presetRanges = [
            'Today' => [now()->startOfDay(), now()->endOfDay()],
            'Last 7 days' => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
            'This month' => [now()->startOfMonth(), now()->endOfMonth()],
            'Last month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
        ];
        $metricChange = static function (float $current, float $previous): ?array {
            if ($previous <= 0) {
                return null;
            }

            $percentage = (($current - $previous) / $previous) * 100;

            return [
                'percentage' => abs($percentage),
                'direction' => $percentage > 0 ? 'up' : ($percentage < 0 ? 'down' : 'same'),
            ];
        };
        $reservationChange = $comparison
            ? $metricChange((float) $bookingSummary['total'], (float) $comparison['bookingSummary']['total'])
            : null;
        $valueChange = $comparison
            ? $metricChange((float) $revenueSummary['reservation_value'], (float) $comparison['revenueSummary']['reservation_value'])
            : null;
    @endphp

    <!-- Date Range Controls -->
    <div class="mb-8 bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4 mb-6">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Report filters</h2>
                <p class="text-sm text-gray-500 mt-0.5">Filter by reservation creation date, then export or review charts below.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a
                    href="{{ route('admin.reports.export.csv', $reportQuery) }}"
                    class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Export Excel (CSV)
                </a>
                <a
                    href="{{ route('admin.reports.export.pdf', $reportQuery) }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                    Export PDF
                </a>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.reports.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
            <input type="hidden" name="grouping" value="{{ $grouping }}">

            <!-- Date From -->
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" name="start_date" id="start_date" value="{{ $startDate ? $startDate->format('Y-m-d') : '' }}"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                <x-input-error class="mt-1" :messages="$errors->get('start_date')" />
            </div>

            <!-- Date Until -->
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">Date Until</label>
                <input type="date" name="end_date" id="end_date" value="{{ $endDate ? $endDate->format('Y-m-d') : '' }}"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                <x-input-error class="mt-1" :messages="$errors->get('end_date')" />
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-3">
                <button type="submit" class="flex-1 inline-flex justify-center items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-primary-dark transition">
                    Apply Filters
                </button>
                @if($startDate || $endDate)
                    <a href="{{ route('admin.reports.index', ['grouping' => $grouping]) }}" class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-sm text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                        Clear
                    </a>
                @endif
            </div>
        </form>
        <div class="mt-4 flex flex-wrap items-center gap-2" aria-label="Quick date ranges">
            <span class="mr-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Quick range</span>
            @foreach($presetRanges as $label => [$presetStart, $presetEnd])
                @php
                    $isActivePreset = $startDate?->isSameDay($presetStart) && $endDate?->isSameDay($presetEnd);
                @endphp
                <a
                    href="{{ route('admin.reports.index', ['start_date' => $presetStart->format('Y-m-d'), 'end_date' => $presetEnd->format('Y-m-d'), 'grouping' => $grouping]) }}"
                    class="rounded-full border px-3 py-1.5 text-xs font-medium transition {{ $isActivePreset ? 'border-primary bg-primary text-white' : 'border-gray-300 bg-white text-gray-700 hover:border-primary hover:text-primary' }}"
                >{{ $label }}</a>
            @endforeach
        </div>
        <p class="mt-4 text-xs text-gray-500">
            Note: Reservation trends and summaries are based on the reservation creation date. Payment summaries are based on the payment date.
        </p>
        @if($comparison)
            <p class="mt-1 text-xs text-gray-500">
                Comparisons use the preceding {{ $comparison['startDate']->diffInDays($comparison['endDate']) + 1 }}-day period:
                {{ $comparison['startDate']->format('M j, Y') }}–{{ $comparison['endDate']->format('M j, Y') }}.
            </p>
        @endif
    </div>

    @if($bookingSummary['total'] > 0 || $paymentSummary['total_payments'] > 0)

        <!-- Summary KPIs -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Reservations -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-col gap-3">
                <div class="self-start p-3 rounded-full bg-blue-100 text-blue-600">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <div class="w-full flex flex-col gap-1 min-w-0">
                    <p class="text-sm font-medium text-gray-500">Total Reservations</p>
                    <p class="text-2xl font-bold text-gray-900 break-words">{{ number_format($bookingSummary['total']) }}</p>
                    @if($reservationChange)
                        <p class="text-xs font-medium {{ $reservationChange['direction'] === 'up' ? 'text-green-700' : ($reservationChange['direction'] === 'down' ? 'text-amber-700' : 'text-gray-500') }}">
                            {{ $reservationChange['direction'] === 'up' ? '↑' : ($reservationChange['direction'] === 'down' ? '↓' : '—') }}
                            {{ number_format($reservationChange['percentage'], 1) }}% vs previous period
                        </p>
                    @elseif($comparison)
                        <p class="text-xs text-gray-500">No reservations in the previous period</p>
                    @endif
                </div>
            </div>

            <!-- Reservation Value -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-col gap-3">
                <div class="self-start p-3 rounded-full bg-purple-100 text-purple-600">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"></path></svg>
                </div>
                <div class="w-full flex flex-col gap-1 min-w-0">
                    <p class="text-sm font-medium text-gray-500">Reservation Value</p>
                    <p class="text-2xl font-bold text-gray-900 break-words"><span class="mr-1">₱</span>{{ number_format($revenueSummary['reservation_value'], 2) }}</p>
                    @if($valueChange)
                        <p class="text-xs font-medium {{ $valueChange['direction'] === 'up' ? 'text-green-700' : ($valueChange['direction'] === 'down' ? 'text-amber-700' : 'text-gray-500') }}">
                            {{ $valueChange['direction'] === 'up' ? '↑' : ($valueChange['direction'] === 'down' ? '↓' : '—') }}
                            {{ number_format($valueChange['percentage'], 1) }}% vs previous period
                        </p>
                    @elseif($comparison)
                        <p class="text-xs text-gray-500">No reservation value in the previous period</p>
                    @endif
                </div>
            </div>

            <!-- Verified Payment Revenue -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-col gap-3">
                <div class="self-start p-3 rounded-full bg-green-100 text-green-600">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div class="w-full flex flex-col gap-1 min-w-0">
                    <p class="text-sm font-medium text-gray-500">Verified Payment</p>
                    <p class="text-2xl font-bold text-gray-900 break-words"><span class="mr-1">₱</span>{{ number_format($revenueSummary['verified_payment_revenue'], 2) }}</p>
                </div>
            </div>

            <!-- Outstanding Balance -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-col gap-3">
                <div class="self-start p-3 rounded-full bg-amber-100 text-amber-700">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M5 5l14 14"></path></svg>
                </div>
                <div class="w-full flex flex-col gap-1 min-w-0">
                    <p class="text-sm font-medium text-gray-500">Outstanding Balance</p>
                    <p class="text-2xl font-bold text-gray-900 break-words"><span class="mr-1">₱</span>{{ number_format($revenueSummary['outstanding_balance'], 2) }}</p>
                    <p class="text-xs text-gray-500">Selected reservations minus their verified payments</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8 items-stretch">
            <!-- Reservation Trends -->
            <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden h-full" aria-labelledby="reservation-trends-heading">
                    <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gray-50 flex flex-wrap items-center justify-between gap-3">
                        <h3 id="reservation-trends-heading" class="font-semibold text-gray-900">Reservation Trends</h3>
                        <div class="flex shrink-0 rounded-md border border-gray-200 bg-white p-0.5 text-xs font-medium" role="group" aria-label="Reservation trends timeframe">
                            @foreach(['week' => 'Week', 'month' => 'Month', 'year' => 'Year'] as $value => $label)
                                <a
                                    href="{{ route('admin.reports.index', array_merge($reportQuery, ['grouping' => $value])) }}"
                                    class="rounded-sm px-2.5 py-1 transition {{ $grouping === $value ? 'bg-primary text-white' : 'text-gray-600 hover:text-gray-900' }}"
                                    aria-pressed="{{ $grouping === $value ? 'true' : 'false' }}"
                                >{{ $label }}</a>
                            @endforeach
                        </div>
                    </div>
                    <div class="p-4 sm:p-6">
                        @if(! $hasTrendValues)
                            <x-empty-state
                                icon="chart-bar"
                                title="No Trends Available"
                                description="There is no historical reservation data for the selected period."
                            />
                        @else
                            <x-admin.column-chart
                                :points="$bookingTrends"
                                aria-label="Reservation trends by {{ $grouping }}"
                            />
                        @endif
                    </div>
            </section>

            <!-- By Source -->
            <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden h-full" aria-labelledby="reservation-source-heading">
                <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 id="reservation-source-heading" class="font-semibold text-gray-900">Reservations by Source</h3>
                </div>
                <ul class="p-4 sm:p-6 space-y-4">
                    @forelse($bookingSummary['by_source'] as $source => $count)
                        @php
                            $sourcePct = $statusTotal > 0 ? ((int) $count / $statusTotal) * 100 : 0;
                        @endphp
                        <li>
                            <div class="mb-1.5 flex items-baseline justify-between gap-3">
                                <span class="text-sm font-medium text-gray-700 capitalize">{{ str_replace('_', ' ', $source) }}</span>
                                <span class="text-sm tabular-nums text-gray-900">
                                    <span class="font-semibold">{{ number_format($count) }}</span>
                                    <span class="text-xs text-gray-500">({{ number_format($sourcePct, 1) }}%)</span>
                                </span>
                            </div>
                            <div class="h-3 overflow-hidden rounded-sm bg-gray-100" role="presentation">
                                <div class="h-full rounded-sm bg-indigo-500" @style(['width: '.$sourcePct.'%'])></div>
                            </div>
                            <a href="{{ route('admin.reservations.index', ['source' => $source]) }}" class="mt-1.5 inline-flex text-xs font-medium text-primary hover:text-primary-dark">
                                View all {{ str_replace('_', ' ', $source) }} reservations
                            </a>
                        </li>
                    @empty
                        <li class="py-6 text-sm text-gray-500 text-center">No data available</li>
                    @endforelse
                </ul>
            </section>

            <!-- By Status -->
            <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden lg:col-span-2" aria-labelledby="reservation-status-heading">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h3 id="reservation-status-heading" class="font-semibold text-gray-900">Reservations by Status</h3>
                    </div>
                    <ul class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 p-4 sm:p-6">
                        @foreach(\App\Enums\ReservationStatus::cases() as $status)
                            @php
                                $statusCount = (int) ($bookingSummary['by_status'][$status->value] ?? 0);
                                $statusPct = $statusTotal > 0 ? ($statusCount / $statusTotal) * 100 : 0;
                            @endphp
                            <li class="grid grid-cols-[7rem_minmax(0,1fr)_2.5rem] items-center gap-2">
                                <a href="{{ route('admin.reservations.index', ['status' => $status->value]) }}" class="truncate text-sm text-gray-700 hover:text-primary" title="View all {{ strtolower($status->label()) }} reservations">{{ $status->label() }}</a>
                                <div class="h-3 overflow-hidden rounded-sm bg-gray-100" role="presentation">
                                    <div class="h-full rounded-sm bg-primary" @style(['width: '.$statusPct.'%'])></div>
                                </div>
                                <span class="text-sm font-medium tabular-nums text-gray-900 text-right">{{ $statusCount }}</span>
                            </li>
                        @endforeach
                    </ul>
            </section>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Popular Services (Rooms & Function Halls) -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="font-semibold text-gray-900">Most Reserved Services</h3>
                    <p class="text-xs text-gray-500 mt-1">Rooms & Function Halls</p>
                </div>
                <div class="p-6">
                    @if(empty($popularServices))
                        <p class="text-sm text-gray-500 text-center py-4">No data available for the selected period.</p>
                    @else
                        <ul class="space-y-4">
                            @php
                                $maxCount = max($popularServices);
                            @endphp
                            @foreach($popularServices as $name => $count)
                                <li>
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-sm font-medium text-gray-700">{{ $name }}</span>
                                        <span class="text-sm font-medium text-gray-900">{{ $count }}</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-primary h-2 rounded-full" @style(['width: '.($maxCount > 0 ? ($count / $maxCount) * 100 : 0).'%'])></div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <!-- Package Popularity -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="font-semibold text-gray-900">Package Popularity</h3>
                    <p class="text-xs text-gray-500 mt-1">Total Package Reservations: {{ number_format($packagePopularity['total_package_bookings']) }}</p>
                </div>
                <div class="p-6">
                    @if(empty($packagePopularity['breakdown']))
                        <p class="text-sm text-gray-500 text-center py-4">No package data available for the selected period.</p>
                    @else
                        <ul class="space-y-4">
                            @foreach($packagePopularity['breakdown'] as $package)
                                <li>
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-sm font-medium text-gray-700">{{ $package['name'] }}</span>
                                        <div class="text-right">
                                            <span class="text-sm font-medium text-gray-900">{{ $package['count'] }}</span>
                                            <span class="text-xs text-gray-500 ml-1">({{ $package['share_percentage'] }}%)</span>
                                        </div>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-teal-500 h-2 rounded-full" @style(['width: '.$package['share_percentage'].'%'])></div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        <!-- Payment Summary Breakdown -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="font-semibold text-gray-900">Payment Status Breakdown</h3>
                <p class="mt-1 text-xs text-gray-500">{{ number_format($paymentSummary['total_payments']) }} payment records in the selected payment-date range</p>
            </div>
            <div class="p-6">
                @if($paymentSummary['total_payments'] === 0)
                    <p class="text-sm text-gray-500 text-center py-4">No payment data available for the selected period.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Count</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach(\App\Enums\PaymentStatus::cases() as $status)
                                    @php
                                        $data = $paymentSummary['by_status'][$status->value] ?? ['count' => 0, 'total_amount' => 0];
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <x-badge :status="$status->value" />
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 text-right">{{ number_format($data['count']) }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 text-right">₱{{ number_format($data['total_amount'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

    @else
        <div class="bg-white p-8 rounded-xl shadow-sm border border-gray-200 text-center">
            <x-empty-state
                icon="document-report"
                title="No Data Available"
                description="There are no reservations or payments recorded for the selected date range."
            />
            @if($startDate || $endDate)
                <div class="mt-4">
                    <a href="{{ route('admin.reports.index', ['grouping' => $grouping]) }}" class="text-sm font-medium text-primary hover:text-primary-dark">Clear Date Filters</a>
                </div>
            @endif
        </div>
    @endif

</x-admin-layout>
