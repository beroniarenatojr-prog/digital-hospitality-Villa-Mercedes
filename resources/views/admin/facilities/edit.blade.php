<x-admin-layout>
    <x-slot name="header">
        Edit Facility — {{ $facility->name }}
    </x-slot>

    @php
        $photoCount = $facility->galleryImages->count();
        $remaining = \App\Models\GalleryImage::MAX_COUNT - $photoCount;
        $recommended = \App\Models\GalleryImage::RECOMMENDED_COUNT;
    @endphp

    <div class="max-w-4xl">
        <div class="bg-white shadow-sm rounded-lg border border-gray-200">
            <form method="POST" action="{{ route('admin.facilities.update', $facility) }}" enctype="multipart/form-data" class="p-6 space-y-6">
                @csrf
                @method('PUT')

                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $facility->name) }}" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                    @error('name')
                        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea id="description" name="description" rows="4"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">{{ old('description', $facility->description) }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Capacities -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="base_capacity" class="block text-sm font-medium text-gray-700">
                            Standard Capacity <span class="text-danger">*</span>
                        </label>
                        <input type="number" id="base_capacity" name="base_capacity" value="{{ old('base_capacity', $facility->capacity) }}" @required(! $facility->isCottage()) min="1"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                        <p class="mt-1 text-xs text-gray-500">Number of guests included in the base occupancy.</p>
                        @error('base_capacity')
                            <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="max_capacity" class="block text-sm font-medium text-gray-700">
                            Maximum Capacity <span class="text-danger">*</span>
                        </label>
                        <input type="number" id="max_capacity" name="max_capacity" value="{{ old('max_capacity', $facility->max_capacity) }}" @required(! $facility->isCottage()) min="1"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                        <p class="mt-1 text-xs text-gray-500">Absolute maximum number of guests allowed.</p>
                        @error('max_capacity')
                            <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                @if($facility->isCottage())<p class="-mt-4 text-xs text-gray-500">Leave both blank to keep this cottage without a fixed guest-capacity limit.</p>@endif

                <!-- Images -->
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Guest photos</label>
                        <p class="mt-1 text-sm text-gray-500">
                            Upload at least {{ $recommended }} photos so guests can see different angles — bed, bathroom, view, and how much space this {{ strtolower($facility->facilityType?->name ?? 'facility') }} covers.
                            You can keep up to {{ \App\Models\GalleryImage::MAX_COUNT }} photos ({{ $photoCount }} used).
                        </p>
                    </div>

                    @if($photoCount < $recommended)
                        <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
                            {{ $photoCount }} of {{ $recommended }} recommended photos. Add {{ $recommended - $photoCount }} more so guests know what they are booking.
                        </p>
                    @endif

                    @if($photoCount > 0)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach($facility->galleryImages as $image)
                                <div class="border rounded-md overflow-hidden bg-gray-50">
                                    <div class="relative">
                                        <img src="{{ $image->url() }}" class="w-full h-36 object-cover" alt="{{ $image->caption ?: $facility->name }}">
                                        <button type="button"
                                                onclick="event.preventDefault(); if (confirm('Delete this photo?')) document.getElementById('delete-image-{{ $image->id }}').submit();"
                                                class="absolute top-2 right-2 text-white bg-danger hover:bg-red-700 font-semibold py-1 px-2 rounded text-xs">
                                            Delete
                                        </button>
                                    </div>
                                    <div class="p-3">
                                        <label for="caption-{{ $image->id }}" class="block text-xs font-medium text-gray-700 mb-1">What this photo shows</label>
                                        <input type="text" id="caption-{{ $image->id }}" name="captions[{{ $image->id }}]"
                                               value="{{ old('captions.'.$image->id, $image->caption) }}"
                                               maxlength="120"
                                               placeholder="e.g. Bathroom, garden view, hall seating"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500 italic">No photos uploaded yet.</p>
                    @endif

                    @if($remaining > 0)
                        <div>
                            <label for="images" class="block text-sm font-medium text-gray-700">Upload more photos</label>
                            <input type="file" id="images" name="images[]" multiple accept="image/jpeg,image/png,image/jpg,image/webp"
                                   class="mt-1 block w-full text-sm text-gray-500
                                          file:mr-4 file:py-2 file:px-4
                                          file:rounded-md file:border-0
                                          file:text-sm file:font-semibold
                                          file:bg-primary-light file:text-primary
                                          hover:file:bg-primary-light/80">
                            <p class="mt-1 text-xs text-gray-500">Select up to {{ $remaining }} more (JPEG, PNG, JPG, WEBP). Max 5MB each. Add captions after saving.</p>
                        </div>
                    @else
                        <p class="text-sm text-gray-500">This facility already has {{ \App\Models\GalleryImage::MAX_COUNT }} photos. Delete one before adding another.</p>
                    @endif
                    @error('images')
                        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                    @enderror
                    @error('images.*')
                        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                    @enderror
                    @error('captions.*')
                        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Submit -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                    <a href="{{ route('admin.facilities.show', $facility) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-dark transition">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden Forms for Image Deletion -->
    @foreach($facility->galleryImages as $image)
        <form id="delete-image-{{ $image->id }}" action="{{ route('admin.facilities.images.destroy', [$facility, $image]) }}" method="POST" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
</x-admin-layout>
