<x-admin-layout>
    <x-slot name="header">Edit {{ $facility->name }}</x-slot>

    @php
        $photoCount = $facility->galleryImages->count();
        $remaining = \App\Models\GalleryImage::MAX_COUNT - $photoCount;
        $recommended = \App\Models\GalleryImage::RECOMMENDED_COUNT;
    @endphp

    <x-admin.page-header
        title="Edit {{ $facility->name }}"
        :breadcrumbs="[
            ['label' => 'Facilities', 'url' => route('admin.facilities.index')],
            ['label' => $facility->name, 'url' => route('admin.facilities.show', $facility)],
            ['label' => 'Edit'],
        ]"
    >
        <x-slot:actions>
            <x-admin.button variant="secondary" :href="route('admin.facilities.show', $facility)">Back to facility</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.facilities.update', $facility) }}" enctype="multipart/form-data" class="max-w-4xl space-y-6">
        @csrf
        @method('PUT')

        <x-admin.panel title="What this space is" description="The name and description appear on the public site and on every reservation for this space.">
            <div class="space-y-4">
                <div>
                    <label for="name" class="admin-label">Name <span class="text-danger" aria-hidden="true">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $facility->name) }}" required aria-required="true" maxlength="255"
                           @error('name') aria-invalid="true" @enderror class="admin-input mt-1">
                    <x-input-error class="mt-1" :messages="$errors->get('name')" />
                </div>

                <div>
                    <label for="description" class="admin-label">Description</label>
                    <textarea id="description" name="description" rows="4"
                              @error('description') aria-invalid="true" @enderror class="admin-input mt-1">{{ old('description', $facility->description) }}</textarea>
                    <x-input-error class="mt-1" :messages="$errors->get('description')" />
                </div>
            </div>
        </x-admin.panel>

        <x-admin.panel title="Exclusions" description="Publish only resort-approved exclusions reconciled against this facility's existing inclusions.">
            <label for="exclusions" class="admin-label">Not included (one item per line)</label>
            <textarea id="exclusions" name="exclusions" rows="4" maxlength="5000" class="admin-input mt-1">{{ old('exclusions', $facility->exclusions) }}</textarea>
            <x-input-error class="mt-1" :messages="$errors->get('exclusions')" />
            @if($facility->slug === 'diamond-hall')
                <p class="admin-help">Diamond Hall remains at 250 guests pending a separate resort capacity check.</p>
            @endif
        </x-admin.panel>

        <x-admin.panel
            title="Capacity"
            description="Standard capacity is what the base price covers; extra guests are charged up to the maximum."
        >
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="base_capacity" class="admin-label">
                        Standard capacity
                        @unless ($facility->isCottage())
                            <span class="text-danger" aria-hidden="true">*</span>
                        @endunless
                    </label>
                    <input type="number" id="base_capacity" name="base_capacity" value="{{ old('base_capacity', $facility->capacity) }}"
                           @required(! $facility->isCottage()) min="1"
                           @error('base_capacity') aria-invalid="true" @enderror class="admin-input mt-1">
                    <p class="admin-help">Number of guests included in the base occupancy.</p>
                    <x-input-error class="mt-1" :messages="$errors->get('base_capacity')" />
                </div>

                <div>
                    <label for="max_capacity" class="admin-label">
                        Maximum capacity
                        @unless ($facility->isCottage())
                            <span class="text-danger" aria-hidden="true">*</span>
                        @endunless
                    </label>
                    <input type="number" id="max_capacity" name="max_capacity" value="{{ old('max_capacity', $facility->max_capacity) }}"
                           @required(! $facility->isCottage()) min="1"
                           @error('max_capacity') aria-invalid="true" @enderror class="admin-input mt-1">
                    <p class="admin-help">Absolute maximum number of guests allowed.</p>
                    <x-input-error class="mt-1" :messages="$errors->get('max_capacity')" />
                </div>
            </div>

            @if ($facility->isCottage())
                <p class="mt-3 text-sm text-text-muted">Leave both blank to keep this cottage without a fixed guest-capacity limit.</p>
            @endif
        </x-admin.panel>

        <x-admin.panel
            title="Guest photos"
            :badge="$photoCount.' / '.\App\Models\GalleryImage::MAX_COUNT"
            description="Guests see these on the public facility page, in this order."
        >
            @if ($photoCount < $recommended)
                <div class="mb-4">
                    <x-admin.notice variant="warning" title="{{ $photoCount }} of {{ $recommended }} recommended photos" :dismissible="false">
                        Add {{ $recommended - $photoCount }} more so guests know what they are booking.
                    </x-admin.notice>
                </div>
            @endif

            @if ($photoCount > 0)
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach ($facility->galleryImages as $image)
                        <div class="overflow-hidden rounded-xl border border-border bg-muted/40">
                            <img src="{{ $image->url() }}" class="h-36 w-full object-cover" alt="{{ $image->caption ?: $facility->name }}">

                            <div class="space-y-3 p-3">
                                <div>
                                    <label for="caption-{{ $image->id }}" class="admin-label">What this photo shows</label>
                                    <input type="text" id="caption-{{ $image->id }}" name="captions[{{ $image->id }}]"
                                           value="{{ old('captions.'.$image->id, $image->caption) }}"
                                           maxlength="120"
                                           placeholder="e.g. Bathroom, garden view, hall seating"
                                           class="admin-input mt-1">
                                </div>

                                <x-admin.confirm-action
                                    :action="route('admin.facilities.images.destroy', [$facility, $image])"
                                    method="DELETE"
                                    label="Delete this photo"
                                    title="Delete this photo?"
                                    :consequence="'The photo is removed from '.$facility->name.' for guests as soon as you confirm. This cannot be undone.'"
                                    confirm="Delete photo"
                                    size="sm"
                                />
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-text-muted">No photos uploaded yet. The first photo is the one guests see in search results.</p>
            @endif

            @if ($remaining > 0)
                <div class="mt-4">
                    <label for="images" class="admin-label">Upload more photos</label>
                    <input type="file" id="images" name="images[]" multiple accept="image/jpeg,image/png,image/jpg,image/webp"
                           @error('images') aria-invalid="true" @enderror
                           class="mt-1 block w-full text-sm text-text-muted file:mr-4 file:rounded-lg file:border-0 file:bg-primary-light file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary-light/80">
                    <p class="admin-help">Select up to {{ $remaining }} more (JPEG, PNG, JPG, WEBP). Max 5MB each. Add captions after saving.</p>
                </div>
            @else
                <p class="mt-4 text-sm text-text-muted">This facility already has {{ \App\Models\GalleryImage::MAX_COUNT }} photos. Delete one before adding another.</p>
            @endif

            <x-input-error class="mt-1" :messages="$errors->get('images')" />
            <x-input-error class="mt-1" :messages="$errors->get('images.*')" />
            <x-input-error class="mt-1" :messages="$errors->get('captions.*')" />
        </x-admin.panel>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <x-admin.button variant="secondary" :href="route('admin.facilities.show', $facility)">Cancel</x-admin.button>
            <x-admin.button type="submit">Save changes</x-admin.button>
        </div>
    </form>
</x-admin-layout>
