<x-admin-layout>
    <x-slot name="header">
        TESDA Training Batches
    </x-slot>

    <div class="mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <p class="text-sm text-gray-500 max-w-2xl">
            Enterprise-based training runs inside the working resort. A scheduled batch holds its rooms,
            halls and cafe against guest bookings, so cohorts and guests can never be sold the same space.
        </p>
        <div class="flex gap-2">
            <a href="{{ route('admin.training-applications.index') }}"
               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                Applications
                @if($pendingApplications > 0)
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-warning-light text-warning">{{ $pendingApplications }}</span>
                @endif
            </a>
            <a href="{{ route('admin.training-batches.create') }}"
               class="inline-flex items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-hover transition">
                Schedule Batch
            </a>
        </div>
    </div>

    <div class="mb-6 bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <form method="GET" action="{{ route('admin.training-batches.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label for="search" class="block text-xs font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Cohort code or program"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
            </div>
            <div>
                <label for="status" class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                <select name="status" id="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                    <option value="">All statuses</option>
                    @foreach(\App\Enums\TrainingBatchStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 inline-flex justify-center items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-hover transition">
                    Filter
                </button>
                @if(request()->anyFilled(['search', 'status']))
                    <a href="{{ route('admin.training-batches.index') }}" class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                        Clear
                    </a>
                @endif
            </div>
        </form>
    </div>

    <x-admin.table>
        <x-admin.table-head>
            <x-admin.table-cell :isHeader="true">Cohort</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Dates</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Resources held</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Slots</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Status</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Actions</x-admin.table-cell>
        </x-admin.table-head>
        <x-admin.table-body>
            @forelse($batches as $batch)
                <x-admin.table-row>
                    <x-admin.table-cell>
                        <a href="{{ route('admin.training-batches.show', $batch) }}" class="font-mono text-sm font-semibold text-primary hover:underline">
                            {{ $batch->cohort_code }}
                        </a>
                        <span class="block text-xs text-gray-500">{{ $batch->program }}</span>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <span class="text-sm text-gray-900">{{ $batch->dateRangeLabel() }}</span>
                        <span class="block text-xs text-gray-500">{{ $batch->sessionHoursLabel() }}</span>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <span class="text-sm text-gray-900">{{ $batch->facilities->pluck('name')->join(', ') ?: '—' }}</span>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <span class="text-sm text-gray-900">{{ $batch->slotsLabel() }}</span>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <x-badge :status="$batch->status->label()" />
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <a href="{{ route('admin.training-batches.edit', $batch) }}" class="text-primary hover:underline text-sm font-medium">Edit</a>
                    </x-admin.table-cell>
                </x-admin.table-row>
            @empty
                <x-admin.table-row>
                    <x-admin.table-cell colspan="6" class="text-center py-8 text-gray-500">
                        No training batches scheduled yet.
                    </x-admin.table-cell>
                </x-admin.table-row>
            @endforelse
        </x-admin.table-body>
    </x-admin.table>

    <div class="mt-6">
        {{ $batches->withQueryString()->links() }}
    </div>
</x-admin-layout>
