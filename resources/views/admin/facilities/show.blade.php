@php
    $statusMeanings = [
        \App\Enums\FacilityStatus::Available->value => 'Operationally open. Booking availability is checked for the selected dates and times.',
        \App\Enums\FacilityStatus::Unavailable->value => 'Closed to new reservations. Existing stays are unaffected.',
        \App\Enums\FacilityStatus::Maintenance->value => 'Temporarily closed for repairs. Staff reopen it when the work is finished.',
    ];

    $currentComponentIds = $facility->packageComponents->pluck('component_facility_id')->all();
    $componentOptions = \App\Models\Facility::query()
        ->where('id', '!=', $facility->id)
        ->where('is_active', true)
        ->orderBy('name')
        ->get();
@endphp

<x-admin-layout>
    <x-slot name="header">{{ $facility->name }}</x-slot>

    <x-admin.page-header
        title="{{ $facility->name }}"
        :breadcrumbs="[
            ['label' => 'Facilities', 'url' => route('admin.facilities.index')],
            ['label' => $facility->name],
        ]"
    >
        <x-slot:actions>
            <x-admin.button variant="secondary" :href="route('admin.facilities.edit', $facility)">Edit facility</x-admin.button>
            <x-admin.button variant="secondary" :href="route('admin.rates.index', ['search' => $facility->name])">Its rates</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if($facility->isRoom())
                <x-admin.panel title="Room cleaning release">
                    <p>{{ $facility->cleaning_required_at ? 'Awaiting cleaning — unavailable for new bookings.' : 'No pending cleaning hold. Reservations and operational status still apply.' }}</p>
                    @if($facility->cleaning_required_at)
                        <form method="POST" action="{{ route('admin.facilities.cleaning-complete', $facility) }}">@csrf @method('PATCH')
                            <x-admin.button type="submit">Mark cleaning complete</x-admin.button>
                        </form>
                    @endif
                </x-admin.panel>
            @endif
            <x-admin.panel title="Space details">
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Name</dt>
                        <dd class="mt-1 text-sm text-text">{{ $facility->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Type</dt>
                        <dd class="mt-1 text-sm text-text">{{ $facility->facilityType->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Public address</dt>
                        <dd class="mt-1 font-mono text-sm text-text-muted">{{ $facility->slug }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Status</dt>
                        <dd class="mt-1"><x-badge :status="$facility->status" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Public listing</dt>
                        <dd class="mt-1 text-sm text-text">{{ $facility->is_active ? 'Listed' : 'Hidden' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Added</dt>
                        <dd class="mt-1"><x-admin.date-time :value="$facility->created_at" format="day" /></dd>
                    </div>

                    @if ($facility->description)
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Description</dt>
                            <dd class="mt-1 max-w-[70ch] text-sm text-text">{{ $facility->description }}</dd>
                        </div>
                    @endif
                </dl>
            </x-admin.panel>

            <x-admin.panel
                title="Capacity"
                description="Standard capacity is what the base price covers; extra guests are charged up to the maximum."
            >
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-lg bg-muted p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Standard Capacity</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums text-text">{{ $facility->capacity ?: 'No limit' }}</p>
                        <p class="mt-1 text-xs text-text-muted">Number of guests included in the base occupancy.</p>
                    </div>
                    <div class="rounded-lg bg-muted p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Maximum Capacity</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums text-text">{{ $facility->effectiveMaxCapacity() ?: 'No limit' }}</p>
                        <p class="mt-1 text-xs text-text-muted">Absolute maximum number of guests allowed.</p>
                    </div>
                </div>
            </x-admin.panel>

            <x-admin.panel
                :title="$isPackage ? 'Amenities & Inclusions' : 'Amenities'"
                :badge="$facility->amenities->count()"
                description="Descriptive features shown to guests. They do not reserve anything."
                x-data="{ editing: false }"
            >
                <x-slot:actions>
                    <button
                        type="button"
                        @click="editing = ! editing"
                        class="rounded border border-border bg-surface px-3 py-1.5 text-xs font-semibold text-text transition-colors duration-150 hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    >
                        <span x-text="editing ? 'Close' : 'Choose amenities'">Choose amenities</span>
                    </button>
                </x-slot:actions>

                @if ($facility->amenities->isNotEmpty())
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($facility->amenities as $amenity)
                            <li class="inline-flex items-center gap-1 rounded-full bg-primary-light px-3 py-1 text-sm font-medium text-primary">
                                @if ($amenity->icon)
                                    <span aria-hidden="true">{{ $amenity->icon }}</span>
                                @endif
                                {{ $amenity->name }}
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-text-muted">No amenities assigned yet. Added amenities appear on the public facility page.</p>
                @endif

                <form x-show="editing" x-cloak method="POST" action="{{ route('admin.facilities.amenities', $facility) }}" class="mt-4 border-t border-border pt-4">
                    @csrf
                    <fieldset>
                        <legend class="admin-label">Amenities this space offers</legend>
                        <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                            @foreach ($allAmenities as $amenity)
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 text-sm hover:bg-muted">
                                    <input type="checkbox" name="amenities[]" value="{{ $amenity->id }}"
                                           @checked($facility->amenities->contains($amenity->id))
                                           class="h-4 w-4 rounded border-border text-primary focus:ring-primary">
                                    {{ $amenity->name }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <p class="admin-help">Unticking an amenity removes it from the public page the moment you save.</p>
                    <div class="mt-4 flex justify-end">
                        <x-admin.button type="submit">Save amenities</x-admin.button>
                    </div>
                </form>
            </x-admin.panel>

            <x-admin.panel title="Exclusions / Not included" description="Manage approved exclusions through Edit facility; check them against this space's inclusions before publishing.">
                <p class="text-sm text-text-muted whitespace-pre-line">{{ $facility->exclusions ?: 'No exclusions published yet.' }}</p>
            </x-admin.panel>

            @if ($isPackage)
                <x-admin.panel
                    title="Bookable resource components"
                    :badge="$facility->packageComponents->count()"
                    description="The spaces this package actually consumes when a guest books it. This is different from amenities, which are descriptive."
                    x-data="{ editing: false }"
                >
                    @if ($facility->packageComponents->isNotEmpty())
                        <ul class="divide-y divide-border rounded-lg border border-border">
                            @foreach ($facility->packageComponents as $component)
                                <li class="flex items-center justify-between gap-3 px-4 py-3">
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-medium text-text">{{ $component->componentFacility->name }}</span>
                                        <span class="block truncate text-xs text-text-muted">{{ $component->componentFacility->facilityType->name ?? '' }}</span>
                                    </span>
                                    <x-badge :status="$component->componentFacility->status" />
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-text-muted">No components assigned yet. Without components, this package consumes no inventory when it is booked.</p>
                    @endif

                    <x-slot:actions>
                        <button
                            type="button"
                            @click="editing = ! editing"
                            class="rounded border border-border bg-surface px-3 py-1.5 text-xs font-semibold text-text transition-colors duration-150 hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                        >
                            <span x-text="editing ? 'Close' : 'Choose components'">Choose components</span>
                        </button>
                    </x-slot:actions>

                    <form x-show="editing" x-cloak method="POST" action="{{ route('admin.facilities.components', $facility) }}" class="mt-4 border-t border-border pt-4">
                        @csrf
                        <fieldset>
                            <legend class="admin-label">Spaces included in this package</legend>
                            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @foreach ($componentOptions as $option)
                                    <label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 text-sm hover:bg-muted">
                                        <input type="checkbox" name="components[]" value="{{ $option->id }}"
                                               @checked(in_array($option->id, $currentComponentIds, true))
                                               class="h-4 w-4 rounded border-border text-primary focus:ring-primary">
                                        <span class="min-w-0">
                                            {{ $option->name }}
                                            <span class="text-xs text-text-muted">({{ $option->facilityType->name ?? 'Unknown type' }})</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <p class="admin-help">Ticked spaces are held for the whole package reservation and cannot be double booked.</p>
                        <div class="mt-4 flex justify-end">
                            <x-admin.button type="submit">Save components</x-admin.button>
                        </div>
                    </form>
                </x-admin.panel>
            @endif

            @if ($facility->rates->isNotEmpty())
                <x-admin.panel
                    title="Current rates"
                    :badge="$facility->rates->count()"
                    :padding="false"
                    description="Every price guests can be charged for this space."
                >
                    <x-slot:actions>
                        <a href="{{ route('admin.rates.index', ['search' => $facility->name]) }}" class="rounded text-sm font-semibold text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                            Open rate list
                        </a>
                    </x-slot:actions>

                    <x-admin.table plain>
                        <x-admin.table-head>
                            <x-admin.table-cell :isHeader="true">Rate</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true">Type</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true" numeric>Amount</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true">Active</x-admin.table-cell>
                        </x-admin.table-head>
                        <x-admin.table-body>
                            @foreach ($facility->rates as $rate)
                                <x-admin.table-row>
                                    <x-admin.table-cell>
                                        <a href="{{ route('admin.rates.show', $rate) }}" class="rounded font-medium text-text hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                            {{ $rate->name }}
                                        </a>
                                    </x-admin.table-cell>
                                    <x-admin.table-cell nowrap>{{ $rate->type->label() }}</x-admin.table-cell>
                                    <x-admin.table-cell numeric><x-amount :value="$rate->amount" /></x-admin.table-cell>
                                    <x-admin.table-cell nowrap>{{ $rate->is_active ? 'Active' : 'Inactive' }}</x-admin.table-cell>
                                </x-admin.table-row>
                            @endforeach
                        </x-admin.table-body>
                    </x-admin.table>
                </x-admin.panel>
            @endif

            @if ($facility->galleryImages->isNotEmpty())
                <x-admin.panel
                    title="Guest photos"
                    :badge="$facility->galleryImages->count()"
                    description="Shown to guests in this order. Captions are edited from the edit screen."
                >
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        @foreach ($facility->galleryImages as $image)
                            <figure class="overflow-hidden rounded-xl border border-border">
                                <img src="{{ $image->url() }}" alt="{{ $image->caption ?: $facility->name }}" class="aspect-video w-full object-cover">
                                @if ($image->caption)
                                    <figcaption class="bg-muted/60 px-3 py-2 text-xs text-text">{{ $image->caption }}</figcaption>
                                @endif
                            </figure>
                        @endforeach
                    </div>
                </x-admin.panel>
            @endif
        </div>

        <div class="space-y-6 lg:sticky lg:top-24">
            <x-admin.panel title="Status" description="Status controls whether new guests can book this space.">
                <form method="POST" action="{{ route('admin.facilities.status', $facility) }}">
                    @csrf
                    @method('PATCH')

                    <label for="status" class="admin-label">Set status to</label>
                    <select name="status" id="status" class="admin-input mt-1" required aria-required="true">
                        @foreach (\App\Enums\FacilityStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected($status === $facility->status)>{{ $status->label() }}</option>
                        @endforeach
                    </select>

                    <dl class="mt-3 space-y-2 text-xs text-text-muted">
                        @foreach ($statusMeanings as $value => $meaning)
                            <div>
                                <dt class="font-semibold text-text">{{ \App\Enums\FacilityStatus::from($value)->label() }}</dt>
                                <dd>{{ $meaning }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <x-admin.button type="submit" variant="secondary" class="mt-4 w-full">Update status</x-admin.button>
                </form>
            </x-admin.panel>

            <x-admin.panel title="Actions" description="Archiving keeps every past reservation and rate; it only removes the space from the admin lists and the public site.">
                <div class="space-y-2">
                    <x-admin.button variant="secondary" :href="route('admin.facilities.edit', $facility)" class="w-full">Edit facility</x-admin.button>

                    <x-admin.confirm-action
                        :action="route('admin.facilities.destroy', $facility)"
                        method="DELETE"
                        label="Archive facility"
                        title="Archive {{ $facility->name }}?"
                        :consequence="'Guest pages, the booking flow, and this list stop offering '.$facility->name.'. Past reservations, rates, and photos are kept.'"
                        confirm="Archive facility"
                    />
                </div>
            </x-admin.panel>
        </div>
    </div>
</x-admin-layout>
