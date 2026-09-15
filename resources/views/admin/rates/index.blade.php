<x-admin-layout>
    <x-slot name="header">
        Rate Management
    </x-slot>

    @php
        $hasRateFilters = request()->anyFilled(['search', 'facility_type', 'type', 'active']);
    @endphp
    <div data-live-filter data-live-filter-url="{{ route('admin.rates.index') }}">
    <!-- Filters & Actions Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <form method="GET" action="{{ route('admin.rates.index') }}" class="flex flex-wrap items-center gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Rate or facility name..."
                   autocomplete="off"
                   class="min-w-[12rem] flex-1 rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm sm:max-w-xs">
            <select name="facility_type" class="rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                <option value="">All Facility Types</option>
                @foreach($facilityTypes as $type)
                    <option value="{{ $type->slug }}" {{ request('facility_type') === $type->slug ? 'selected' : '' }}>{{ $type->name }}</option>
                @endforeach
            </select>
            <select name="type" class="rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                <option value="">All Rate Types</option>
                @foreach(\App\Enums\RateType::cases() as $rateType)
                    <option value="{{ $rateType->value }}" {{ request('type') === $rateType->value ? 'selected' : '' }}>{{ $rateType->label() }}</option>
                @endforeach
            </select>
            <select name="active" class="rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                <option value="">Active & Inactive</option>
                <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>Active Only</option>
                <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>Inactive Only</option>
            </select>
            <x-admin.live-filter-reset :visible="$hasRateFilters" class="min-h-[38px] text-sm" />
        </form>
        <a href="{{ route('admin.rates.create') }}" class="inline-flex items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-dark transition">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Create Rate
        </a>
    </div>

    <x-admin.live-filter-results>
        @include('admin.rates.partials.results')
    </x-admin.live-filter-results>
    </div>
</x-admin-layout>
