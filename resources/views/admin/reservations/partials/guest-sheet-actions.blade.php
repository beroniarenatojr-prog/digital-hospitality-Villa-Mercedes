<div class="flex flex-wrap gap-2" role="group" aria-label="Guest sheet for {{ $reservation->facility->name }} ({{ $reservation->reservation_code }})">
    <x-admin.button variant="secondary" :href="route('admin.reservations.guest-information-sheet', $reservation)">
        Download guest sheet
    </x-admin.button>
    <x-admin.button
        variant="secondary"
        :href="route('admin.reservations.guest-information-sheet', ['reservation' => $reservation, 'print' => 1])"
        target="_blank"
        rel="noopener"
    >
        Print guest sheet
    </x-admin.button>
</div>
