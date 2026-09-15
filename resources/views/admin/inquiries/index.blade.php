<x-admin-layout>
    <x-slot name="header">
        Inquiry Management
    </x-slot>

    @php
        $hasInquiryFilters = request()->anyFilled(['search', 'status', 'date_from', 'date_until']);
    @endphp
    <div data-live-filter data-live-filter-url="{{ route('admin.inquiries.index') }}">
    <!-- Filters -->
    <div class="mb-6 bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <form method="GET" action="{{ route('admin.inquiries.index') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

            <!-- Search -->
            <div>
                <label for="search" class="block text-xs font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Subject, Message, Name"
                       autocomplete="off"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
            </div>

            <!-- Status -->
            <div>
                <label for="status" class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                <select name="status" id="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                    <option value="">All Statuses</option>
                    @foreach(\App\Enums\InquiryStatus::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Date From -->
            <div>
                <label for="date_from" class="block text-xs font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
            </div>

            <!-- Date Until -->
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                <div class="min-w-0 flex-1">
                    <label for="date_until" class="block text-xs font-medium text-gray-700 mb-1">Date Until</label>
                    <input type="date" name="date_until" id="date_until" value="{{ request('date_until') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                </div>
                <x-admin.live-filter-reset :visible="$hasInquiryFilters" />
            </div>
        </form>
    </div>

    <x-admin.live-filter-results>
        @include('admin.inquiries.partials.results')
    </x-admin.live-filter-results>
    </div>
</x-admin-layout>
