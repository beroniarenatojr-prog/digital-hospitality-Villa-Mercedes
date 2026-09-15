@props([])

<div {{ $attributes->merge(['class' => 'relative', 'data-live-filter-results' => '']) }} aria-live="polite" aria-busy="false">
    <div data-live-filter-loading class="admin-live-filter-loading hidden" role="status">
        <div class="flex w-full max-w-3xl flex-col gap-3 px-4">
            <div class="h-10 animate-pulse rounded-md bg-gray-200/90"></div>
            <div class="h-12 animate-pulse rounded-md bg-gray-100"></div>
            <div class="h-12 animate-pulse rounded-md bg-gray-100"></div>
            <div class="h-12 animate-pulse rounded-md bg-gray-100"></div>
            <p class="mt-1 flex items-center justify-center gap-2 text-sm font-medium text-gray-600">
                <svg class="h-4 w-4 animate-spin text-primary" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Updating results
            </p>
        </div>
    </div>
    <div data-live-filter-content>
        {{ $slot }}
    </div>
</div>
