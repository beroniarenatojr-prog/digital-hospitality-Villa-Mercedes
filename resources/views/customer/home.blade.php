<x-customer-layout>
    <div class="py-10 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-10 px-4 sm:px-6 lg:px-8">
            <x-customer.page-header
                eyebrow="Villa Mercedes experience"
                title="What would you like to reserve?"
                description="Start with the kind of visit you have in mind. Your dates and party size stay with you as you continue."
            >
                <x-slot name="actions">
                    <x-customer.step-pill :step="1" label="Select category" />
                </x-slot>
            </x-customer.page-header>

            <section aria-labelledby="service-choice-heading">
                <h2 id="service-choice-heading" class="sr-only">Reservation categories</h2>

                <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6">
                    @foreach($services as $index => $service)
                        @php
                            $fallbackImage = match ($service['type']) {
                                'room' => asset('images/rooms & cottage.jpg'),
                                'cottage' => asset('images/rooms & cottage.jpg'),
                                'resort_package' => asset('images/resort pakage.jpg'),
                                'function_hall' => asset('images/event-hosting.jpg'),
                            };
                            $serviceUrl = route('customer.grouped-booking.workspace', ['type' => $service['type']]);
                        @endphp
                        <li>
                            <article class="group flex h-full flex-col overflow-hidden rounded-2xl border border-border bg-surface shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md focus-within:border-primary">
                                <a href="{{ $serviceUrl }}" class="relative block aspect-[4/3] overflow-hidden bg-primary-light focus:outline-none">
                                    <img
                                        src="{{ $service['image'] ?: $fallbackImage }}"
                                        alt="{{ $service['image'] ? $service['imageAlt'] : $service['name'] }}"
                                        class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                                        loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                                    >
                                    <span class="absolute left-3 top-3 inline-flex items-center rounded-full bg-surface/95 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-text shadow-sm">
                                        {{ $service['tag'] }}
                                    </span>
                                </a>
                                <div class="flex flex-1 flex-col p-5">
                                    <h3 class="font-heading text-2xl font-semibold text-text">
                                        <a href="{{ $serviceUrl }}" class="focus:outline-none focus:underline">{{ $service['name'] }}</a>
                                    </h3>
                                    <p class="mt-2 flex-1 text-sm leading-relaxed text-text-muted">{{ $service['description'] }}</p>
                                    <div class="mt-5">
                                        <x-primary-button tag="a" href="{{ $serviceUrl }}" class="w-full justify-center uppercase tracking-[0.08em]">
                                            {{ $service['action'] }}
                                        </x-primary-button>
                                    </div>
                                </div>
                            </article>
                        </li>
                    @endforeach
                </ul>
            </section>

            @if($unfinished->isNotEmpty())
                <section aria-labelledby="continue-booking-heading" class="border-t border-border pt-8">
                    <div class="max-w-2xl">
                        <h2 id="continue-booking-heading" class="font-heading text-2xl font-semibold text-text sm:text-3xl">
                            Continue where you left off
                        </h2>
                        <p class="mt-1 text-sm text-text-muted">
                            These bookings are not submitted and do not hold a space. Your saved details are ready when you are.
                        </p>
                    </div>
                    <ul class="mt-5 grid gap-4 lg:grid-cols-2">
                        @foreach($unfinished->take(2) as $booking)
                            <li>
                                @include('customer.reservations.partials.unfinished-card', ['booking' => $booking])
                            </li>
                        @endforeach
                    </ul>
                    @if($unfinished->count() > 2)
                        <a href="{{ route('customer.reservations.index') }}" class="mt-4 inline-flex min-h-11 items-center text-sm font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                            View all unfinished bookings
                        </a>
                    @endif
                </section>
            @endif

            <x-customer.trust-strip />
        </div>
    </div>
</x-customer-layout>
