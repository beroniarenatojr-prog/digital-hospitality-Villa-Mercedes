<x-admin-layout>
    <x-slot name="header">
        FAQs
    </x-slot>

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4" x-data="{ showCreateModal: false }">
        <h2 class="text-lg font-semibold text-gray-900">Manage FAQs</h2>
        
        <button @click="showCreateModal = true" class="inline-flex items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-dark transition shadow-sm">
            <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add FAQ
        </button>

        <!-- Create Modal -->
        <div x-show="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showCreateModal = false"></div>
                <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full p-6 z-50">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">New FAQ</h3>
                        <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-500">
                            <span class="sr-only">Close</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('admin.faqs.store') }}">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <label for="faq_category_id" class="block text-sm font-medium text-gray-700">Category <span class="text-danger">*</span></label>
                                <select name="faq_category_id" id="faq_category_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                                    <option value="">Select a category</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="question" class="block text-sm font-medium text-gray-700">Question <span class="text-danger">*</span></label>
                                <input type="text" name="question" id="question" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                            </div>
                            <div>
                                <label for="answer" class="block text-sm font-medium text-gray-700">Answer <span class="text-danger">*</span></label>
                                <textarea name="answer" id="answer" rows="4" required
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm"></textarea>
                            </div>
                            <div>
                                <label for="sort_order" class="block text-sm font-medium text-gray-700">Sort Order</label>
                                <input type="number" name="sort_order" id="sort_order" value="0" min="0"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                                <p class="mt-1 text-xs text-gray-500">Lower numbers appear first within the category.</p>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end gap-3">
                            <button type="button" @click="showCreateModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-md text-sm font-semibold hover:bg-primary-dark">Create FAQ</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- FAQs List Grouped by Category -->
    <div class="space-y-6">
        @php
            $groupedFaqs = $faqs->groupBy('faq_category_id');
        @endphp

        @forelse($categories as $category)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-sm font-bold text-gray-900">{{ $category->name }}</h3>
                    @if(!$category->is_active)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-800">Category Inactive</span>
                    @endif
                </div>
                
                @if(isset($groupedFaqs[$category->id]) && $groupedFaqs[$category->id]->count() > 0)
                    <ul role="list" class="divide-y divide-gray-200">
                        @foreach($groupedFaqs[$category->id] as $faq)
                            <li class="p-4 sm:p-6 hover:bg-gray-50 transition" x-data="{ showEditModal: false }">
                                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3">
                                            <h4 class="text-sm font-semibold text-gray-900">{{ $faq->question }}</h4>
                                            @if($faq->is_active)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Inactive</span>
                                            @endif
                                        </div>
                                        <p class="mt-2 text-sm text-gray-600 line-clamp-2">{{ $faq->answer }}</p>
                                        <p class="mt-2 text-xs text-gray-400">Sort Order: {{ $faq->sort_order }}</p>
                                    </div>
                                    
                                    <div class="flex items-center gap-3 shrink-0 mt-2 sm:mt-0">
                                        <!-- Toggle Status Form -->
                                        <form method="POST" action="{{ route('admin.faqs.status', $faq) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-xs font-medium {{ $faq->is_active ? 'text-amber-600 hover:text-amber-800' : 'text-success hover:text-green-800' }}">
                                                {{ $faq->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                        
                                        <span class="text-gray-300">|</span>
                                        
                                        <button @click="showEditModal = true" class="text-xs font-medium text-primary hover:text-primary-dark">
                                            Edit
                                        </button>
                                    </div>
                                </div>

                                <!-- Edit Modal -->
                                <div x-show="showEditModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                                    <div class="flex min-h-full items-center justify-center p-4">
                                        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showEditModal = false"></div>
                                        <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full p-6 z-50">
                                            <div class="flex justify-between items-center mb-4">
                                                <h3 class="text-lg font-medium text-gray-900">Edit FAQ</h3>
                                                <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-500">
                                                    <span class="sr-only">Close</span>
                                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                </button>
                                            </div>
                                            <form method="POST" action="{{ route('admin.faqs.update', $faq) }}">
                                                @csrf
                                                @method('PUT')
                                                <div class="space-y-4">
                                                    <div>
                                                        <label for="faq_category_id_{{ $faq->id }}" class="block text-sm font-medium text-gray-700">Category <span class="text-danger">*</span></label>
                                                        <select name="faq_category_id" id="faq_category_id_{{ $faq->id }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                                                            @foreach($categories as $cat)
                                                                <option value="{{ $cat->id }}" {{ $faq->faq_category_id == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label for="question_{{ $faq->id }}" class="block text-sm font-medium text-gray-700">Question <span class="text-danger">*</span></label>
                                                        <input type="text" name="question" id="question_{{ $faq->id }}" value="{{ $faq->question }}" required
                                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                                                    </div>
                                                    <div>
                                                        <label for="answer_{{ $faq->id }}" class="block text-sm font-medium text-gray-700">Answer <span class="text-danger">*</span></label>
                                                        <textarea name="answer" id="answer_{{ $faq->id }}" rows="4" required
                                                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">{{ $faq->answer }}</textarea>
                                                    </div>
                                                    <div>
                                                        <label for="sort_order_{{ $faq->id }}" class="block text-sm font-medium text-gray-700">Sort Order</label>
                                                        <input type="number" name="sort_order" id="sort_order_{{ $faq->id }}" value="{{ $faq->sort_order }}" min="0"
                                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                                                    </div>
                                                </div>
                                                <div class="mt-6 flex justify-end gap-3">
                                                    <button type="button" @click="showEditModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</button>
                                                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-md text-sm font-semibold hover:bg-primary-dark">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="p-6 text-center text-sm text-gray-500">
                        No FAQs in this category.
                    </div>
                @endif
            </div>
        @empty
            <div class="p-8 text-center bg-white rounded-lg border border-gray-200">
                <p class="text-sm text-gray-500">No FAQ categories found. Please create a category first.</p>
            </div>
        @endforelse
    </div>
</x-admin-layout>
