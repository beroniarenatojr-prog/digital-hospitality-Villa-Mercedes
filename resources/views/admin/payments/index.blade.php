<x-admin-layout>
    <x-slot name="header">
        Payment Management
    </x-slot>

    @if(($overdueReviews ?? 0) > 0)
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">{{ $overdueReviews }} deposit proof(s) have waited more than 24 hours. Review these first. Availability remains held until funds are reconciled.</div>
    @endif
    <a href="{{ route('admin.payment-destinations.index') }}" class="mb-4 inline-block font-semibold text-primary">Manage resort QR accounts</a>
    @php
        $hasPaymentFilters = request()->anyFilled(['search', 'status', 'method', 'facility_type', 'facility_id', 'date_from', 'date_until']);
        $hasPaymentSecondaryFilters = request()->anyFilled(['method', 'facility_type', 'facility_id', 'date_from', 'date_until']);
    @endphp
    <div data-live-filter data-live-filter-url="{{ route('admin.payments.index') }}">
    <form method="GET" action="{{ route('admin.payments.index') }}" class="mb-6 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1">
                <label for="search" class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Find a payment</label>
                <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Reservation code, guest name, phone, or email" autocomplete="off" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
            </div>
            <div class="sm:w-44">
                <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Status</label>
                <select name="status" id="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                    <option value="">All statuses</option>
                    @foreach(\App\Enums\PaymentStatus::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <x-admin.live-filter-reset :visible="$hasPaymentFilters" />
        </div>

        <details class="mt-4 border-t border-gray-100 pt-3" @if($hasPaymentSecondaryFilters) open @endif>
            <summary class="cursor-pointer list-none flex items-center justify-between gap-4 text-sm font-semibold text-gray-800 hover:text-primary focus:outline-none">
                <span class="flex items-center gap-2"><svg class="h-4 w-4 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 01.8 1.6L14 13.333V19a1 1 0 01-1.447.894l-3-1.5A1 1 0 019 17.5v-4.167L3.2 4.6A1 1 0 013 4z" /></svg>More filters</span>
                <span class="text-xs font-medium text-gray-500" data-live-filter-hint data-live-filter-hint-fields="method,facility_type,facility_id,date_from,date_until" data-live-filter-hint-idle="Method, space, or date" data-live-filter-hint-active="Filters applied">{{ $hasPaymentSecondaryFilters ? 'Filters applied' : 'Method, space, or date' }}</span>
            </summary>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="method" class="block text-xs font-medium text-gray-700 mb-1">Method</label>
                    <select name="method" id="method" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm"><option value="">All methods</option>@foreach(\App\Enums\PaymentMethod::cases() as $method)<option value="{{ $method->value }}" {{ request('method') === $method->value ? 'selected' : '' }}>{{ $method->label() }}</option>@endforeach</select>
                </div>
                <div>
                    <label for="facility_type" class="block text-xs font-medium text-gray-700 mb-1">Space type</label>
                    <select name="facility_type" id="facility_type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm"><option value="">All types</option>@foreach($facilityTypes as $type)<option value="{{ $type->slug }}" {{ request('facility_type') === $type->slug ? 'selected' : '' }}>{{ $type->name }}</option>@endforeach</select>
                </div>
                <div>
                    <label for="facility_id" class="block text-xs font-medium text-gray-700 mb-1">Space</label>
                    <select name="facility_id" id="facility_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm"><option value="">All spaces</option>@foreach($facilities as $facility)<option value="{{ $facility->id }}" {{ request('facility_id') == $facility->id ? 'selected' : '' }}>{{ $facility->name }}</option>@endforeach</select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label for="date_from" class="block text-xs font-medium text-gray-700 mb-1">From</label><input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm"></div>
                    <div><label for="date_until" class="block text-xs font-medium text-gray-700 mb-1">To</label><input type="date" name="date_until" id="date_until" value="{{ request('date_until') }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm"></div>
                </div>
            </div>
        </details>
    </form>

    <x-admin.live-filter-results>
        @include('admin.payments.partials.results')
    </x-admin.live-filter-results>
    </div>
</x-admin-layout>
