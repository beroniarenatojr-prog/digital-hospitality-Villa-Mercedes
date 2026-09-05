@props(['eyebrow' => null, 'title', 'description' => null])

<div class="bg-surface border-b border-border">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
        @if($eyebrow)
            <p class="text-sm font-semibold uppercase tracking-widest text-accent mb-3">{{ $eyebrow }}</p>
        @endif
        <h1 class="text-3xl md:text-4xl font-display font-bold text-text">{{ $title }}</h1>
        @if($description)
            <p class="mt-4 text-lg text-text-muted max-w-3xl">
                {{ $description }}
            </p>
        @endif
    </div>
</div>
