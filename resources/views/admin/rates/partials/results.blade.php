@php
    $hasFilters = request()->anyFilled(['search', 'facility_type', 'type', 'active']);
@endphp

@if ($rates->isEmpty())
    <div class="rounded-xl border border-border bg-surface">
        <x-admin.empty
            icon="rates"
            title="{{ $hasFilters ? 'No rates match these filters' : 'No rates yet' }}"
            description="{{ $hasFilters ? 'Clear a filter or search by a different rate or facility name. Inactive rates are hidden when the status filter is set to active only.' : 'A facility can be viewed but not booked until it has a base rate. Add the base rate first, then seasonal or extra-person charges.' }}"
        >
            <x-slot:action>
                @unless ($hasFilters)
                    <x-admin.button :href="route('admin.rates.create')">Add a rate</x-admin.button>
                @endunless
            </x-slot:action>
        </x-admin.empty>
    </div>
@else
    <div class="hidden lg:block">
        <x-admin.table>
            <x-admin.table-head>
                <x-admin.table-cell :isHeader="true">Rate</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Facility</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Type</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true" numeric>Amount</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Charged</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Effective period</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Status</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true"><span class="sr-only">Actions</span></x-admin.table-cell>
            </x-admin.table-head>
            <x-admin.table-body>
                @foreach ($rates as $rate)
                    <x-admin.table-row>
                        <x-admin.table-cell>
                            <a href="{{ route('admin.rates.show', $rate) }}" class="rounded font-semibold text-text hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                {{ $rate->name }}
                            </a>
                        </x-admin.table-cell>

                        <x-admin.table-cell>
                            <a href="{{ route('admin.facilities.show', $rate->facility) }}" class="rounded text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                {{ $rate->facility->name }}
                            </a>
                            <span class="mt-0.5 block text-xs text-text-muted">{{ $rate->facility->facilityType->name ?? '' }}</span>
                        </x-admin.table-cell>

                        <x-admin.table-cell nowrap>
                            {{ $rate->type->label() }}
                        </x-admin.table-cell>

                        <x-admin.table-cell numeric>
                            <x-amount :value="$rate->amount" />
                        </x-admin.table-cell>

                        <x-admin.table-cell nowrap>
                            {{ str_replace('_', ' ', $rate->unit) }}
                        </x-admin.table-cell>

                        <x-admin.table-cell nowrap>
                            @if ($rate->effective_from && $rate->effective_until)
                                <x-admin.date-range :from="$rate->effective_from" :to="$rate->effective_until" :time="false" />
                            @elseif ($rate->effective_from)
                                From <x-admin.date-time :value="$rate->effective_from" format="date" />
                            @else
                                <span class="text-text-muted">Any date</span>
                            @endif
                        </x-admin.table-cell>

                        <x-admin.table-cell nowrap>
                            @if ($rate->is_active)
                                <span class="text-sm text-success">Active</span>
                            @else
                                <span class="text-sm text-text-muted">Inactive</span>
                            @endif
                        </x-admin.table-cell>

                        <x-admin.table-cell nowrap>
                            <a href="{{ route('admin.rates.show', $rate) }}" class="rounded font-semibold text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                Open
                            </a>
                            <a href="{{ route('admin.rates.edit', $rate) }}" class="ml-3 rounded font-semibold text-text-muted hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                Edit
                            </a>
                        </x-admin.table-cell>
                    </x-admin.table-row>
                @endforeach
            </x-admin.table-body>
        </x-admin.table>
    </div>

    <ul class="space-y-3 lg:hidden">
        @foreach ($rates as $rate)
            <li class="rounded-xl border border-border bg-surface p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <a href="{{ route('admin.rates.show', $rate) }}" class="block truncate font-semibold text-text hover:text-primary">
                            {{ $rate->name }}
                        </a>
                        <p class="truncate text-sm text-text-muted">{{ $rate->facility->name }}</p>
                    </div>
                    <span class="shrink-0 text-right">
                        <x-amount :value="$rate->amount" class="font-semibold" />
                        <span class="mt-0.5 block text-xs text-text-muted">{{ str_replace('_', ' ', $rate->unit) }}</span>
                    </span>
                </div>

                <dl class="mt-3 space-y-1.5 text-sm">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-xs text-text-muted">Type</dt>
                        <dd class="text-right text-text">{{ $rate->type->label() }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-xs text-text-muted">Status</dt>
                        <dd class="text-right text-text">{{ $rate->is_active ? 'Active' : 'Inactive' }}</dd>
                    </div>
                </dl>

                <div class="mt-4 flex items-center gap-2 border-t border-border pt-3">
                    <x-admin.button variant="secondary" size="sm" :href="route('admin.rates.show', $rate)" class="flex-1">Open</x-admin.button>
                    <x-admin.button variant="ghost" size="sm" :href="route('admin.rates.edit', $rate)" class="flex-1">Edit</x-admin.button>
                </div>
            </li>
        @endforeach
    </ul>

    @if ($rates->hasPages())
        <div class="mt-6">
            {{ $rates->withQueryString()->links() }}
        </div>
    @endif
@endif
