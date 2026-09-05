<x-admin-layout>
    <x-slot name="header">
        Training Applications
    </x-slot>

    <nav class="mb-6 text-sm text-gray-500">
        <a href="{{ route('admin.training-batches.index') }}" class="hover:text-primary">Training</a>
        <span class="mx-2">/</span>
        <span class="text-gray-900">Applications</span>
    </nav>

    <div class="mb-6 bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <form method="GET" action="{{ route('admin.training-applications.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label for="status" class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                <select name="status" id="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                    <option value="">All statuses</option>
                    @foreach(\App\Enums\TrainingApplicationStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2 sm:col-span-2">
                <button type="submit" class="inline-flex justify-center items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-hover transition">
                    Filter
                </button>
                @if(request()->anyFilled(['status', 'batch']))
                    <a href="{{ route('admin.training-applications.index') }}" class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                        Clear
                    </a>
                @endif
            </div>
        </form>
    </div>

    <x-admin.table>
        <x-admin.table-head>
            <x-admin.table-cell :isHeader="true">Applicant</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Batch</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Applied</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Status</x-admin.table-cell>
            <x-admin.table-cell :isHeader="true">Decide</x-admin.table-cell>
        </x-admin.table-head>
        <x-admin.table-body>
            @forelse($applications as $application)
                <x-admin.table-row>
                    <x-admin.table-cell>
                        <span class="font-medium text-gray-900">{{ $application->name }}</span>
                        <span class="block text-xs text-gray-500">{{ $application->email }} &middot; {{ $application->phone }}</span>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <a href="{{ route('admin.training-batches.show', $application->trainingBatch) }}" class="text-sm font-medium text-primary hover:underline">
                            {{ $application->trainingBatch->cohort_code }}
                        </a>
                        <span class="block text-xs text-gray-500">{{ $application->trainingBatch->program }}</span>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <span class="text-sm text-gray-900">{{ $application->created_at->format('M d, Y') }}</span>
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <x-badge :status="$application->status->label()" />
                    </x-admin.table-cell>
                    <x-admin.table-cell>
                        <form method="POST" action="{{ route('admin.training-applications.status', $application) }}" class="flex items-center gap-2">
                            @csrf
                            @method('PATCH')
                            <select name="status" class="rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-xs">
                                @foreach(\App\Enums\TrainingApplicationStatus::cases() as $status)
                                    <option value="{{ $status->value }}" @selected($application->status === $status)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="px-3 py-1.5 bg-primary text-white rounded-md text-xs font-semibold hover:bg-primary-hover">
                                Save
                            </button>
                        </form>
                    </x-admin.table-cell>
                </x-admin.table-row>
            @empty
                <x-admin.table-row>
                    <x-admin.table-cell colspan="5" class="text-center py-8 text-gray-500">
                        No applications found.
                    </x-admin.table-cell>
                </x-admin.table-row>
            @endforelse
        </x-admin.table-body>
    </x-admin.table>

    <div class="mt-6">
        {{ $applications->withQueryString()->links() }}
    </div>
</x-admin-layout>
