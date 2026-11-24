@props(['search'])

@php
    /** @var array<string, mixed>|null $search */
    $search = $search ?? null;
@endphp

@if(is_array($search))
    <section class="mb-12" aria-labelledby="availability-results-heading">
        <div class="mb-6">
            <h2 id="availability-results-heading" class="font-heading text-3xl font-semibold text-text">Availability for your dates</h2>
            @if(($search['status'] ?? null) === 'available')
                <p class="mt-2 text-sm text-text-muted">These options are free for the full date range and party size you entered. Starting rates are shown; the exact total is calculated when you continue from the details page.</p>
            @endif
        </div>

        @if(in_array($search['status'] ?? null, ['unavailable', 'fully_booked', 'temporarily_closed'], true))
            <p role="status" class="rounded-lg bg-danger-light px-4 py-3 text-sm font-semibold text-danger">
                {{ $search['message'] ?? 'Not available on the selected date/time.' }}
            </p>
        @elseif(($search['status'] ?? null) === 'walk_in')
            <p role="status" class="rounded-lg bg-warning-light px-4 py-3 text-sm text-warning">
                {{ $search['message'] }}
            </p>
        @elseif(($search['status'] ?? null) === 'invalid')
            <p role="alert" class="rounded-lg bg-danger-light px-4 py-3 text-sm text-danger">
                {{ $search['message'] }}
            </p>
        @elseif(($search['status'] ?? null) === 'available')
            <p class="text-sm font-semibold text-text">Available for your selected dates</p>
        @endif

        @if(! empty($search['results']))
            <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($search['results'] as $item)
                    <x-availability-result-card :item="$item" />
                @endforeach
            </div>

            @guest
                @if(($search['status'] ?? null) === 'available')
                    <p class="mt-4 text-xs text-text-muted">Sign in or create an account to continue. Your service and visit details will be kept.</p>
                @endif
            @endguest
        @endif

        @if(! empty($search['alternatives']))
            <div class="mt-10 border-t border-border pt-8">
                <h3 class="text-sm font-semibold text-text">Other options for these dates</h3>
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($search['alternatives'] as $item)
                        <x-availability-result-card :item="$item" />
                    @endforeach
                </div>
            </div>
        @endif
    </section>
@endif
