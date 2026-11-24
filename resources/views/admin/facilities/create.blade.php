<x-admin-layout>
    <x-slot name="header">Create Facility</x-slot>

    <x-admin.page-header
        title="Create Facility"
        :breadcrumbs="[
            ['label' => 'Facilities', 'url' => route('admin.facilities.index')],
            ['label' => 'New facility'],
        ]"
    />

    <form method="POST" action="{{ route('admin.facilities.store') }}" enctype="multipart/form-data" class="max-w-3xl space-y-6">
        @csrf

        <x-admin.panel
            title="What this space is"
            description="The name and description appear on the public site and on every reservation for this space."
        >
            <div class="space-y-4">
                <div>
                    <label for="facility_type_id" class="admin-label">Facility type <span class="text-danger" aria-hidden="true">*</span></label>
                    <select id="facility_type_id" name="facility_type_id" required aria-required="true"
                            @error('facility_type_id') aria-invalid="true" @enderror class="admin-input mt-1">
                        <option value="">Select a type</option>
                        @foreach ($facilityTypes as $type)
                            <option value="{{ $type->id }}" @selected((string) old('facility_type_id') === (string) $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                    <p class="admin-help">The type decides how this space is priced and which guests can book it.</p>
                    <x-input-error class="mt-1" :messages="$errors->get('facility_type_id')" />
                </div>

                <div>
                    <label for="name" class="admin-label">Name <span class="text-danger" aria-hidden="true">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required aria-required="true" maxlength="255"
                           placeholder="e.g. Deluxe Room, Diamond Hall"
                           @error('name') aria-invalid="true" @enderror class="admin-input mt-1">
                    <x-input-error class="mt-1" :messages="$errors->get('name')" />
                </div>

                <div>
                    <label for="description" class="admin-label">Description</label>
                    <textarea id="description" name="description" rows="4"
                              placeholder="What the space includes and who it suits"
                              @error('description') aria-invalid="true" @enderror class="admin-input mt-1">{{ old('description') }}</textarea>
                    <x-input-error class="mt-1" :messages="$errors->get('description')" />
                </div>
            </div>
        </x-admin.panel>

        <x-admin.panel title="Exclusions" description="Only publish exclusions checked against the resort-approved catalog.">
            <label for="exclusions" class="admin-label">Not included (one item per line)</label>
            <textarea id="exclusions" name="exclusions" rows="4" maxlength="5000" class="admin-input mt-1">{{ old('exclusions') }}</textarea>
            <x-input-error class="mt-1" :messages="$errors->get('exclusions')" />
        </x-admin.panel>

        <x-admin.panel
            title="Capacity"
            description="Standard capacity is what the base price covers; extra guests are charged up to the maximum."
        >
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="base_capacity" class="admin-label">Standard capacity <span class="text-danger" aria-hidden="true">*</span></label>
                    <input type="number" id="base_capacity" name="base_capacity" value="{{ old('base_capacity') }}" min="1"
                           @error('base_capacity') aria-invalid="true" @enderror class="admin-input mt-1">
                    <p class="admin-help">Number of guests included in the base occupancy.</p>
                    <x-input-error class="mt-1" :messages="$errors->get('base_capacity')" />
                </div>

                <div>
                    <label for="max_capacity" class="admin-label">Maximum capacity <span class="text-danger" aria-hidden="true">*</span></label>
                    <input type="number" id="max_capacity" name="max_capacity" value="{{ old('max_capacity') }}" min="1"
                           @error('max_capacity') aria-invalid="true" @enderror class="admin-input mt-1">
                    <p class="admin-help">Absolute maximum number of guests allowed.</p>
                    <x-input-error class="mt-1" :messages="$errors->get('max_capacity')" />
                </div>
            </div>

            <p class="mt-3 text-sm text-text-muted">Leave both capacity fields blank for cottages; cottage party size is recorded without a fixed limit.</p>
        </x-admin.panel>

        <x-admin.panel
            title="Guest photos"
            description="Photos are the strongest reason a guest picks one space over another. Aim for different angles rather than several shots of the same corner."
        >
            <label for="images" class="admin-label">Upload photos</label>
            <input type="file" id="images" name="images[]" multiple accept="image/jpeg,image/png,image/jpg,image/webp"
                   @error('images') aria-invalid="true" @enderror
                   class="mt-1 block w-full text-sm text-text-muted file:mr-4 file:rounded-lg file:border-0 file:bg-primary-light file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary-light/80">
            <p class="admin-help">
                Upload at least {{ \App\Models\GalleryImage::RECOMMENDED_COUNT }} photos — bed, bathroom, view, and how much space this covers.
                You can add up to {{ \App\Models\GalleryImage::MAX_COUNT }} photos. Captions can be added after creating the facility.
            </p>
            <p class="admin-help">JPEG, PNG, JPG, or WEBP. Max 5MB per photo.</p>
            <x-input-error class="mt-1" :messages="$errors->get('images')" />
            <x-input-error class="mt-1" :messages="$errors->get('images.*')" />
        </x-admin.panel>

        <x-admin.panel title="Public listing" description="Hidden facilities keep their reservations and history; they simply stop being offered to new guests.">
            <div class="flex items-start gap-3">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', true))
                       class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-primary">
                <div>
                    <label for="is_active" class="admin-label">List this facility on the public site</label>
                    <p class="admin-help">Leave it ticked now and use the status controls on the facility page when a space needs maintenance.</p>
                </div>
            </div>
        </x-admin.panel>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <x-admin.button variant="secondary" :href="route('admin.facilities.index')">Cancel</x-admin.button>
            <x-admin.button type="submit">Create Facility</x-admin.button>
        </div>
    </form>
</x-admin-layout>
