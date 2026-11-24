<x-customer-layout>
    @include('pages.partials.page-intro', [
        'eyebrow' => 'About us',
        'title' => config('resort.name'),
        'description' => 'A nature farm and resort in Ilagan — grown from a family hangout into a place to stay and celebrate.',
    ])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-20">
        <section id="reviews" class="scroll-mt-24" aria-labelledby="about-reviews-heading">
            <x-section-heading id="about-reviews-heading" title="Guest reviews" align="left" class="mb-8 !max-w-none">
                Notes from guests after their stay, celebration, or day at the pools.
            </x-section-heading>
            @if($reviews->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($reviews as $review)
                        <x-review-quote :review="$review" />
                    @endforeach
                </div>
                <div class="mt-8">
                    <a href="{{ route('reviews') }}" class="text-sm font-semibold text-primary hover:underline">Read all reviews &rarr;</a>
                </div>
            @else
                <p class="text-text-muted">Customer reviews will appear here after guests share their experience from their account.</p>
            @endif
        </section>

        <section id="faqs" class="scroll-mt-24" aria-labelledby="about-faqs-heading">
            <x-section-heading id="about-faqs-heading" title="Frequently asked questions" align="left" class="mb-8 !max-w-none">
                Helpful answers about the resort, reservations, pools, and policies.
            </x-section-heading>
            @if($faqs->isNotEmpty())
                @php($groupedFaqs = $faqs->groupBy('faq_category_id'))
                <div class="space-y-10">
                    @foreach($groupedFaqs as $categoryFaqs)
                        @php($category = $categoryFaqs->first()->category)
                        <section aria-labelledby="about-faq-category-{{ $category->id }}">
                            <h3 id="about-faq-category-{{ $category->id }}" class="mb-3 font-heading text-2xl font-semibold text-text border-b border-border pb-2">{{ $category->name }}</h3>
                            <div class="space-y-3" x-data="{ active: {{ (int) $categoryFaqs->first()->id }} }">
                                @foreach($categoryFaqs as $faq)
                                    <x-faq-accordion-item :faq="$faq" answer-id="about-faq-answer-{{ $faq->id }}" />
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
                <div class="mt-8">
                    <a href="{{ route('faq.index') }}" class="text-sm font-semibold text-primary hover:underline">Browse all FAQs &rarr;</a>
                </div>
            @else
                <p class="text-text-muted">FAQs will appear here as the resort publishes answers for guests.</p>
            @endif
        </section>

        <section class="grid lg:grid-cols-2 gap-12 items-center">
            <div class="relative">
                <img
                    src="{{ asset('images/hero-villa-mercedes.jpg') }}"
                    alt="Villa Mercedes Nature Farm &amp; Resort grounds in Ilagan"
                    loading="lazy"
                    decoding="async"
                    class="w-full rounded-2xl object-cover aspect-[4/3] shadow-none"
                >
                <div class="absolute bottom-0 right-6 bg-primary/90 px-4 py-3 text-white">
                    <p class="font-semibold">Heartful hospitality</p>
                    <p class="text-xs text-white/70">Nature farm &amp; resort</p>
                </div>
            </div>
            <div>
                <p class="font-script text-3xl md:text-4xl text-primary leading-snug">
                    A peaceful nature farm stay in Ilagan, blending comfort and grounds for lasting memories.
                </p>
                <div class="mt-8 grid sm:grid-cols-2 gap-4">
                    <div class="border-t border-border pt-5">
                        <h2 class="font-bold text-text">Mission</h2>
                        <p class="mt-2 text-sm text-text-muted">{{ config('resort.mission') }}</p>
                    </div>
                    <div class="border-t border-border pt-5">
                        <h2 class="font-bold text-text">Vision</h2>
                        <p class="mt-2 text-sm text-text-muted">{{ config('resort.vision') }}</p>
                    </div>
                </div>
                <dl class="mt-10 grid grid-cols-2 sm:grid-cols-4 gap-6">
                    @foreach(config('resort.stats') as $stat)
                        <div>
                            <dt class="font-heading text-3xl md:text-4xl font-semibold text-primary">{{ $stat['value'] }}</dt>
                            <dd class="mt-1 text-xs uppercase tracking-wide text-text-muted">{{ $stat['label'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>

        <section>
            <x-section-heading title="C.A.R.E. values" align="left" class="mb-8 !max-w-none">
                These are the values we apply in how we host guests and run the resort.
            </x-section-heading>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach(config('resort.values') as $value)
                    <div class="border-t border-border pt-5">
                        <p class="font-heading text-4xl font-semibold text-primary">{{ $value['letter'] }}</p>
                        <h3 class="mt-2 font-bold text-text">{{ $value['title'] }}</h3>
                        <p class="mt-2 text-sm text-text-muted">{{ $value['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="border-y border-border bg-transparent py-8 md:py-10">
            <h2 class="font-heading text-3xl md:text-4xl font-semibold text-primary mb-4">How we grew</h2>
            <p class="text-text-muted max-w-3xl">
                Villa Mercedes started as a family place to spend weekends and holidays. Pools, a hotel, cottages, and a function hall followed — and the property became a resort so others could enjoy it too. Today we welcome overnight guests and event clients.
            </p>
        </section>
    </div>
</x-customer-layout>
