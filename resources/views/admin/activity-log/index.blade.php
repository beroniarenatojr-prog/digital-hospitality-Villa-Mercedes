<x-admin-layout>
    <x-slot name="header">Staff activity</x-slot>

    @php
        $hasFilters = request()->anyFilled(['staff_id', 'action_type', 'date_from', 'date_until']);
    @endphp

    <x-admin.page-header title="Staff activity" />

    <div class="mb-6">
        <x-admin.notice variant="info" title="This record is read-only" :dismissible="false">
            Entries are written automatically and cannot be edited or deleted from here, including by administrators.
        </x-admin.notice>
    </div>

    <x-admin.live-filter :action="route('admin.activity-log.index')">
        <x-admin.filter-form
            :action="route('admin.activity-log.index')"
            :active="$hasFilters"
            label="Filter activity"
        >
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div>
                    <label for="staff_id" class="admin-label">Staff member</label>
                    <select name="staff_id" id="staff_id" class="admin-input mt-1">
                        <option value="">Any staff member</option>
                        @foreach ($staffMembers as $staff)
                            <option value="{{ $staff->id }}" @selected((string) request('staff_id') === (string) $staff->id)>{{ $staff->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="action_type" class="admin-label">Action type</label>
                    <select name="action_type" id="action_type" class="admin-input mt-1">
                        <option value="">Any action</option>
                        @foreach (\App\Enums\ActivityActionType::cases() as $actionType)
                            <option value="{{ $actionType->value }}" @selected(request('action_type') === $actionType->value)>{{ $actionType->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <x-slot:more>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="date_from" class="admin-label">From</label>
                        <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}" class="admin-input mt-1">
                    </div>
                    <div>
                        <label for="date_until" class="admin-label">Until</label>
                        <input type="date" name="date_until" id="date_until" value="{{ request('date_until') }}" class="admin-input mt-1">
                    </div>
                </div>
            </x-slot:more>
        </x-admin.filter-form>

        <x-admin.live-filter-results>
            @include('admin.activity-log.partials.results')
        </x-admin.live-filter-results>
    </x-admin.live-filter>
</x-admin-layout>
