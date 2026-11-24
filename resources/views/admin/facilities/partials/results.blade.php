@php
    $hasFilters = request()->anyFilled(['search', 'type', 'status', 'active']);
@endphp

@if ($facilities->isEmpty())
    <div class="rounded-xl border border-border bg-surface">
        <x-admin.empty
            icon="facilities"
            title="{{ $hasFilters ? 'No facilities match these filters' : 'No facilities yet' }}"
            description="{{ $hasFilters ? 'Clear a filter or search by a different name. A facility hidden by the public listing filter is still bookable for existing reservations.' : 'Add the resort rooms, cottages, and function halls here. A facility becomes bookable once it has at least one rate.' }}"
        >
            <x-slot:action>
                @unless ($hasFilters)
                    <x-admin.button :href="route('admin.facilities.create')">Add a facility</x-admin.button>
                @endunless
            </x-slot:action>
        </x-admin.empty>
    </div>
@else
    <div class="hidden lg:block">
        <x-admin.table>
            <x-admin.table-head>
                <x-admin.table-cell :isHeader="true">Facility</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Type</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Status</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Public listing</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true" numeric>Capacity</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true" numeric>Amenities</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true"><span class="sr-only">Actions</span></x-admin.table-cell>
            </x-admin.table-head>
            <x-admin.table-body>
                @foreach ($facilities as $facility)
                    <x-admin.table-row>
                        <x-admin.table-cell>
                            <div class="flex min-w-0 items-center gap-3">
                                @if ($facility->galleryImages->first())
                                    <img src="{{ $facility->galleryImages->first()->url() }}" alt="" class="h-12 w-16 shrink-0 rounded-lg object-cover" loading="lazy">
                                @else
                                    <span class="flex h-12 w-16 shrink-0 items-center justify-center rounded-lg bg-secondary-light text-primary" aria-hidden="true">
                                        <x-admin.nav-icon name="facilities" class="h-5 w-5" />
                                    </span>
                                @endif
                                <span class="min-w-0">
                                    <a href="{{ route('admin.facilities.show', $facility) }}" class="rounded font-semibold text-text hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                        {{ $facility->name }}
                                    </a>
                                    <span class="mt-0.5 block font-mono text-xs text-text-muted">{{ $facility->slug }}</span>
                                </span>
                            </div>
                        </x-admin.table-cell>

                        <x-admin.table-cell nowrap>
                            {{ $facility->facilityType->name }}
                        </x-admin.table-cell>

                        <x-admin.table-cell nowrap>
                            <x-badge :status="$facility->status" />
                        </x-admin.table-cell>

                        <x-admin.table-cell nowrap>
                            @if ($facility->is_active)
                                <span class="text-sm text-text">Listed</span>
                            @else
                                <span class="text-sm text-text-muted">Hidden</span>
                            @endif
                        </x-admin.table-cell>

                        <x-admin.table-cell numeric>
                            @if ($facility->isCottage() && ! $facility->effectiveMaxCapacity())
                                <span class="text-text-muted">No limit</span>
                            @else
                                {{ $facility->capacity }} <span class="text-text-muted">/ {{ $facility->effectiveMaxCapacity() }}</span>
                            @endif
                        </x-admin.table-cell>

                        <x-admin.table-cell numeric>
                            {{ $facility->amenities->count() }}
                        </x-admin.table-cell>

                        <x-admin.table-cell nowrap>
                            <a href="{{ route('admin.facilities.show', $facility) }}" class="rounded font-semibold text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                Open
                            </a>
                            <a href="{{ route('admin.facilities.edit', $facility) }}" class="ml-3 rounded font-semibold text-text-muted hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                Edit
                            </a>
                        </x-admin.table-cell>
                    </x-admin.table-row>
                @endforeach
            </x-admin.table-body>
        </x-admin.table>
    </div>

    <ul class="space-y-3 lg:hidden">
        @foreach ($facilities as $facility)
            <li class="rounded-xl border border-border bg-surface p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        @if ($facility->galleryImages->first())
                            <img src="{{ $facility->galleryImages->first()->url() }}" alt="" class="h-12 w-16 shrink-0 rounded-lg object-cover" loading="lazy">
                        @else
                            <span class="flex h-12 w-16 shrink-0 items-center justify-center rounded-lg bg-secondary-light text-primary" aria-hidden="true">
                                <x-admin.nav-icon name="facilities" class="h-5 w-5" />
                            </span>
                        @endif
                        <div class="min-w-0">
                            <a href="{{ route('admin.facilities.show', $facility) }}" class="block truncate font-semibold text-text hover:text-primary">
                                {{ $facility->name }}
                            </a>
                            <p class="truncate text-sm text-text-muted">{{ $facility->facilityType->name }}</p>
                        </div>
                    </div>
                    <x-badge :status="$facility->status" />
                </div>

                <dl class="mt-3 space-y-1.5 text-sm">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-xs text-text-muted">Capacity</dt>
                        <dd class="text-right tabular-nums text-text">
                            @if ($facility->isCottage() && ! $facility->effectiveMaxCapacity())
                                No limit
                            @else
                                {{ $facility->capacity }} / {{ $facility->effectiveMaxCapacity() }}
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-xs text-text-muted">Public listing</dt>
                        <dd class="text-right text-text">{{ $facility->is_active ? 'Listed' : 'Hidden' }}</dd>
                    </div>
                </dl>

                <div class="mt-4 flex items-center gap-2 border-t border-border pt-3">
                    <x-admin.button variant="secondary" size="sm" :href="route('admin.facilities.show', $facility)" class="flex-1">Open</x-admin.button>
                    <x-admin.button variant="ghost" size="sm" :href="route('admin.facilities.edit', $facility)" class="flex-1">Edit</x-admin.button>
                </div>
            </li>
        @endforeach
    </ul>

    @if ($facilities->hasPages())
        <div class="mt-6">
            {{ $facilities->withQueryString()->links() }}
        </div>
    @endif
@endif
