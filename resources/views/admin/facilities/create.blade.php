<x-admin-layout>
    <x-slot name="header">
        Create Facility
    </x-slot>

    <div class="max-w-2xl">
        <div class="bg-white shadow-sm rounded-lg border border-gray-200">
            <form method="POST" action="{{ route('admin.facilities.store') }}" enctype="multipart/form-data" class="p-6 space-y-6">
                @csrf

                <!-- Facility Type -->
                <div>
                    <label for="facility_type_id" class="block text-sm font-medium text-gray-700">Facility Type <span class="text-danger">*</span></label>
                    <select id="facility_type_id" name="facility_type_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                        <option value="">Select a type</option>
                        @foreach($facilityTypes as $type)
                            <option value="{{ $type->id }}" {{ old('facility_type_id') == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                        @endforeach
                    </select>
                    @error('facility_type_id')
                        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                           placeholder="e.g. Deluxe Room, Diamond Hall">
                    @error('name')
                        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea id="description" name="description" rows="4"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                              placeholder="Brief description of this facility">{{ old('description') }}</textarea>
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
                        <input type="number" id="base_capacity" name="base_capacity" value="{{ old('base_capacity') }}" required min="1"
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
                        <input type="number" id="max_capacity" name="max_capacity" value="{{ old('max_capacity') }}" required min="1"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                        <p class="mt-1 text-xs text-gray-500">Absolute maximum number of guests allowed.</p>
                        @error('max_capacity')
                            <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Images -->
                <div>
                    <label for="images" class="block text-sm font-medium text-gray-700">Images</label>
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

                <!-- Active State -->
                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring-primary">
                    <label for="is_active" class="text-sm font-medium text-gray-700">Active (visible and bookable)</label>
                </div>

                <!-- Submit -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                    <a href="{{ route('admin.facilities.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-dark transition">
                        Create Facility
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
