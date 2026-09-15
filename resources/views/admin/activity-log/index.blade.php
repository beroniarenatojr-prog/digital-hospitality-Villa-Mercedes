<x-admin-layout>
    <x-slot name="header">
        Activity Log
    </x-slot>

    @php
        $hasFilters = request()->anyFilled(['staff_id', 'action_type', 'date_from', 'date_until']);
    @endphp

    <div class="mb-4 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-600">
        Staff actions are recorded with the person who performed them, when they happened, and what changed. These entries cannot be edited or deleted.
    </div>

    <div data-live-filter data-live-filter-url="{{ route('admin.activity-log.index') }}">
    <form method="GET" action="{{ route('admin.activity-log.index') }}" class="mb-6 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="staff_id" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">Staff member</label>
                <select name="staff_id" id="staff_id" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                    <option value="">All staff</option>
                    @foreach($staffMembers as $staff)
                        <option value="{{ $staff->id }}" @selected((string) request('staff_id') === (string) $staff->id)>{{ $staff->name }} (#{{ $staff->id }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="action_type" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">Action type</label>
                <select name="action_type" id="action_type" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                    <option value="">All actions</option>
                    @foreach(\App\Enums\ActivityActionType::cases() as $actionType)
                        <option value="{{ $actionType->value }}" @selected(request('action_type') === $actionType->value)>{{ $actionType->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="date_from" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">From</label>
                <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                <div class="min-w-0 flex-1">
                    <label for="date_until" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">To</label>
                    <input type="date" name="date_until" id="date_until" value="{{ request('date_until') }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary focus:ring-primary">
                </div>
                <x-admin.live-filter-reset :visible="$hasFilters" />
            </div>
        </div>
    </form>

    <x-admin.live-filter-results>
        @include('admin.activity-log.partials.results')
    </x-admin.live-filter-results>
    </div>
</x-admin-layout>
