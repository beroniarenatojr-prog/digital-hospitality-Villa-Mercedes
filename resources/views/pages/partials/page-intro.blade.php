@props(['eyebrow' => null, 'title', 'description' => null])

<div class="bg-surface border-b border-border" x-reveal>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
        @if($eyebrow)
            <p class="text-sm font-semibold uppercase tracking-widest text-accent mb-3">{{ $eyebrow }}</p>
        @endif
        <h1 class="font-heading text-4xl md:text-5xl font-semibold text-primary">{{ $title }}</h1>
        <span class="mt-4 block h-px w-12 bg-accent" aria-hidden="true"></span>
        @if($description)
            <p class="mt-4 text-lg text-text-muted max-w-3xl">
                {{ $description }}
            </p>
        @endif
    </div>
</div>
