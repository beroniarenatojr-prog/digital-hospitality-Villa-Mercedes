<x-customer-layout>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 space-y-6">
        <h1 class="font-heading text-4xl">New reservation</h1>
        <p>Choose a service and check availability. A 50% QR deposit secures a standard booking after staff verify payment. The remaining balance is collected at arrival.</p>
        <nav class="flex flex-wrap gap-3" aria-label="Reservation services">
            @foreach(['room' => 'Rooms', 'cottage' => 'Cottages', 'resort_package' => 'Packages', 'function_hall' => 'Function hall inquiries'] as $key => $label)
                <a href="{{ route('customer.reservations.new', [...$selection, 'type' => $key]) }}" @if($type === $key) aria-current="page" @endif class="px-4 py-2 rounded-lg border {{ $type === $key ? 'bg-primary text-white' : 'border-border' }}">{{ $label }}</a>
            @endforeach
        </nav>
        <form method="GET" class="grid sm:grid-cols-4 gap-3 bg-surface border border-border rounded-xl p-4">
            <input type="hidden" name="type" value="{{ $type }}">
            <label>{{ $type === 'cottage' ? 'Visit date' : 'Arrival date' }}<input type="date" name="{{ $type === 'cottage' ? 'date' : 'check_in' }}" value="{{ $selection[$type === 'cottage' ? 'date' : 'check_in'] ?? '' }}" min="{{ now()->toDateString() }}" class="block w-full rounded border-border"></label>
            @if($type !== 'cottage')<label>Departure date<input type="date" name="check_out" value="{{ $selection['check_out'] ?? '' }}" min="{{ now()->toDateString() }}" class="block w-full rounded border-border"></label>@endif
            <label>Guests<input type="number" min="1" name="guests" value="{{ $selection['guests'] ?? 1 }}" class="block w-full rounded border-border"></label>
            @if(isset($selection['session']))<input type="hidden" name="session" value="{{ $selection['session'] }}">@endif
            <button class="self-end bg-primary text-white rounded-lg px-4 py-2">Use these dates</button>
        </form>
        <p class="text-sm text-text-muted">Availability and the exact total are checked on the selected reservation form. Pool entrance fees are separate and paid at the resort.</p>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($facilities as $facility)
                <article class="bg-surface rounded-xl border border-border overflow-hidden flex flex-col">
                    @if($facility->galleryImages->first())<img src="{{ $facility->galleryImages->first()->url() }}" alt="{{ $facility->name }}" class="w-full aspect-video object-cover" loading="lazy">@endif
                    <div class="p-5 space-y-3 flex-1">
                        <h2 class="font-heading text-2xl">{{ $facility->name }}</h2>
                        <p class="text-sm">{{ $facility->description }}</p>
                        <p class="text-sm">Up to {{ $facility->maxGuestCount() }} guests</p>
                        @if($type === 'function_hall' || ! $facility->isOnlineBookable())
                            <a class="text-primary font-semibold underline" href="{{ route('customer.inquiries.index', ['subject' => 'Quotation for '.$facility->name]) }}">Ask staff for a quotation</a>
                        @else
                            <a class="inline-block bg-primary text-white rounded-lg px-4 py-2" href="{{ route('customer.booking.create', ['facility' => $facility, ...$selection]) }}">Continue to reservation</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
        {{ $facilities->links() }}
    </div>
</x-customer-layout>
