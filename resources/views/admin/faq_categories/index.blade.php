<x-admin-layout>
    <x-slot name="header">FAQ categories</x-slot>

    <x-admin.page-header
        title="FAQ categories"
    >
        <x-slot:actions>
            <x-admin.button variant="secondary" :href="route('admin.faqs.index')">Manage questions</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <section  class="mb-6 overflow-hidden rounded-xl border border-border bg-surface">
        <div class="px-4 py-3 text-sm font-semibold text-text">
            Add a new category
        </div>

        <div class="border-t border-border p-5">
            <p class="mb-4 text-sm text-text-muted">The slug is the address fragment used by the public page; keep it short and lowercase.</p>

            <form method="POST" action="{{ route('admin.faq-categories.store') }}">
                @csrf

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="new_category_name" class="admin-label">Name <span class="text-danger" aria-hidden="true">*</span></label>
                        <input type="text" name="name" id="new_category_name" value="{{ old('name') }}" required aria-required="true"
                               @error('name') aria-invalid="true" @enderror class="admin-input mt-1">
                        <x-input-error class="mt-1" :messages="$errors->get('name')" />
                    </div>

                    <div>
                        <label for="new_category_slug" class="admin-label">Slug <span class="text-danger" aria-hidden="true">*</span></label>
                        <input type="text" name="slug" id="new_category_slug" value="{{ old('slug') }}" required aria-required="true" placeholder="e.g. general-questions"
                               @error('slug') aria-invalid="true" @enderror class="admin-input mt-1">
                        <x-input-error class="mt-1" :messages="$errors->get('slug')" />
                    </div>

                    <div>
                        <label for="new_category_sort_order" class="admin-label">Position</label>
                        <input type="number" name="sort_order" id="new_category_sort_order" value="{{ old('sort_order', 0) }}" min="0" class="admin-input mt-1">
                        <p class="admin-help">Lower numbers appear first.</p>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-end gap-2">
                    <x-admin.button type="submit">Create category</x-admin.button>
                </div>
            </form>
        </div>
    </section>

    @if ($categories->isEmpty())
        <div class="rounded-xl border border-border bg-surface">
            <x-admin.empty
                icon="faqs"
                title="No FAQ categories yet"
                description="Categories are the headings guests read on the help page. Create one to start grouping questions."
            />
        </div>
    @else
        <div class="space-y-4">
            @foreach ($categories as $category)
                <x-admin.panel :padding="false">
                    <div x-data="{ editing: false }">
                        <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-sm font-semibold text-text">{{ $category->name }}</h2>
                                    @if ($category->is_active)
                                        <span class="rounded-sm border border-current px-2 py-0.5 text-xs font-semibold text-success">Published</span>
                                    @else
                                        <span class="rounded-sm border border-current px-2 py-0.5 text-xs font-semibold text-text-muted">Hidden</span>
                                    @endif
                                </div>
                                <p class="mt-1 font-mono text-xs text-text-muted">{{ $category->slug }}</p>
                                <p class="mt-1 text-xs text-text-muted">Position {{ $category->sort_order }}</p>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <form method="POST" action="{{ route('admin.faq-categories.status', $category) }}">
                                    @csrf
                                    @method('PATCH')
                                    <x-admin.button type="submit" :variant="$category->is_active ? 'secondary' : 'primary'" size="sm">
                                        {{ $category->is_active ? 'Hide from guests' : 'Publish' }}
                                    </x-admin.button>
                                </form>

                                <x-admin.button type="button" variant="ghost" size="sm" @click="editing = ! editing">
                                    <span x-text="editing ? 'Close' : 'Edit'">Edit</span>
                                </x-admin.button>
                            </div>
                        </div>

                        <form x-show="editing" x-cloak method="POST" action="{{ route('admin.faq-categories.update', $category) }}" class="border-t border-border p-5">
                            @csrf
                            @method('PUT')

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <label for="name_{{ $category->id }}" class="admin-label">Name</label>
                                    <input type="text" name="name" id="name_{{ $category->id }}" value="{{ $category->name }}" class="admin-input mt-1">
                                </div>

                                <div>
                                    <label for="slug_{{ $category->id }}" class="admin-label">Slug</label>
                                    <input type="text" name="slug" id="slug_{{ $category->id }}" value="{{ $category->slug }}" class="admin-input mt-1">
                                </div>

                                <div>
                                    <label for="sort_order_{{ $category->id }}" class="admin-label">Position</label>
                                    <input type="number" name="sort_order" id="sort_order_{{ $category->id }}" value="{{ $category->sort_order }}" min="0" class="admin-input mt-1">
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                                <x-admin.button type="button" variant="secondary" size="sm" @click="editing = false">Cancel</x-admin.button>
                                <x-admin.button type="submit" size="sm">Save changes</x-admin.button>
                            </div>
                        </form>
                    </div>
                </x-admin.panel>
            @endforeach
        </div>
    @endif
</x-admin-layout>
