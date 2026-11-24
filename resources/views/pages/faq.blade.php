<x-customer-layout>
    <div class="bg-background">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="mb-8 max-w-2xl">
                <h1 class="font-heading text-4xl font-semibold text-text tracking-tight">Frequently Asked Questions</h1>
                <p class="mt-3 text-text-muted">Find answers to common questions about our facilities, reservations, and policies.</p>
            </div>

            <form action="{{ route('faq.index') }}" method="GET" class="mb-10">
                <fieldset>
                    <legend class="sr-only">Filter by category</legend>
                    <div class="flex flex-wrap gap-2">
                        <a
                            href="{{ route('faq.index') }}"
                            @class([
                                'inline-flex items-center justify-center min-h-[40px] rounded-full px-5 py-2 text-sm font-semibold transition',
                                'bg-primary text-white shadow-sm' => ! $selectedCategory,
                                'bg-surface text-text-muted border border-border hover:border-primary/40 hover:text-primary' => $selectedCategory,
                            ])
                        >All</a>
                        @foreach($categories as $category)
                            <a
                                href="{{ route('faq.index', ['category_id' => $category->id]) }}"
                                @class([
                                    'inline-flex items-center justify-center min-h-[40px] rounded-full px-5 py-2 text-sm font-semibold transition',
                                    'bg-primary text-white shadow-sm' => $selectedCategory?->id === $category->id,
                                    'bg-surface text-text-muted border border-border hover:border-primary/40 hover:text-primary' => $selectedCategory?->id !== $category->id,
                                ])
                            >{{ $category->name }}</a>
                        @endforeach
                    </div>
                </fieldset>
            </form>

            @if($faqs->isEmpty())
                <x-empty-state
                    title="No FAQs available"
                    description="We couldn't find any frequently asked questions for this category."
                />
            @else
                @php
                    $groupedFaqs = $faqs->groupBy('faq_category_id');
                @endphp

                <div class="space-y-12">
                    @foreach($groupedFaqs as $categoryId => $categoryFaqs)
                        @php
                            $category = $categoryFaqs->first()->category;
                            $firstFaqId = (int) $categoryFaqs->first()->id;
                        @endphp
                        <section aria-labelledby="faq-category-{{ $category->id }}">
                            <h2 id="faq-category-{{ $category->id }}" class="mb-5 font-heading text-2xl font-semibold text-text border-b border-border pb-2">
                                {{ $category->name }}
                            </h2>

                            <div class="space-y-3" x-data="{ active: {{ $firstFaqId }} }">
                                @foreach($categoryFaqs as $faq)
                                    <x-faq-accordion-item :faq="$faq" />
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
            @endif

            <div class="mt-12">
                <x-alert type="info">
                    Still have questions? Feel free to <a href="{{ config('resort.messenger_url') ?: config('resort.facebook_url') }}" target="_blank" rel="noopener noreferrer" class="font-medium underline hover:text-primary-dark">message us on Facebook</a>. Opens in a new tab; replies arrive there.
                </x-alert>
            </div>
        </div>
    </div>
</x-customer-layout>
