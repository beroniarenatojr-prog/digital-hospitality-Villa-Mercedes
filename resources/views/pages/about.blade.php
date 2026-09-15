<x-customer-layout>
    @include('pages.partials.page-intro', [
        'eyebrow' => 'About us',
        'title' => config('resort.name'),
        'description' => 'A nature farm and resort in Ilagan — grown from a family hangout into a place to stay and celebrate.',
    ])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-20">
        <section class="grid lg:grid-cols-2 gap-12 items-center">
            <div class="relative">
                <img
                    src="{{ asset('images/hero-villa-mercedes.jpg') }}"
                    alt="Villa Mercedes Nature Farm &amp; Resort grounds in Ilagan"
                    loading="lazy"
                    decoding="async"
                    class="w-full rounded-3xl object-cover aspect-[4/3] shadow-lg"
                >
                <div class="absolute -bottom-5 right-6 bg-surface rounded-2xl shadow-md px-5 py-4 flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-primary text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                    </span>
                    <div>
                        <p class="font-semibold text-text">Heartful hospitality</p>
                        <p class="text-xs text-text-muted">Nature farm &amp; resort</p>
                    </div>
                </div>
            </div>
            <div>
                <p class="font-script text-3xl md:text-4xl text-primary leading-snug">
                    A peaceful nature farm stay in Ilagan, blending comfort and grounds for lasting memories.
                </p>
                <div class="mt-8 grid sm:grid-cols-2 gap-4">
                    <div class="bg-surface rounded-2xl shadow-sm border-l-4 border-primary p-6">
                        <h2 class="font-bold text-text">Mission</h2>
                        <p class="mt-2 text-sm text-text-muted">{{ config('resort.mission') }}</p>
                    </div>
                    <div class="bg-surface rounded-2xl shadow-sm border-l-4 border-primary p-6">
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
                    <div class="bg-surface border border-border rounded-2xl p-6 border-l-4 border-l-primary">
                        <p class="font-heading text-4xl font-semibold text-primary">{{ $value['letter'] }}</p>
                        <h3 class="mt-2 font-bold text-text">{{ $value['title'] }}</h3>
                        <p class="mt-2 text-sm text-text-muted">{{ $value['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="bg-background rounded-3xl p-8 md:p-10 border border-border">
            <h2 class="font-heading text-3xl md:text-4xl font-semibold text-primary mb-4">How we grew</h2>
            <p class="text-text-muted max-w-3xl">
                Villa Mercedes started as a family place to spend weekends and holidays. Pools, a hotel, cottages, and a function hall followed — and the property became a resort so others could enjoy it too. Today we welcome overnight guests and event clients.
            </p>
        </section>
    </div>
</x-customer-layout>
