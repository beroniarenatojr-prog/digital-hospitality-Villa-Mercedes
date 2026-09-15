<div class="hidden md:block">
    <x-admin.table>
        <x-admin.table-head>
            <x-admin.table-cell :isHeader="true">Name</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Type</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Status</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Active</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Capacity</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Amenities</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Actions</x-admin.table-cell>
        </x-admin.table-head>
        <x-admin.table-body>
            @forelse($facilities as $facility)
                <x-admin.table-row>
                    <x-admin.table-cell>
                        <a href="{{ route('admin.facilities.show', $facility) }}" class="font-medium text-primary hover:text-primary-dark">
                            {{ $facility->name }}
                        </a>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <span class="text-sm text-gray-600">{{ $facility->facilityType->name }}</span>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <x-badge :status="$facility->status->value" />
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        @if($facility->is_active)
                            <span class="inline-flex items-center text-success text-xs font-medium">
                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                Active
                            </span>
                        @else
                            <span class="inline-flex items-center text-gray-400 text-xs font-medium">
                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                                Inactive
                            </span>
                        @endif
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        {{ $facility->capacity }} / {{ $facility->effectiveMaxCapacity() }}
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        {{ $facility->amenities->count() }}
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <a href="{{ route('admin.facilities.show', $facility) }}" class="text-primary hover:text-primary-dark text-sm font-medium">View</a>
                        <a href="{{ route('admin.facilities.edit', $facility) }}" class="text-gray-600 hover:text-gray-900 text-sm font-medium ml-3">Edit</a>
                    </x-admin.table-cell>
                </x-admin.table-row>
            @empty
                <x-admin.table-row>
                    <x-admin.table-cell colspan="7" class="text-center py-8 text-gray-500">
                        No facilities found matching your criteria.
                    </x-admin.table-cell>
                </x-admin.table-row>
            @endforelse
        </x-admin.table-body>
    </x-admin.table>
</div>

<div class="md:hidden space-y-4">
    @forelse($facilities as $facility)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <a href="{{ route('admin.facilities.show', $facility) }}" class="font-semibold text-primary hover:text-primary-dark">
                    {{ $facility->name }}
                </a>
                <x-badge :status="$facility->status->value" />
            </div>
            <div class="text-sm text-gray-600 space-y-1">
                <p><span class="font-medium">Type:</span> {{ $facility->facilityType->name }}</p>
                <p><span class="font-medium">Capacity:</span> {{ $facility->capacity }} / {{ $facility->effectiveMaxCapacity() }}</p>
                <p><span class="font-medium">Active:</span> {{ $facility->is_active ? 'Yes' : 'No' }}</p>
            </div>
            <div class="mt-3 flex gap-3">
                <a href="{{ route('admin.facilities.show', $facility) }}" class="text-primary hover:text-primary-dark text-sm font-medium">View</a>
                <a href="{{ route('admin.facilities.edit', $facility) }}" class="text-gray-600 hover:text-gray-900 text-sm font-medium">Edit</a>
            </div>
        </div>
    @empty
        <x-empty-state title="No Facilities" description="No facilities found matching your criteria." />
    @endforelse
</div>

<div class="mt-6">
    {{ $facilities->withQueryString()->links() }}
</div>
