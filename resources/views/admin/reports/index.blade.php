<x-admin-layout>
    <x-slot name="header">
        Reporting & Analytics
    </x-slot>

    <!-- Date Range & Grouping Controls -->
    <div class="mb-8 bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <form method="GET" action="{{ route('admin.reports.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
            <!-- Date From -->
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" name="start_date" id="start_date" value="{{ $startDate ? $startDate->format('Y-m-d') : '' }}"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
            </div>

            <!-- Date Until -->
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">Date Until</label>
                <input type="date" name="end_date" id="end_date" value="{{ $endDate ? $endDate->format('Y-m-d') : '' }}"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
            </div>

            <!-- Trends Grouping -->
            <div>
                <label for="grouping" class="block text-sm font-medium text-gray-700 mb-1">Trends Grouping</label>
                <select name="grouping" id="grouping" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                    <option value="month" {{ $grouping === 'month' ? 'selected' : '' }}>Monthly</option>
                    <option value="quarter" {{ $grouping === 'quarter' ? 'selected' : '' }}>Quarterly</option>
                </select>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-3">
                <button type="submit" class="flex-1 inline-flex justify-center items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-primary-dark transition">
                    Apply Filters
                </button>
                @if($startDate || $endDate)
                    <a href="{{ route('admin.reports.index') }}" class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-sm text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                        Clear
                    </a>
                @endif
            </div>
        </form>
        <p class="mt-4 text-xs text-gray-500">
            Note: Booking trends and summaries are based on the reservation creation date. Payment summaries are based on the payment date.
        </p>
    </div>

    @if($bookingSummary['total'] > 0 || $paymentSummary['total_payments'] > 0)
        
        <!-- Summary KPIs -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Bookings -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Bookings</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($bookingSummary['total']) }}</p>
                </div>
            </div>

            <!-- Reservation Value -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"></path></svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Reservation Value</p>
                    <p class="text-2xl font-bold text-gray-900">₱{{ number_format($revenueSummary['reservation_value'], 2) }}</p>
                </div>
            </div>

            <!-- Verified Payment Revenue -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Verified Payment</p>
                    <p class="text-2xl font-bold text-gray-900">₱{{ number_format($revenueSummary['verified_payment_revenue'], 2) }}</p>
                </div>
            </div>

            <!-- Total Payments -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex items-center">
                <div class="p-3 rounded-full bg-indigo-100 text-indigo-600 mr-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Payments</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($paymentSummary['total_payments']) }}</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            <!-- Booking Breakdown -->
            <div class="lg:col-span-1 space-y-8">
                <!-- By Status -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h3 class="font-semibold text-gray-900">Bookings by Status</h3>
                    </div>
                    <ul class="divide-y divide-gray-100 p-4">
                        @foreach(\App\Enums\ReservationStatus::cases() as $status)
                            <li class="py-3 flex justify-between items-center">
                                <span class="text-sm text-gray-700">{{ $status->label() }}</span>
                                <span class="font-medium text-gray-900">{{ $bookingSummary['by_status'][$status->value] ?? 0 }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <!-- By Source -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h3 class="font-semibold text-gray-900">Bookings by Source</h3>
                    </div>
                    <ul class="divide-y divide-gray-100 p-4">
                        @forelse($bookingSummary['by_source'] as $source => $count)
                            <li class="py-3 flex justify-between items-center">
                                <span class="text-sm text-gray-700 capitalize">{{ str_replace('_', ' ', $source) }}</span>
                                <span class="font-medium text-gray-900">{{ $count }}</span>
                            </li>
                        @empty
                            <li class="py-3 text-sm text-gray-500 text-center">No data available</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <!-- Booking Trends -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden h-full">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                        <h3 class="font-semibold text-gray-900">Booking Trends ({{ ucfirst($grouping) }})</h3>
                    </div>
                    <div class="p-6">
                        @if(empty($bookingTrends))
                            <x-empty-state 
                                icon="chart-bar" 
                                title="No Trends Available" 
                                description="There is no historical booking data for the selected period." 
                            />
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead>
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Bookings</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @foreach($bookingTrends as $period => $count)
                                            <tr>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $period }}</td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 text-right">{{ number_format($count) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
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
                                        <div class="bg-primary h-2 rounded-full" style="width: {{ $maxCount > 0 ? ($count / $maxCount) * 100 : 0 }}%"></div>
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
                    <p class="text-xs text-gray-500 mt-1">Total Package Bookings: {{ number_format($packagePopularity['total_package_bookings']) }}</p>
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
                                        <div class="bg-teal-500 h-2 rounded-full" style="width: {{ $package['share_percentage'] }}%"></div>
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
                    <a href="{{ route('admin.reports.index') }}" class="text-sm font-medium text-primary hover:text-primary-dark">Clear Date Filters</a>
                </div>
            @endif
        </div>
    @endif

</x-admin-layout>
