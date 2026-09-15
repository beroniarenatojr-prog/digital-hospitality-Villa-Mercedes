<div class="hidden md:block">
    <x-admin.table>
        <x-admin.table-head>
            <x-admin.table-cell :isHeader="true">Facility</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Rate Name</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Type</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Amount</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Unit</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Effective Period</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Active</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Actions</x-admin.table-cell>
        </x-admin.table-head>
        <x-admin.table-body>
            @forelse($rates as $rate)
                <x-admin.table-row>
                    <x-admin.table-cell>
                        <a href="{{ route('admin.facilities.show', $rate->facility) }}" class="font-medium text-primary hover:text-primary-dark text-sm">
                            {{ $rate->facility->name }}
                        </a>
                        <span class="block text-xs text-gray-400">{{ $rate->facility->facilityType->name ?? '' }}</span>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <a href="{{ route('admin.rates.show', $rate) }}" class="font-medium text-gray-900 hover:text-primary">
                            {{ $rate->name }}
                        </a>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                            {{ $rate->type === \App\Enums\RateType::Base ? 'bg-blue-100 text-blue-800' : '' }}
                            {{ $rate->type === \App\Enums\RateType::Seasonal ? 'bg-amber-100 text-amber-800' : '' }}
                            {{ $rate->type === \App\Enums\RateType::AdditionalCharge ? 'bg-purple-100 text-purple-800' : '' }}
                            {{ $rate->type === \App\Enums\RateType::ExtraPerson ? 'bg-teal-100 text-teal-800' : '' }}
                        ">
                            {{ $rate->type->label() }}
                        </span>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <span class="font-semibold">₱{{ number_format($rate->amount, 2) }}</span>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <span class="text-sm text-gray-600">{{ str_replace('_', ' ', $rate->unit) }}</span>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        @if($rate->effective_from && $rate->effective_until)
                            <span class="text-sm text-gray-600">
                                {{ $rate->effective_from->format('M d') }} – {{ $rate->effective_until->format('M d, Y') }}
                            </span>
                        @elseif($rate->effective_from)
                            <span class="text-sm text-gray-600">From {{ $rate->effective_from->format('M d, Y') }}</span>
                        @else
                            <span class="text-xs text-gray-400">—</span>
                        @endif
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        @if($rate->is_active)
                            <span class="inline-flex items-center text-success text-xs font-medium">
                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                Active
                            </span>
                        @else
                            <span class="inline-flex items-center text-gray-400 text-xs font-medium">Inactive</span>
                        @endif
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <a href="{{ route('admin.rates.show', $rate) }}" class="text-primary hover:text-primary-dark text-sm font-medium">View</a>
                        <a href="{{ route('admin.rates.edit', $rate) }}" class="text-gray-600 hover:text-gray-900 text-sm font-medium ml-3">Edit</a>
                    </x-admin.table-cell>
                </x-admin.table-row>
            @empty
                <x-admin.table-row>
                    <x-admin.table-cell colspan="8" class="text-center py-8 text-gray-500">
                        No rates found matching your criteria.
                    </x-admin.table-cell>
                </x-admin.table-row>
            @endforelse
        </x-admin.table-body>
    </x-admin.table>
</div>

<div class="md:hidden space-y-4">
    @forelse($rates as $rate)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <a href="{{ route('admin.rates.show', $rate) }}" class="font-semibold text-gray-900 hover:text-primary">
                    {{ $rate->name }}
                </a>
                @if($rate->is_active)
                    <span class="text-success text-xs font-medium">Active</span>
                @else
                    <span class="text-gray-400 text-xs font-medium">Inactive</span>
                @endif
            </div>
            <div class="text-sm text-gray-600 space-y-1">
                <p><span class="font-medium">Facility:</span> {{ $rate->facility->name }}</p>
                <p><span class="font-medium">Type:</span> {{ $rate->type->label() }}</p>
                <p><span class="font-medium">Amount:</span> <span class="font-semibold">₱{{ number_format($rate->amount, 2) }}</span> {{ str_replace('_', ' ', $rate->unit) }}</p>
                @if($rate->effective_from && $rate->effective_until)
                    <p><span class="font-medium">Period:</span> {{ $rate->effective_from->format('M d') }} – {{ $rate->effective_until->format('M d, Y') }}</p>
                @endif
            </div>
            <div class="mt-3 flex gap-3">
                <a href="{{ route('admin.rates.show', $rate) }}" class="text-primary hover:text-primary-dark text-sm font-medium">View</a>
                <a href="{{ route('admin.rates.edit', $rate) }}" class="text-gray-600 hover:text-gray-900 text-sm font-medium">Edit</a>
            </div>
        </div>
    @empty
        <p class="text-center text-gray-500 py-8">No rates found.</p>
    @endforelse
</div>

<div class="mt-6">
    {{ $rates->withQueryString()->links() }}
</div>
