@props(['review'])

@php
    /** @var \App\Models\GuestReview $review */
    $rating = max(1, min(5, (int) $review->rating));
@endphp

<blockquote {{ $attributes->merge(['class' => 'flex flex-col border-t border-border bg-surface p-6 text-left shadow-none sm:p-8']) }}>
    <p class="flex gap-0.5 text-accent" aria-label="{{ $rating }} out of 5 stars">
        @for($i = 1; $i <= 5; $i++)
            <svg class="w-4 h-4 {{ $i <= $rating ? 'fill-current' : 'fill-none stroke-current opacity-40' }}" viewBox="0 0 20 20" aria-hidden="true">
                <path d="M10 2.5l2.2 4.46 4.92.72-3.56 3.47.84 4.9L10 13.9 5.6 16.05l.84-4.9L2.88 7.68l4.92-.72L10 2.5z"/>
            </svg>
        @endfor
    </p>
    @if($review->body)<p class="mt-4 font-heading text-lg leading-relaxed text-text">“{{ $review->body }}”</p>@endif
    <footer class="mt-auto pt-6 flex items-center gap-3">
        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-light text-xs font-semibold text-primary">
            {{ $review->initials() }}
        </span>
        <span>
            <span class="block text-sm font-semibold text-text">{{ $review->guestName() }}</span>
            @if($review->stayCaption())
                <span class="block text-xs text-text-muted">{{ $review->stayCaption() }}</span>
            @endif
        </span>
    </footer>
</blockquote>
