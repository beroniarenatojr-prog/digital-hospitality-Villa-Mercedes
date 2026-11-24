@php
    $hasFilters = request()->anyFilled(['staff_id', 'action_type', 'date_from', 'date_until']);
@endphp

@if ($activities->isEmpty())
    <div class="rounded-xl border border-border bg-surface">
        <x-admin.empty
            icon="activity-log"
            title="{{ $hasFilters ? 'No activity matches these filters' : 'No staff activity recorded yet' }}"
            description="{{ $hasFilters ? 'Try a wider date range, or clear the staff member and action filters.' : 'Sign-ins, approvals, payments, and record changes are logged here as staff make them.' }}"
        />
    </div>
@else
    <div class="hidden lg:block">
        <x-admin.table>
            <x-admin.table-head>
                <x-admin.table-cell :isHeader="true">When</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Staff</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Action</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">What happened</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Record</x-admin.table-cell>
            </x-admin.table-head>
            <x-admin.table-body>
                @foreach ($activities as $activity)
                    <x-admin.table-row>
                        <x-admin.table-cell nowrap>
                            <x-admin.date-time :value="$activity->created_at" />
                        </x-admin.table-cell>

                        <x-admin.table-cell>
                            <span class="font-medium">{{ $activity->actorName() }}</span>
                            @if ($activity->causer_id)
                                <span class="mt-0.5 block text-xs text-text-muted">Staff #{{ $activity->causer_id }}</span>
                            @endif
                        </x-admin.table-cell>

                        <x-admin.table-cell nowrap>
                            <x-badge :status="$activity->actionLabel()" />
                        </x-admin.table-cell>

                        <x-admin.table-cell>
                            <span class="block max-w-xl whitespace-normal">{{ $activity->description }}</span>
                        </x-admin.table-cell>

                        <x-admin.table-cell>
                            @if ($activity->subjectLabel())
                                @if ($activity->subjectUrl())
                                    <a href="{{ $activity->subjectUrl() }}" class="rounded font-semibold text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                        {{ $activity->subjectLabel() }}
                                    </a>
                                @else
                                    <span>{{ $activity->subjectLabel() }}</span>
                                @endif
                            @else
                                <span class="text-text-muted">Not linked to a record</span>
                            @endif
                        </x-admin.table-cell>
                    </x-admin.table-row>
                @endforeach
            </x-admin.table-body>
        </x-admin.table>
    </div>

    <ul class="space-y-3 lg:hidden">
        @foreach ($activities as $activity)
            <li class="rounded-xl border border-border bg-surface p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-medium text-text">{{ $activity->actorName() }}</p>
                        @if ($activity->causer_id)
                            <p class="text-xs text-text-muted">Staff #{{ $activity->causer_id }}</p>
                        @endif
                    </div>
                    <x-badge :status="$activity->actionLabel()" />
                </div>

                <p class="mt-2 text-sm text-text">{{ $activity->description }}</p>
                <p class="mt-2 text-xs text-text-muted"><x-admin.date-time :value="$activity->created_at" /></p>

                @if ($activity->subjectLabel())
                    <div class="mt-3 border-t border-border pt-3">
                        @if ($activity->subjectUrl())
                            <a href="{{ $activity->subjectUrl() }}" class="rounded font-semibold text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                {{ $activity->subjectLabel() }}
                            </a>
                        @else
                            <span class="text-sm text-text">{{ $activity->subjectLabel() }}</span>
                        @endif
                    </div>
                @endif
            </li>
        @endforeach
    </ul>

    @if ($activities->hasPages())
        <div class="mt-6">
            {{ $activities->links() }}
        </div>
    @endif
@endif
