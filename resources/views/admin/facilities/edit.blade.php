<x-admin-layout>
    <x-slot name="header">
        Edit Facility — {{ $facility->name }}
    </x-slot>

    <div class="max-w-2xl">
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
                        <input type="number" id="base_capacity" name="base_capacity" value="{{ old('base_capacity', $facility->capacity) }}" required min="1"
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
                        <input type="number" id="max_capacity" name="max_capacity" value="{{ old('max_capacity', $facility->max_capacity) }}" required min="1"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                        <p class="mt-1 text-xs text-gray-500">Absolute maximum number of guests allowed.</p>
                        @error('max_capacity')
                            <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Images -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Existing Images</label>
                    @if($facility->galleryImages->count() > 0)
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                            @foreach($facility->galleryImages as $image)
                                <div class="relative group border rounded-md overflow-hidden">
                                    <img src="{{ Storage::url($image->image_path) }}" class="w-full h-32 object-cover" alt="Facility Image">
                                    <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                                        <button type="button" 
                                                onclick="event.preventDefault(); document.getElementById('delete-image-{{ $image->id }}').submit();"
                                                class="text-white bg-danger hover:bg-red-700 font-bold py-1 px-2 rounded text-xs">
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500 italic mb-4">No images uploaded yet.</p>
                    @endif

                    <label for="images" class="block text-sm font-medium text-gray-700">Upload New Images</label>
                    <input type="file" id="images" name="images[]" multiple accept="image/*"
                           class="mt-1 block w-full text-sm text-gray-500
                                  file:mr-4 file:py-2 file:px-4
                                  file:rounded-md file:border-0
                                  file:text-sm file:font-semibold
                                  file:bg-primary-light file:text-primary
                                  hover:file:bg-primary-light/80">
                    <p class="mt-1 text-xs text-gray-500">You can select multiple images (JPEG, PNG, JPG, WEBP). Max 5MB per image.</p>
                    @error('images.*')
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
