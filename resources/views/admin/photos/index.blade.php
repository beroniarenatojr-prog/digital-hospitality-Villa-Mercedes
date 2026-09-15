<x-admin-layout>
    <x-slot name="header">
        Photos — {{ $collection->label() }}
    </x-slot>

    <div class="mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <p class="text-sm text-gray-500 max-w-2xl">
            These photos appear on the public {{ $collection->label() }} page. Upload JPEG, PNG, or WEBP files (max 5MB each).
            You can keep up to {{ $collection->maxCount() }} photos here.
        </p>
        <div class="inline-flex rounded-md border border-gray-200 bg-white p-1">
            @foreach(\App\Enums\PagePhotoCollection::cases() as $tab)
                <a href="{{ route('admin.photos.index', ['collection' => $tab->value]) }}"
                   class="px-3 py-1.5 text-xs font-semibold uppercase tracking-widest rounded {{ $collection === $tab ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-50' }}">
                    {{ $tab->label() }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-1">Upload photos</h2>
                <p class="text-sm text-gray-500 mb-4">
                    {{ $photos->count() }} of {{ $collection->maxCount() }} used.
                </p>

                @if($photos->count() < $collection->maxCount())
                    <form method="POST" action="{{ route('admin.photos.store') }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <input type="hidden" name="collection" value="{{ $collection->value }}">

                        <div>
                            <label for="images" class="block text-sm font-medium text-gray-700">Photos</label>
                            <input type="file" id="images" name="images[]" multiple accept="image/jpeg,image/png,image/jpg,image/webp" required
                                   class="mt-1 block w-full text-sm text-gray-500
                                          file:mr-4 file:py-2 file:px-4
                                          file:rounded-md file:border-0
                                          file:text-sm file:font-semibold
                                          file:bg-primary-light file:text-primary
                                          hover:file:bg-primary-light/80">
                        </div>

                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700">Title (optional)</label>
                            <input type="text" id="title" name="title" value="{{ old('title') }}" maxlength="120"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                                   placeholder="Pool by day">
                            <p class="mt-1 text-xs text-gray-500">Used when you upload a single photo. Edit titles after a batch upload.</p>
                        </div>

                        <div>
                            <label for="caption" class="block text-sm font-medium text-gray-700">Caption (optional)</label>
                            <input type="text" id="caption" name="caption" value="{{ old('caption') }}" maxlength="255"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                                   placeholder="Nature-framed swimming for all ages.">
                        </div>

                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-dark transition">
                            Upload
                        </button>
                    </form>
                @else
                    <p class="text-sm text-gray-500">This page is full. Delete a photo before adding another.</p>
                @endif
            </div>
        </div>

        <div class="lg:col-span-2 space-y-4">
            @forelse($photos as $photo)
                <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-4 sm:p-5">
                    <div class="flex flex-col sm:flex-row gap-4">
                        <img src="{{ $photo->url() }}" alt="{{ $photo->displayTitle() }}" class="w-full sm:w-40 h-32 object-cover rounded-md shrink-0 bg-gray-100">
                        <form method="POST" action="{{ route('admin.photos.update', $photo) }}" class="flex-1 grid sm:grid-cols-2 gap-3">
                            @csrf
                            @method('PATCH')
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Title</label>
                                <input type="text" name="title" value="{{ old('title', $photo->title) }}" maxlength="120"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Caption</label>
                                <input type="text" name="caption" value="{{ old('caption', $photo->caption) }}" maxlength="255"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Order</label>
                                <input type="number" name="sort_order" value="{{ old('sort_order', $photo->sort_order) }}" min="0"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                            </div>
                            <div class="flex items-end gap-2">
                                <button type="submit" class="inline-flex items-center px-3 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-dark transition">
                                    Save
                                </button>
                                <button type="submit" form="delete-photo-{{ $photo->id }}"
                                        class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50"
                                        onclick="return confirm('Delete this photo?')">
                                    Delete
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <form id="delete-photo-{{ $photo->id }}" method="POST" action="{{ route('admin.photos.destroy', $photo) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @empty
                <div class="bg-white shadow-sm rounded-lg border border-dashed border-gray-300 p-10 text-center">
                    <p class="text-sm font-medium text-gray-900">No photos yet</p>
                    <p class="mt-1 text-sm text-gray-500">Upload photos so guests see the real {{ strtolower($collection->label()) }} instead of placeholders.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-admin-layout>
