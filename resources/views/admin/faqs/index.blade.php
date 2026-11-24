<x-admin-layout>
    <x-slot name="header">FAQs</x-slot>

    <x-admin.page-header
        title="FAQs"
    >
        <x-slot:actions>
            <x-admin.button variant="secondary" :href="route('admin.faq-categories.index')">Manage categories</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    @php
        $groupedFaqs = $faqs->groupBy('faq_category_id');
    @endphp

    <section  class="mb-6 overflow-hidden rounded-xl border border-border bg-surface">
        <div class="px-4 py-3 text-sm font-semibold text-text">
            Add a new FAQ
        </div>

        <div class="border-t border-border p-5">
            <p class="mb-4 text-sm text-text-muted">It appears on the public help page as soon as it is active.</p>
            <form method="POST" action="{{ route('admin.faqs.store') }}">
                @csrf

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="new_faq_category_id" class="admin-label">Category <span class="text-danger" aria-hidden="true">*</span></label>
                        <select name="faq_category_id" id="new_faq_category_id" required aria-required="true"
                                @error('faq_category_id') aria-invalid="true" @enderror class="admin-input mt-1">
                            <option value="">Select a category</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}" @selected((string) old('faq_category_id') === (string) $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-1" :messages="$errors->get('faq_category_id')" />
                    </div>

                    <div>
                        <label for="new_faq_sort_order" class="admin-label">Position in the category</label>
                        <input type="number" name="sort_order" id="new_faq_sort_order" value="{{ old('sort_order', 0) }}" min="0" class="admin-input mt-1">
                        <p class="admin-help">Lower numbers appear first.</p>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="new_faq_question" class="admin-label">Question <span class="text-danger" aria-hidden="true">*</span></label>
                        <input type="text" name="question" id="new_faq_question" value="{{ old('question') }}" required aria-required="true" maxlength="255"
                               placeholder="How do I cancel a booking?"
                               @error('question') aria-invalid="true" @enderror class="admin-input mt-1">
                        <x-input-error class="mt-1" :messages="$errors->get('question')" />
                    </div>

                    <div class="sm:col-span-2">
                        <label for="new_faq_answer" class="admin-label">Answer <span class="text-danger" aria-hidden="true">*</span></label>
                        <textarea name="answer" id="new_faq_answer" rows="4" required aria-required="true"
                                  @error('answer') aria-invalid="true" @enderror class="admin-input mt-1">{{ old('answer') }}</textarea>
                        <x-input-error class="mt-1" :messages="$errors->get('answer')" />
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-end gap-2">
                    <x-admin.button type="submit">Create FAQ</x-admin.button>
                </div>
            </form>
        </div>
    </section>

    @if ($categories->isEmpty())
        <div class="rounded-xl border border-border bg-surface">
            <x-admin.empty
                icon="faqs"
                title="No FAQ categories yet"
                description="Every FAQ belongs to a category, because the public page groups answers under headings. Create the first category to start adding questions."
            >
                <x-slot:action>
                    <x-admin.button :href="route('admin.faq-categories.index')">Create a category</x-admin.button>
                </x-slot:action>
            </x-admin.empty>
        </div>
    @else
        <div class="space-y-6">
            @foreach ($categories as $category)
                @php($categoryFaqs = $groupedFaqs[$category->id] ?? collect())

                <x-admin.panel
                    :title="$category->name"
                    :badge="$categoryFaqs->count()"
                    :padding="false"
                    :description="$category->is_active ? null : 'This category is inactive, so its questions are hidden from the public page.'"
                >
                    @if ($categoryFaqs->isEmpty())
                        <x-admin.empty
                            icon="faqs"
                            title="No questions in this category yet"
                            description="Add the first question with the Add FAQ button above, or move an existing question here."
                        />
                    @else
                        <ul class="divide-y divide-border">
                            @foreach ($categoryFaqs as $faq)
                                <li x-data="{ editing: false }" class="px-5 py-4">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="text-sm font-semibold text-text">{{ $faq->question }}</h3>
                                                @if ($faq->is_active)
                                                    <span class="rounded-sm border border-current px-2 py-0.5 text-xs font-semibold text-success">Published</span>
                                                @else
                                                    <span class="rounded-sm border border-current px-2 py-0.5 text-xs font-semibold text-text-muted">Draft</span>
                                                @endif
                                            </div>
                                            <p class="mt-1 max-w-[70ch] text-sm text-text-muted">{{ $faq->answer }}</p>
                                            <p class="mt-1 text-xs text-text-muted">Position {{ $faq->sort_order }}</p>
                                        </div>

                                        <div class="flex shrink-0 items-center gap-2">
                                            <form method="POST" action="{{ route('admin.faqs.status', $faq) }}">
                                                @csrf
                                                @method('PATCH')
                                                <x-admin.button type="submit" :variant="$faq->is_active ? 'secondary' : 'primary'" size="sm">
                                                    {{ $faq->is_active ? 'Unpublish' : 'Publish' }}
                                                </x-admin.button>
                                            </form>

                                            <x-admin.button type="button" variant="ghost" size="sm" @click="editing = ! editing">
                                                <span x-text="editing ? 'Close' : 'Edit'">Edit</span>
                                            </x-admin.button>
                                        </div>
                                    </div>

                                    <form x-show="editing" x-cloak method="POST" action="{{ route('admin.faqs.update', $faq) }}" class="mt-4 border-t border-border pt-4">
                                        @csrf
                                        @method('PUT')

                                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <div>
                                                <label for="faq_category_id_{{ $faq->id }}" class="admin-label">Category</label>
                                                <select name="faq_category_id" id="faq_category_id_{{ $faq->id }}" class="admin-input mt-1">
                                                    @foreach ($categories as $cat)
                                                        <option value="{{ $cat->id }}" @selected($faq->faq_category_id == $cat->id)>{{ $cat->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <label for="sort_order_{{ $faq->id }}" class="admin-label">Position in the category</label>
                                                <input type="number" name="sort_order" id="sort_order_{{ $faq->id }}" value="{{ $faq->sort_order }}" min="0" class="admin-input mt-1">
                                            </div>

                                            <div class="sm:col-span-2">
                                                <label for="question_{{ $faq->id }}" class="admin-label">Question</label>
                                                <input type="text" name="question" id="question_{{ $faq->id }}" value="{{ $faq->question }}" maxlength="255" class="admin-input mt-1">
                                            </div>

                                            <div class="sm:col-span-2">
                                                <label for="answer_{{ $faq->id }}" class="admin-label">Answer</label>
                                                <textarea name="answer" id="answer_{{ $faq->id }}" rows="4" class="admin-input mt-1">{{ $faq->answer }}</textarea>
                                            </div>
                                        </div>

                                        <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                                            <x-admin.button type="button" variant="secondary" size="sm" @click="editing = false">Cancel</x-admin.button>
                                            <x-admin.button type="submit" size="sm">Save changes</x-admin.button>
                                        </div>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-admin.panel>
            @endforeach
        </div>
    @endif
</x-admin-layout>
