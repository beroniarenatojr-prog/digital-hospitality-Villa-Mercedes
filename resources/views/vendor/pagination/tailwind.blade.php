{{-- Every admin list calls ->links() with no argument, so Laravel resolves it to
     pagination::tailwind and this project-owned copy decides, in one place, how
     "there are more records" looks back here. The framework's structure and
     accessibility contract are kept deliberately intact — nav landmark,
     aria-current on the page you are on, aria-disabled on the direction you
     cannot take — and only the paint and the wording change:

     - the resort palette instead of Tailwind's raw grays,
     - tabular numerals so the count does not shuffle as it changes,
     - the position summary stays visible on a phone, where "which page am I
       on" is hardest to answer and matters most to the front desk,
     - 40px minimum targets, and "Previous"/"Next" spelled out rather than two
       bare chevrons. --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-text-muted tabular-nums">
            Showing
            @if ($paginator->firstItem())
                <span class="font-medium text-text">{{ $paginator->firstItem() }}</span>
                to
                <span class="font-medium text-text">{{ $paginator->lastItem() }}</span>
            @else
                <span class="font-medium text-text">{{ $paginator->count() }}</span>
            @endif
            of
            <span class="font-medium text-text">{{ $paginator->total() }}</span>
            {{ \Illuminate\Support\Str::plural('record', $paginator->total()) }}
        </p>

        <div class="flex items-center justify-between gap-1.5 sm:justify-end">
            {{-- Phone: a numbered strip would either wrap or shrink past a thumb
                 target, so the two directions carry the job and the summary
                 above states the position. --}}
            <div class="flex w-full items-center gap-1.5 sm:hidden">
                @if ($paginator->onFirstPage())
                    <span class="admin-pager-link is-disabled flex-1" aria-disabled="true">Previous</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="admin-pager-link flex-1">Previous</a>
                @endif

                <span class="admin-pager-gap shrink-0 tabular-nums">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="admin-pager-link flex-1">Next</a>
                @else
                    <span class="admin-pager-link is-disabled flex-1" aria-disabled="true">Next</span>
                @endif
            </div>

            {{-- Desktop: the page list, with the current page carrying
                 aria-current so the highlight is read out, not just seen. --}}
            <div class="hidden items-center gap-1.5 sm:flex">
                @if ($paginator->onFirstPage())
                    <span class="admin-pager-link is-disabled" aria-disabled="true" aria-label="Previous page">Previous</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="admin-pager-link" aria-label="Previous page">Previous</a>
                @endif

                @foreach ($elements as $element)
                    {{-- The "…" between distant page groups. --}}
                    @if (is_string($element))
                        <span class="admin-pager-gap" aria-hidden="true">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="admin-pager-link tabular-nums" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="admin-pager-link tabular-nums" aria-label="Go to page {{ $page }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="admin-pager-link" aria-label="Next page">Next</a>
                @else
                    <span class="admin-pager-link is-disabled" aria-disabled="true" aria-label="Next page">Next</span>
                @endif
            </div>
        </div>
    </nav>
@endif
