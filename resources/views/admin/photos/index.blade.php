<x-admin-layout>
    <x-slot name="header">Photos — {{ $collection->label() }}</x-slot>

    <x-admin.page-header
        title="Photos — {{ $collection->label() }}"
    >
        <x-slot:actions>
            <div class="inline-flex rounded-lg border border-border bg-surface p-1">
                @foreach (\App\Enums\PagePhotoCollection::cases() as $tab)
                    <a
                        href="{{ route('admin.photos.index', ['collection' => $tab->value]) }}"
                        @if ($collection === $tab) aria-current="page" @endif
                        class="rounded-md px-3 py-1.5 text-xs font-semibold transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 {{ $collection === $tab ? 'bg-primary text-white' : 'text-text-muted hover:bg-muted hover:text-text' }}"
                    >
                        {{ $tab->label() }}
                    </a>
                @endforeach
            </div>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 items-start">
        <div class="space-y-4 lg:col-span-2">
            @forelse ($photos as $photo)
                <x-admin.panel :padding="false">
                    <div class="flex flex-col gap-4 p-4 sm:flex-row sm:p-5">
                        <img src="{{ $photo->url() }}" alt="{{ $photo->displayTitle() }}" class="h-32 w-full shrink-0 rounded-lg bg-muted object-cover sm:w-40">

                        <div class="min-w-0 flex-1">
                            <form method="POST" action="{{ route('admin.photos.update', $photo) }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                @csrf
                                @method('PATCH')

                                <div class="sm:col-span-2">
                                    <label for="title-{{ $photo->id }}" class="admin-label">Title</label>
                                    <input type="text" id="title-{{ $photo->id }}" name="title" value="{{ old('title', $photo->title) }}" maxlength="120"
                                           class="admin-input mt-1">
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="caption-{{ $photo->id }}" class="admin-label">Caption</label>
                                    <input type="text" id="caption-{{ $photo->id }}" name="caption" value="{{ old('caption', $photo->caption) }}" maxlength="255"
                                           class="admin-input mt-1">
                                </div>

                                <div>
                                    <label for="sort-order-{{ $photo->id }}" class="admin-label">Position</label>
                                    <input type="number" id="sort-order-{{ $photo->id }}" name="sort_order" value="{{ old('sort_order', $photo->sort_order) }}" min="0"
                                           class="admin-input mt-1">
                                    <p class="admin-help">Lower numbers appear first.</p>
                                </div>

                                <div class="flex items-end">
                                    <x-admin.button type="submit" class="w-full sm:w-auto">Save changes</x-admin.button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="border-t border-border px-4 py-3 sm:px-5">
                        <x-admin.confirm-action
                            :action="route('admin.photos.destroy', $photo)"
                            method="DELETE"
                            label="Delete this photo"
                            title="Delete this photo?"
                            :consequence="$photo->displayTitle().' disappears from the public '.strtolower($collection->label()).' page immediately. This cannot be undone.'"
                            confirm="Delete photo"
                            size="sm"
                        />
                    </div>
                </x-admin.panel>
            @empty
                <div class="rounded-xl border border-border bg-surface">
                    <x-admin.empty
                        icon="photos"
                        title="No photos yet"
                        description="Upload photos so guests see the real {{ strtolower($collection->label()) }} instead of placeholders. Aim for a wide shot, a detail shot, and one that shows scale."
                    />
                </div>
            @endforelse
        </div>

        <div class="space-y-6 sticky top-24 self-start">
            <x-admin.panel
                title="Upload photos"
                :badge="$photos->count().' / '.$collection->maxCount()"
                description="Upload several at once, then title each one from the list."
            >
                @if ($photos->count() < $collection->maxCount())
                    <form method="POST" action="{{ route('admin.photos.store') }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <input type="hidden" name="collection" value="{{ $collection->value }}">

                        <div>
                            <label for="images" class="admin-label">Photos <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="file" id="images" name="images[]" multiple accept="image/jpeg,image/png,image/jpg,image/webp" required aria-required="true"
                                   class="mt-1 block w-full text-sm text-text-muted file:mr-4 file:rounded-lg file:border-0 file:bg-primary-light file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary-light/80">
                            <p class="admin-help">JPEG, PNG, JPG, or WEBP. Max 5MB each. {{ $collection->maxCount() - $photos->count() }} slots left.</p>
                        </div>

                        <div>
                            <label for="title" class="admin-label">Title</label>
                            <input type="text" id="title" name="title" value="{{ old('title') }}" maxlength="120"
                                   placeholder="Pool by day"
                                   class="admin-input mt-1">
                            <p class="admin-help">Used when you upload a single photo. Edit titles after a batch upload.</p>
                        </div>

                        <div>
                            <label for="caption" class="admin-label">Caption</label>
                            <input type="text" id="caption" name="caption" value="{{ old('caption') }}" maxlength="255"
                                   placeholder="Nature-framed swimming for all ages."
                                   class="admin-input mt-1">
                        </div>

                        <x-admin.button type="submit" class="w-full">Upload photos</x-admin.button>
                    </form>
                @else
                    <x-admin.notice variant="warning" title="This page is full" :dismissible="false">
                        {{ $collection->label() }} holds {{ $collection->maxCount() }} photos. Delete one before adding another.
                    </x-admin.notice>
                @endif
            </x-admin.panel>
        </div>
    </div>
</x-admin-layout>
