<x-customer-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-text mb-2">Frequently Asked Questions</h1>
                <p class="text-text-muted">Find answers to common questions about our facilities, reservations, and policies.</p>
            </div>
            
            <form action="{{ route('faq.index') }}" method="GET" class="w-full md:w-64">
                <div class="flex flex-col">
                    <label for="category_id" class="sr-only">Filter by Category</label>
                    <select name="category_id" id="category_id" onchange="this.form.submit()" class="border-border bg-surface text-text rounded-md shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ ($selectedCategory?->id == $category->id) ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        @if($faqs->isEmpty())
            <x-empty-state 
                title="No FAQs available" 
                description="We couldn't find any frequently asked questions for this category." 
            />
        @else
            @php
                $groupedFaqs = $faqs->groupBy('faq_category_id');
            @endphp
            
            <div class="space-y-8">
                @foreach($groupedFaqs as $categoryId => $categoryFaqs)
                    @php
                        $category = $categoryFaqs->first()->category;
                    @endphp
                    <div>
                        <h2 class="text-xl font-semibold text-primary mb-4 border-b border-border pb-2">{{ $category->name }}</h2>
                        
                        <div class="space-y-4" x-data="{ active: null }">
                            @foreach($categoryFaqs as $faq)
                                <div class="bg-surface rounded-lg shadow-sm border border-border overflow-hidden">
                                    <h3>
                                        <button 
                                            type="button"
                                            class="w-full px-6 py-4 text-left flex justify-between items-center focus:outline-none focus:ring-2 focus:ring-primary focus:ring-inset"
                                            @click="active = (active === {{ $faq->id }} ? null : {{ $faq->id }})"
                                            :aria-expanded="active === {{ $faq->id }}"
                                            aria-controls="faq-answer-{{ $faq->id }}"
                                        >
                                            <span class="font-medium text-text">{{ $faq->question }}</span>
                                            <svg 
                                                class="w-5 h-5 text-text-muted transform transition-transform duration-200" 
                                                :class="{ 'rotate-180': active === {{ $faq->id }} }"
                                                fill="none" 
                                                viewBox="0 0 24 24" 
                                                stroke="currentColor"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                    </h3>
                                    
                                    <div 
                                        id="faq-answer-{{ $faq->id }}"
                                        role="region"
                                        x-show="active === {{ $faq->id }}"
                                        x-collapse
                                        style="display: none;"
                                    >
                                        <div class="px-6 pb-4 pt-0 text-text-muted whitespace-pre-line">
                                            {{ $faq->answer }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-12">
            <x-alert type="info">
                Still have questions? Feel free to <a href="{{ route('customer.inquiries.index') }}" class="font-medium underline hover:text-primary-dark">submit an inquiry</a> and our team will get back to you.
            </x-alert>
        </div>
    </div>
</x-customer-layout>
