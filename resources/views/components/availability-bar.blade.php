@props(['variant' => 'hero', 'submitLabel' => 'Check Availability'])

@php
    $routeName = request()->route()?->getName();
    $defaultType = request('service_type', match ($routeName) {
        'halls' => \App\Enums\BookingServiceType::FunctionHall->value,
        'packages' => \App\Enums\BookingServiceType::ResortPackage->value,
        'cottages', 'resort.show' => \App\Enums\BookingServiceType::Cottage->value,
        'pools' => \App\Enums\BookingServiceType::Pool->value,
        default => \App\Enums\BookingServiceType::Room->value,
    });
    if (! in_array($defaultType, \App\Enums\BookingServiceType::values(), true)) {
        $defaultType = \App\Enums\BookingServiceType::Room->value;
    }

    $destinations = collect(\App\Enums\BookingServiceType::cases())
        ->mapWithKeys(fn (\App\Enums\BookingServiceType $type) => [$type->value => $type->listingUrl()])
        ->all();
    $action = $destinations[$defaultType] ?? route('rooms');

    $today = now()->format('Y-m-d');
    $checkIn = request('check_in', request('checkin'));
    $checkOut = request('check_out', request('checkout'));
    $guests = request('guests');
    $session = request('session');
    $sessions = \App\Models\SwimmingSession::query()->where('is_active', true)->orderBy('sort_order')->get(['name', 'slug']);
    $onHero = $variant === 'hero';
    $controlClasses = 'box-border w-full h-11 min-h-11 max-h-11 px-3 py-0 rounded-lg border text-sm leading-none shadow-sm';
    $fieldClasses = $controlClasses.' border-border bg-surface text-text focus:border-primary focus:ring-primary';
    $buttonClasses = $controlClasses.' inline-flex items-center justify-center border-transparent bg-primary font-semibold text-white hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2';
    $labelClasses = 'block text-xs font-semibold uppercase tracking-wide text-text-muted mb-1.5';
@endphp

<form
    method="GET"
    action="{{ $action }}"
    x-data="{
        serviceType: @js($defaultType),
        destinations: @js($destinations),
        checkIn: @js($checkIn),
        checkOut: @js($checkOut),
        session: @js($session),
        today: @js($today),
        get action() { return this.destinations[this.serviceType] || this.destinations.room; },
        get showsSession() { return this.serviceType === 'cottage'; },
        get checkOutMin() { return this.checkIn || this.today; },
        init() {
            this.$watch('checkIn', (value) => {
                if (value && this.checkOut && this.checkOut < value) {
                    this.checkOut = value;
                }
            });
        },
    }"
    x-bind:action="action"
    @class([
        'rounded-2xl',
        'bg-surface/95 backdrop-blur-md p-4 sm:p-5 shadow-2xl ring-1 ring-black/5' => $onHero,
        'bg-surface border-y border-border p-4 shadow-none' => ! $onHero,
    ])
>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 lg:gap-4 lg:items-end">
        <div>
            <label for="availability-space" class="{{ $labelClasses }}">What do you need?</label>
            <select id="availability-space" x-model="serviceType" class="{{ $fieldClasses }}" required>
                @foreach(\App\Enums\BookingServiceType::cases() as $type)
                    <option value="{{ $type->value }}" @selected($type->value === $defaultType)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="availability-check-in" class="{{ $labelClasses }}">Date In</label>
            <input
                type="date"
                id="availability-check-in"
                name="check_in"
                value="{{ $checkIn }}"
                x-model="checkIn"
                min="{{ $today }}"
                required
                class="{{ $fieldClasses }}"
            >
        </div>

        <div>
            <label for="availability-check-out" class="{{ $labelClasses }}">Date Out</label>
            <input
                type="date"
                id="availability-check-out"
                name="check_out"
                value="{{ $checkOut }}"
                x-model="checkOut"
                :min="checkOutMin"
                required
                class="{{ $fieldClasses }}"
            >
        </div>

        <div>
            <label for="availability-guests" class="{{ $labelClasses }}">Guests</label>
            <input
                type="number"
                id="availability-guests"
                name="guests"
                value="{{ $guests }}"
                min="1"
                max="500"
                inputmode="numeric"
                required
                class="{{ $fieldClasses }}"
            >
        </div>

        <div>
            <span class="{{ $labelClasses }} invisible hidden lg:block" aria-hidden="true">Guests</span>
            <button type="submit" class="{{ $buttonClasses }}">
                {{ $submitLabel }}
            </button>
        </div>
    </div>

    <div x-show="showsSession" x-cloak class="mt-3 max-w-xs">
        <label for="availability-session" class="{{ $labelClasses }}">Swimming session</label>
        <select id="availability-session" name="session" x-model="session" x-bind:disabled="!showsSession" class="{{ $fieldClasses }}">
            <option value="">Any session</option>
            @foreach($sessions as $swimmingSession)
                <option value="{{ $swimmingSession->slug }}" @selected($session === $swimmingSession->slug)>{{ $swimmingSession->name }}</option>
            @endforeach
        </select>
    </div>

    @if(request()->filled('min_capacity'))
        <input type="hidden" name="min_capacity" value="{{ request('min_capacity') }}">
    @endif
    @if(request()->filled('sort') && request('sort') !== 'recommended')
        <input type="hidden" name="sort" value="{{ request('sort') }}">
    @endif

    @if($onHero)
        <p class="mt-3 text-xs text-text-muted text-center sm:text-left">
            Checking availability is free. Results show published starting rates; the exact total is calculated later.
        </p>
    @endif
</form>
