@php
    $selectedFacilityIds = collect(old('facility_ids', $batch->exists ? $batch->facilities->pluck('id')->all() : []))
        ->map(fn ($id) => (int) $id)
        ->all();
    $grouped = $facilities->groupBy(fn ($facility) => $facility->facilityType->name);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 space-y-4">
        <h3 class="text-lg font-semibold text-gray-900 border-b pb-2">Cohort</h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="program" class="block text-sm font-medium text-gray-700">Program <span class="text-danger">*</span></label>
                <input type="text" name="program" id="program" list="program-options" required
                       value="{{ old('program', $batch->program) }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                <datalist id="program-options">
                    @foreach($programs as $program)
                        <option value="{{ $program }}"></option>
                    @endforeach
                </datalist>
            </div>
            <div>
                <label for="cohort_code" class="block text-sm font-medium text-gray-700">Cohort code <span class="text-danger">*</span></label>
                <input type="text" name="cohort_code" id="cohort_code" required
                       value="{{ old('cohort_code', $batch->cohort_code) }}" placeholder="HK-2026-01"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700">Start date <span class="text-danger">*</span></label>
                <input type="date" name="start_date" id="start_date" required
                       value="{{ old('start_date', $batch->start_date?->toDateString()) }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
            </div>
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700">End date <span class="text-danger">*</span></label>
                <input type="date" name="end_date" id="end_date" required
                       value="{{ old('end_date', $batch->end_date?->toDateString()) }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="daily_start_time" class="block text-sm font-medium text-gray-700">Session start time</label>
                <input type="time" name="daily_start_time" id="daily_start_time"
                       value="{{ old('daily_start_time', $batch->daily_start_time ? \Illuminate\Support\Str::substr($batch->daily_start_time, 0, 5) : '') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
            </div>
            <div>
                <label for="daily_end_time" class="block text-sm font-medium text-gray-700">Session end time</label>
                <input type="time" name="daily_end_time" id="daily_end_time"
                       value="{{ old('daily_end_time', $batch->daily_end_time ? \Illuminate\Support\Str::substr($batch->daily_end_time, 0, 5) : '') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
            </div>
        </div>
        <p class="text-xs text-gray-500">
            Leave both times blank to hold the resources continuously for the whole date range. With session
            hours set, the rooms are only held during those hours each day and stay sellable in the evening.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="trainee_capacity" class="block text-sm font-medium text-gray-700">Trainee slots <span class="text-danger">*</span></label>
                <input type="number" name="trainee_capacity" id="trainee_capacity" min="0" max="500" required
                       value="{{ old('trainee_capacity', $batch->trainee_capacity ?: 20) }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700">Status <span class="text-danger">*</span></label>
                <select name="status" id="status" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                    @foreach(\App\Enums\TrainingBatchStatus::cases() as $status)
                        <option value="{{ $status->value }}"
                            @selected(old('status', $batch->status?->value ?? \App\Enums\TrainingBatchStatus::Planned->value) === $status->value)>
                            {{ $status->label() }}{{ $status->isBlocking() ? ' (holds resources)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
            <textarea name="notes" id="notes" rows="3"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">{{ old('notes', $batch->notes) }}</textarea>
        </div>
    </div>

    <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 border-b pb-2">Resources used <span class="text-danger">*</span></h3>
        <p class="text-sm text-gray-500 mt-2 mb-4">
            These spaces become unavailable to guests for the batch period, and any package built on them is
            blocked too. Scheduling is refused if a guest already holds one of them.
        </p>

        <div class="space-y-5">
            @foreach($grouped as $typeName => $typeFacilities)
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">{{ $typeName }}</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                        @foreach($typeFacilities as $facility)
                            <label class="flex items-start gap-2 rounded-md border border-gray-200 p-2 hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" name="facility_ids[]" value="{{ $facility->id }}"
                                       @checked(in_array($facility->id, $selectedFacilityIds, true))
                                       class="mt-0.5 rounded border-gray-300 text-primary focus:ring-primary">
                                <span class="text-sm text-gray-900">
                                    {{ $facility->name }}
                                    <span class="block text-xs text-gray-500">Capacity {{ $facility->effectiveMaxCapacity() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.training-batches.index') }}"
           class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
        <button type="submit" class="px-4 py-2 bg-primary text-white rounded-md text-sm font-semibold hover:bg-primary-hover">
            {{ $submitLabel }}
        </button>
    </div>
</form>
