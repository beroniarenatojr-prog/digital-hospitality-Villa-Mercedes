{{-- Shared by the Executive and Financial views: both ask what the resort sold
     most of, so the two panels have exactly one definition here and each view
     places them in its own grid. --}}
<x-admin.panel
    title="Most Reserved Services"
    description="Rooms and function halls, by recorded bookings."
    :padding="false"
>
    @forelse ($popularServices as $name => $count)
        <div class="flex items-center justify-between gap-3 border-b border-border px-5 py-3 text-sm last:border-b-0">
            <span class="min-w-0 truncate text-text">{{ $name }}</span>
            <strong class="shrink-0 tabular-nums text-text">{{ number_format($count) }}</strong>
        </div>
    @empty
        <x-admin.empty
            icon="facilities"
            title="No service was booked in this range"
            description="Rooms and function halls appear here once reservations inside the selected dates are recorded."
        />
    @endforelse
</x-admin.panel>

<x-admin.panel
    title="Package Popularity"
    :description="'Total package reservations: '.number_format($packagePopularity['total_package_bookings']).'.'"
    :padding="false"
>
    @forelse ($packagePopularity['breakdown'] as $package)
        <div class="flex items-center justify-between gap-3 border-b border-border px-5 py-3 text-sm last:border-b-0">
            <span class="min-w-0 truncate text-text">{{ $package['name'] }}</span>
            <strong class="shrink-0 tabular-nums text-text">{{ number_format($package['count']) }} ({{ $package['share_percentage'] }}%)</strong>
        </div>
    @empty
        <x-admin.empty
            icon="facilities"
            title="No package was booked in this range"
            description="Package reservations appear here once a booking inside the selected dates is recorded."
        />
    @endforelse
</x-admin.panel>
