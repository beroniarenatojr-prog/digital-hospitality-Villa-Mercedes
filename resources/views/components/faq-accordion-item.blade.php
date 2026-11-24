@props([
    'faq',
    'answerId' => null,
])

@php
    $id = (int) $faq->id;
    $panelId = $answerId ?? 'faq-answer-'.$id;
@endphp

<div {{ $attributes->merge(['class' => 'overflow-hidden border-t border-border bg-transparent']) }}>
    <h3 class="m-0">
        <button
            type="button"
            class="flex w-full items-start justify-between gap-4 px-0 py-4 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-inset"
            @click="active = (active === {{ $id }} ? null : {{ $id }})"
            :aria-expanded="active === {{ $id }}"
            aria-controls="{{ $panelId }}"
        >
            <span class="font-semibold text-text pr-2">{{ $faq->question }}</span>
            <span
                class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full transition-colors duration-300 ease-out"
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
        <div class="pb-5 text-sm leading-relaxed text-text-muted whitespace-pre-line">
            {{ $faq->answer }}
        </div>
    </div>
</div>
