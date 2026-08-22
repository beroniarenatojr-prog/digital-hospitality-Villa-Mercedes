<x-admin-layout>
    <x-slot name="header">
        FAQ Categories
    </x-slot>

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4" x-data="{ showCreateModal: false }">
        <h2 class="text-lg font-semibold text-gray-900">Manage FAQ Categories</h2>
        
        <button @click="showCreateModal = true" class="inline-flex items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-dark transition shadow-sm">
            <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Category
        </button>

        <!-- Create Modal -->
        <div x-show="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showCreateModal = false"></div>
                <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6 z-50">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">New FAQ Category</h3>
                        <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-500">
                            <span class="sr-only">Close</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('admin.faq-categories.store') }}">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="name" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                            </div>
                            <div>
                                <label for="slug" class="block text-sm font-medium text-gray-700">Slug <span class="text-danger">*</span></label>
                                <input type="text" name="slug" id="slug" required placeholder="e.g. general-questions"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                            </div>
                            <div>
                                <label for="sort_order" class="block text-sm font-medium text-gray-700">Sort Order</label>
                                <input type="number" name="sort_order" id="sort_order" value="0" min="0"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                                <p class="mt-1 text-xs text-gray-500">Lower numbers appear first.</p>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end gap-3">
                            <button type="button" @click="showCreateModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-md text-sm font-semibold hover:bg-primary-dark">Create Category</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Categories List -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <ul role="list" class="divide-y divide-gray-200">
            @forelse($categories as $category)
                <li class="p-4 sm:p-6 hover:bg-gray-50 transition" x-data="{ showEditModal: false }">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-3">
                                <h4 class="text-sm font-semibold text-gray-900">{{ $category->name }}</h4>
                                @if($category->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Inactive</span>
                                @endif
                            </div>
                            <p class="mt-1 text-xs text-gray-500 font-mono">{{ $category->slug }}</p>
                            <p class="mt-1 text-xs text-gray-400">Sort Order: {{ $category->sort_order }}</p>
                        </div>
                        
                        <div class="flex items-center gap-3 shrink-0">
                            <!-- Toggle Status Form -->
                            <form method="POST" action="{{ route('admin.faq-categories.status', $category) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="text-xs font-medium {{ $category->is_active ? 'text-amber-600 hover:text-amber-800' : 'text-success hover:text-green-800' }}">
                                    {{ $category->is_active ? 'Deactivate' : 'Activate' }}
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
                            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6 z-50">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-medium text-gray-900">Edit FAQ Category</h3>
                                    <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-500">
                                        <span class="sr-only">Close</span>
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                                <form method="POST" action="{{ route('admin.faq-categories.update', $category) }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="space-y-4">
                                        <div>
                                            <label for="name_{{ $category->id }}" class="block text-sm font-medium text-gray-700">Name <span class="text-danger">*</span></label>
                                            <input type="text" name="name" id="name_{{ $category->id }}" value="{{ $category->name }}" required
                                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                                        </div>
                                        <div>
                                            <label for="slug_{{ $category->id }}" class="block text-sm font-medium text-gray-700">Slug <span class="text-danger">*</span></label>
                                            <input type="text" name="slug" id="slug_{{ $category->id }}" value="{{ $category->slug }}" required
                                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                                        </div>
                                        <div>
                                            <label for="sort_order_{{ $category->id }}" class="block text-sm font-medium text-gray-700">Sort Order</label>
                                            <input type="number" name="sort_order" id="sort_order_{{ $category->id }}" value="{{ $category->sort_order }}" min="0"
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
            @empty
                <li class="p-8 text-center text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                    <p class="mt-4 text-sm">No FAQ categories found.</p>
                </li>
            @endforelse
        </ul>
    </div>
</x-admin-layout>
