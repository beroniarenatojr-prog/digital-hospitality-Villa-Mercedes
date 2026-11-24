{{-- The status split is asked for by both the Executive and the Financial view,
     and each row is a route into the filtered reservation list, so it is
     defined once: a status that gains a filter link gains it in both places. --}}
<x-admin.panel
    title="Reservations by Status"
    :padding="false"
>
    <ul class="divide-y divide-border">
        @foreach (\App\Enums\ReservationStatus::cases() as $status)
            <li>
                <a
                    href="{{ route('admin.reservations.index', ['status' => $status->value]) }}"
                    class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm transition-colors duration-150 hover:bg-primary-light/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary"
                >
                    <span class="text-text">{{ $status->label() }}</span>
                    <strong class="tabular-nums text-text">{{ number_format($bookingSummary['by_status'][$status->value] ?? 0) }}</strong>
                </a>
            </li>
        @endforeach
    </ul>
</x-admin.panel>
