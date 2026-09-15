@props([
    'faq',
    'answerId' => null,
])

@php
    $id = (int) $faq->id;
    $panelId = $answerId ?? 'faq-answer-'.$id;
@endphp

<div {{ $attributes->merge(['class' => 'rounded-2xl bg-surface shadow-sm overflow-hidden']) }}>
    <h3 class="m-0">
        <button
            type="button"
            class="w-full px-5 py-4 text-left flex justify-between items-start gap-4 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-inset"
            @click="active = (active === {{ $id }} ? null : {{ $id }})"
            :aria-expanded="active === {{ $id }}"
            aria-controls="{{ $panelId }}"
        >
            <span class="font-semibold text-text pr-2">{{ $faq->question }}</span>
            <span
                class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full transition-colors duration-300 ease-out"
                :class="active === {{ $id }} ? 'bg-accent text-white' : 'border border-accent/40 text-accent bg-transparent'"
                aria-hidden="true"
            >
                <svg
                    class="h-4 w-4 transition-transform duration-300 ease-out"
                    :class="active === {{ $id }} ? 'rotate-45' : 'rotate-0'"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                </svg>
            </span>
        </button>
    </h3>

    <div
        id="{{ $panelId }}"
        role="region"
        x-show="active === {{ $id }}"
        x-collapse.duration.300ms
        class="overflow-hidden"
    >
        <div class="px-5 pb-5 text-sm text-text-muted whitespace-pre-line leading-relaxed">
            {{ $faq->answer }}
        </div>
    </div>
</div>
