<x-admin-layout>
    <x-slot name="header">
        {{ $batch->cohort_code }}
    </x-slot>

    <nav class="mb-6 text-sm text-gray-500">
        <a href="{{ route('admin.training-batches.index') }}" class="hover:text-primary">Training</a>
        <span class="mx-2">/</span>
        <span class="text-gray-900">{{ $batch->cohort_code }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <div class="flex flex-wrap items-start justify-between gap-3 mb-4 border-b pb-3">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">{{ $batch->program }}</h2>
                        <p class="text-sm text-gray-500 mt-1">{{ $batch->dateRangeLabel() }} &middot; {{ $batch->sessionHoursLabel() }}</p>
                    </div>
                    <x-badge :status="$batch->status->label()" />
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Slots</dt>
                        <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $batch->slotsLabel() }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Holds resources</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $batch->status->isBlocking() ? 'Yes — guests cannot book these spaces' : 'No — resources released' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Resources used</dt>
                        <dd class="mt-1 flex flex-wrap gap-2">
                            @forelse($batch->facilities as $facility)
                                <a href="{{ route('admin.facilities.show', $facility) }}"
                                   class="inline-flex items-center px-2.5 py-1 rounded-md bg-info-light text-info text-xs font-medium hover:opacity-80">
                                    {{ $facility->name }}
                                    <span class="ml-1 opacity-70">{{ $facility->facilityType->name }}</span>
                                </a>
                            @empty
                                <span class="text-sm text-gray-500">None assigned</span>
                            @endforelse
                        </dd>
                    </div>
                    @if($batch->notes)
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Notes</dt>
                            <dd class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded-md">{{ $batch->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            @if($conflicts !== [])
                <x-alert type="error">
                    <p class="font-semibold mb-1">This batch currently overlaps guest or cohort claims:</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($conflicts as $conflict)
                            <li>{{ $conflict }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif

            <!-- Applications -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Applications</h3>

                @forelse($batch->applications->sortByDesc('created_at') as $application)
                    <div class="border-b border-gray-100 last:border-b-0 py-4 flex flex-col sm:flex-row sm:items-start gap-4">
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-gray-900">{{ $application->name }}</p>
                            <p class="text-xs text-gray-500">{{ $application->email }} &middot; {{ $application->phone }}</p>
                            @if($application->address)
                                <p class="text-xs text-gray-500">{{ $application->address }}</p>
                            @endif
                            @if($application->message)
                                <p class="mt-2 text-sm text-gray-700 bg-gray-50 rounded-md p-2">{{ $application->message }}</p>
                            @endif
                            <p class="mt-1 text-xs text-gray-400">Applied {{ $application->created_at->format('M d, Y h:i A') }}</p>
                        </div>
                        <div class="sm:w-72 flex-shrink-0 space-y-2">
                            <x-badge :status="$application->status->label()" />
                            <form method="POST" action="{{ route('admin.training-applications.status', $application) }}" class="space-y-2">
                                @csrf
                                @method('PATCH')
                                <select name="status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                                    @foreach(\App\Enums\TrainingApplicationStatus::cases() as $status)
                                        <option value="{{ $status->value }}" @selected($application->status === $status)>{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="staff_notes" value="{{ $application->staff_notes }}" placeholder="Internal note"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                                <button type="submit" class="w-full px-3 py-2 bg-primary text-white rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-primary-hover">
                                    Update
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No applications yet. The public training page lists this batch while it is Planned or Ongoing.</p>
                @endforelse
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 space-y-3">
                <h3 class="text-lg font-semibold text-gray-900 mb-2 border-b pb-2">Actions</h3>

                <a href="{{ route('admin.training-batches.edit', $batch) }}"
                   class="block w-full text-center px-4 py-2 border border-primary text-primary rounded-md text-sm font-semibold hover:bg-primary hover:text-white transition">
                    Edit batch
                </a>

                <form method="POST" action="{{ route('admin.training-batches.status', $batch) }}" class="space-y-2 pt-2">
                    @csrf
                    @method('PATCH')
                    <label for="batch_status" class="block text-sm font-medium text-gray-700">Change status</label>
                    <select name="status" id="batch_status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                        @foreach(\App\Enums\TrainingBatchStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected($batch->status === $status)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500">Completing or cancelling a batch releases its resources for guest bookings.</p>
                    <button type="submit" class="w-full px-4 py-2 bg-primary text-white rounded-md text-sm font-semibold hover:bg-primary-hover">
                        Update status
                    </button>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-2">See it on one calendar</h3>
                <p class="text-sm text-gray-500 mb-3">
                    Training and guest revenue share the same timeline on the Decision Board.
                </p>
                <a href="{{ route('admin.decision-board', ['from' => $batch->start_date->toDateString(), 'to' => $batch->end_date->toDateString()]) }}"
                   class="text-sm font-medium text-primary hover:underline">
                    Open this period &rarr;
                </a>
            </div>
        </div>
    </div>
</x-admin-layout>
