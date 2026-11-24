@props(['open' => false])

<section
    {{ $attributes->class(['min-w-0']) }}
    x-data="{ expanded: @js((bool) $open), canCollapse: false }"
    x-id="['content-heading', 'content-body']"
    x-init="canCollapse = 'onbeforematch' in HTMLElement.prototype; if (!canCollapse) expanded = true"
>
    <div class="flex flex-wrap items-center justify-between gap-3 py-3">
        <h3 :id="$id('content-heading')" class="text-sm font-semibold text-text">{{ $heading }}</h3>
        <button
            type="button"
            x-cloak
            x-show="canCollapse"
            @click="expanded = !expanded"
            :aria-expanded="expanded"
            :aria-controls="$id('content-body')"
            :aria-describedby="$id('content-heading')"
            class="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-4 text-sm font-semibold text-primary hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            x-text="expanded ? 'Hide details' : 'Show details'"
        ></button>
    </div>
    <div :id="$id('content-body')" :hidden="expanded ? null : 'until-found'" @beforematch="expanded = true">
        <div class="pb-3">{{ $slot }}</div>
    </div>
</section>
