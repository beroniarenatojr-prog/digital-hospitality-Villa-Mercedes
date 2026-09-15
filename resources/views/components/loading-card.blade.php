@props(['count' => 1])

@for($index = 0; $index < $count; $index++)
    <article class="overflow-hidden rounded-3xl border border-border bg-surface shadow-sm" aria-hidden="true">
        <div class="aspect-[16/10] animate-pulse bg-muted"></div>
        <div class="space-y-3 p-6">
            <div class="h-5 w-2/3 animate-pulse rounded bg-muted"></div>
            <div class="h-4 w-full animate-pulse rounded bg-muted"></div>
            <div class="h-4 w-5/6 animate-pulse rounded bg-muted"></div>
            <div class="mt-6 flex items-center justify-between">
                <div class="h-7 w-28 animate-pulse rounded bg-muted"></div>
                <div class="h-10 w-28 animate-pulse rounded-lg bg-muted"></div>
            </div>
        </div>
    </article>
@endfor
