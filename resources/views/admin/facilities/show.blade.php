<x-admin-layout>
    <x-slot name="header">
        {{ $facility->name }}
    </x-slot>

    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm text-gray-500">
        <a href="{{ route('admin.facilities.index') }}" class="hover:text-primary">Facilities</a>
        <span class="mx-2">/</span>
        <span class="text-gray-900">{{ $facility->name }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Info Column -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Facility Info Card -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Facility Information</h2>
                    <a href="{{ route('admin.facilities.edit', $facility) }}" class="text-sm font-medium text-primary hover:text-primary-dark">Edit</a>
                </div>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Name</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $facility->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Type</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $facility->facilityType->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Slug</dt>
                        <dd class="mt-1 text-sm text-gray-600 font-mono">{{ $facility->slug }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1"><x-badge :status="$facility->status->value" /></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Active</dt>
                        <dd class="mt-1 text-sm">{{ $facility->is_active ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Created</dt>
                        <dd class="mt-1 text-sm text-gray-600">{{ $facility->created_at->format('M d, Y') }}</dd>
                    </div>
                    @if($facility->description)
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Description</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $facility->description }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <!-- Capacity Card -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Capacity</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-sm font-medium text-gray-500">Standard Capacity</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1">{{ $facility->capacity }}</p>
                        <p class="text-xs text-gray-500 mt-1">Number of guests included in the base occupancy.</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-sm font-medium text-gray-500">Maximum Capacity</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1">{{ $facility->effectiveMaxCapacity() }}</p>
                        <p class="text-xs text-gray-500 mt-1">Absolute maximum number of guests allowed.</p>
                    </div>
                </div>
            </div>

            <!-- Amenities / Inclusions -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6" x-data="{ showAmenityForm: false }">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">
                        {{ $isPackage ? 'Amenities & Inclusions' : 'Amenities' }}
                    </h2>
                    <button @click="showAmenityForm = !showAmenityForm" class="text-sm font-medium text-primary hover:text-primary-dark">
                        <span x-text="showAmenityForm ? 'Cancel' : 'Manage'"></span>
                    </button>
                </div>

                @if($facility->amenities->count() > 0)
                    <div class="flex flex-wrap gap-2 mb-4">
                        @foreach($facility->amenities as $amenity)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-primary-light text-primary">
                                @if($amenity->icon)
                                    <span class="mr-1">{{ $amenity->icon }}</span>
                                @endif
                                {{ $amenity->name }}
                            </span>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500 mb-4">No amenities assigned yet.</p>
                @endif

                <div x-show="showAmenityForm" x-transition style="display: none;">
                    <form method="POST" action="{{ route('admin.facilities.amenities', $facility) }}">
                        @csrf
                        <div class="border-t border-gray-200 pt-4 space-y-3">
                            <p class="text-sm text-gray-600">Select amenities to assign:</p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                @foreach($allAmenities as $amenity)
                                    <label class="flex items-center gap-2 text-sm cursor-pointer p-2 rounded hover:bg-gray-50">
                                        <input type="checkbox" name="amenities[]" value="{{ $amenity->id }}"
                                               {{ $facility->amenities->contains($amenity->id) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-primary focus:ring-primary">
                                        {{ $amenity->name }}
                                    </label>
                                @endforeach
                            </div>
                            <div class="flex justify-end pt-2">
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-dark transition">
                                    Save Amenities
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Package Components (only for packages) -->
            @if($isPackage)
                <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6" x-data="{ showComponentForm: false }">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Bookable Resource Components</h2>
                        <button @click="showComponentForm = !showComponentForm" class="text-sm font-medium text-primary hover:text-primary-dark">
                            <span x-text="showComponentForm ? 'Cancel' : 'Manage'"></span>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mb-4">
                        These are the actual bookable facilities consumed by this package reservation.
                        This is different from amenities/inclusions which are descriptive features.
                    </p>

                    @if($facility->packageComponents->count() > 0)
                        <div class="space-y-2 mb-4">
                            @foreach($facility->packageComponents as $pkgComponent)
                                <div class="flex items-center justify-between bg-gray-50 rounded-lg px-4 py-3">
                                    <div>
                                        <span class="text-sm font-medium text-gray-900">{{ $pkgComponent->componentFacility->name }}</span>
                                        <span class="text-xs text-gray-500 ml-2">{{ $pkgComponent->componentFacility->facilityType->name ?? '' }}</span>
                                    </div>
                                    <x-badge :status="$pkgComponent->componentFacility->status->value" />
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500 mb-4">No bookable resource components assigned yet.</p>
                    @endif

                    <div x-show="showComponentForm" x-transition style="display: none;">
                        <form method="POST" action="{{ route('admin.facilities.components', $facility) }}">
                            @csrf
                            <div class="border-t border-gray-200 pt-4 space-y-3">
                                <p class="text-sm text-gray-600">Select facilities included in this package:</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @php
                                        $currentComponentIds = $facility->packageComponents->pluck('component_facility_id')->toArray();
                                        $allComponentOptions = \App\Models\Facility::where('id', '!=', $facility->id)->where('is_active', true)->orderBy('name')->get();
                                    @endphp
                                    @foreach($allComponentOptions as $option)
                                        <label class="flex items-center gap-2 text-sm cursor-pointer p-2 rounded hover:bg-gray-50">
                                            <input type="checkbox" name="components[]" value="{{ $option->id }}"
                                                   {{ in_array($option->id, $currentComponentIds) ? 'checked' : '' }}
                                                   class="rounded border-gray-300 text-primary focus:ring-primary">
                                            {{ $option->name }}
                                            <span class="text-xs text-gray-400">({{ $option->facilityType->name ?? '' }})</span>
                                        </label>
                                    @endforeach
                                </div>
                                <div class="flex justify-end pt-2">
                                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-dark transition">
                                        Save Components
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            <!-- Current Rates (read-only) -->
            @if($facility->rates->count() > 0)
                <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Current Rates</h2>
                    <x-admin.table>
                        <x-admin.table-head>
                            <x-admin.table-cell :isHeader="true">Name</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true">Type</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true">Amount</x-admin.table-cell>
                            <x-admin.table-cell :isHeader="true">Active</x-admin.table-cell>
                        </x-admin.table-head>
                        <x-admin.table-body>
                            @foreach($facility->rates as $rate)
                                <x-admin.table-row>
                                    <x-admin.table-cell>{{ $rate->name }}</x-admin.table-cell>
                                    <x-admin.table-cell>{{ $rate->type->value }}</x-admin.table-cell>
                                    <x-admin.table-cell>₱{{ number_format($rate->amount, 2) }}</x-admin.table-cell>
                                    <x-admin.table-cell>{{ $rate->is_active ? 'Yes' : 'No' }}</x-admin.table-cell>
                                </x-admin.table-row>
                            @endforeach
                        </x-admin.table-body>
                    </x-admin.table>
                </div>
            @endif

            <!-- Gallery Images (read-only) -->
            @if($facility->galleryImages->count() > 0)
                <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Gallery Images</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        @foreach($facility->galleryImages as $image)
                            <div class="relative aspect-video bg-gray-100 rounded-lg overflow-hidden">
                                <img src="{{ $image->url() }}" alt="{{ $image->caption ?? $facility->name }}" class="object-cover w-full h-full">
                                @if($image->caption)
                                    <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent p-2">
                                        <p class="text-white text-xs">{{ $image->caption }}</p>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar Actions Column -->
        <div class="space-y-6">
            <!-- Status Management -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6" x-data="{ showConfirm: false, selectedStatus: '' }">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Status Management</h2>
                <div class="space-y-2">
                    @foreach(\App\Enums\FacilityStatus::cases() as $status)
                        @if($status !== $facility->status)
                            <button @click="selectedStatus = '{{ $status->value }}'; showConfirm = true"
                                    class="w-full text-left px-3 py-2 rounded-md text-sm font-medium border
                                    {{ $status === \App\Enums\FacilityStatus::Available ? 'border-success text-success hover:bg-success-light' : '' }}
                                    {{ $status === \App\Enums\FacilityStatus::Unavailable ? 'border-danger text-danger hover:bg-danger-light' : '' }}
                                    {{ $status === \App\Enums\FacilityStatus::Maintenance ? 'border-gray-400 text-gray-600 hover:bg-gray-50' : '' }}
                                    transition">
                                Set to {{ $status->label() }}
                            </button>
                        @endif
                    @endforeach
                </div>

                <!-- Confirmation Modal -->
                <div x-show="showConfirm" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                    <div class="flex min-h-full items-center justify-center p-4">
                        <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="showConfirm = false"></div>
                        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6 z-50">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Confirm Status Change</h3>
                            <p class="text-sm text-gray-500 mb-4">
                                Are you sure you want to change the status of <strong>{{ $facility->name }}</strong>?
                                This will affect future availability.
                            </p>
                            <form method="POST" action="{{ route('admin.facilities.status', $facility) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" x-bind:value="selectedStatus">
                                <div class="flex justify-end gap-3">
                                    <button type="button" @click="showConfirm = false" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                        Cancel
                                    </button>
                                    <button type="submit" class="px-4 py-2 bg-primary border border-transparent rounded-md text-sm font-medium text-white hover:bg-primary-dark">
                                        Confirm
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6" x-data="{ showDeleteConfirm: false }">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Actions</h2>
                <div class="space-y-2">
                    <a href="{{ route('admin.facilities.edit', $facility) }}" class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition">
                        Edit Facility
                    </a>
                    <button @click="showDeleteConfirm = true" class="w-full inline-flex items-center justify-center px-4 py-2 border border-danger rounded-md shadow-sm text-sm font-medium text-danger bg-white hover:bg-danger-light transition">
                        Archive Facility
                    </button>
                </div>

                <!-- Delete Confirmation Modal -->
                <div x-show="showDeleteConfirm" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                    <div class="flex min-h-full items-center justify-center p-4">
                        <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="showDeleteConfirm = false"></div>
                        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6 z-50">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Archive Facility</h3>
                            <p class="text-sm text-gray-500 mb-4">
                                This will safely archive <strong>{{ $facility->name }}</strong>.
                                Historical reservations will be preserved. This action can be reversed.
                            </p>
                            <form method="POST" action="{{ route('admin.facilities.destroy', $facility) }}">
                                @csrf
                                @method('DELETE')
                                <div class="flex justify-end gap-3">
                                    <button type="button" @click="showDeleteConfirm = false" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                        Cancel
                                    </button>
                                    <button type="submit" class="px-4 py-2 bg-danger border border-transparent rounded-md text-sm font-medium text-white hover:bg-red-700">
                                        Archive
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>

