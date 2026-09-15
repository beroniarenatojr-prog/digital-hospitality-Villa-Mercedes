<div class="hidden md:block">
    <x-admin.table>
        <x-admin.table-head>
            <x-admin.table-cell :isHeader="true">When</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Staff</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Action</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Description</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Record</x-admin.table-cell>
        </x-admin.table-head>
        <x-admin.table-body>
            @forelse($activities as $activity)
                <x-admin.table-row>
                    <x-admin.table-cell>
                        <span class="block text-sm text-gray-900">{{ $activity->created_at->format('M d, Y') }}</span>
                        <span class="block text-xs text-gray-500">{{ $activity->created_at->format('g:i A') }}</span>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <span class="font-medium text-gray-900">{{ $activity->actorName() }}</span>
                        @if($activity->causer_id)
                            <span class="block text-xs text-gray-500">Staff #{{ $activity->causer_id }}</span>
                        @endif
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <x-badge :status="$activity->actionLabel()" />
                    </x-admin.table-cell>
                    <x-admin.table-cell class="whitespace-normal">
                        <span class="block max-w-xl text-sm text-gray-900">{{ $activity->description }}</span>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        @if($activity->subjectLabel())
                            @if($activity->subjectUrl())
                                <a href="{{ $activity->subjectUrl() }}" class="text-sm font-medium text-primary hover:text-primary-dark">
                                    {{ $activity->subjectLabel() }}
                                </a>
                            @else
                                <span class="text-sm text-gray-700">{{ $activity->subjectLabel() }}</span>
                            @endif
                        @else
                            <span class="text-sm text-gray-400">—</span>
                        @endif
                    </x-admin.table-cell>
                </x-admin.table-row>
            @empty
                <x-admin.table-row>
                    <x-admin.table-cell colspan="5" class="py-8 text-center text-gray-500">
                        No staff activity found matching your criteria.
                    </x-admin.table-cell>
                </x-admin.table-row>
            @endforelse
        </x-admin.table-body>
    </x-admin.table>
</div>

<div class="space-y-4 md:hidden">
    @forelse($activities as $activity)
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="mb-2 flex items-start justify-between gap-3">
                <div>
                    <p class="font-medium text-gray-900">{{ $activity->actorName() }}</p>
                    @if($activity->causer_id)
                        <p class="text-xs text-gray-500">Staff #{{ $activity->causer_id }}</p>
                    @endif
                </div>
                <x-badge :status="$activity->actionLabel()" />
            </div>
            <p class="text-sm text-gray-900">{{ $activity->description }}</p>
            <p class="mt-2 text-xs text-gray-500">{{ $activity->created_at->format('M d, Y g:i A') }}</p>
            @if($activity->subjectLabel())
                <div class="mt-3 border-t border-gray-100 pt-3">
                    @if($activity->subjectUrl())
                        <a href="{{ $activity->subjectUrl() }}" class="text-sm font-medium text-primary hover:text-primary-dark">
                            {{ $activity->subjectLabel() }}
                        </a>
                    @else
                        <span class="text-sm text-gray-700">{{ $activity->subjectLabel() }}</span>
                    @endif
                </div>
            @endif
        </div>
    @empty
        <p class="py-8 text-center text-gray-500">No staff activity found matching your criteria.</p>
    @endforelse
</div>

<div class="mt-6">
    {{ $activities->links() }}
</div>
