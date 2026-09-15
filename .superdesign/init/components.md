# Shared UI primitives

## PrimaryButton
- Path: `resources/views/components/primary-button.blade.php`
- Brown filled CTA. Props: tag.

```blade
@props(['tag' => 'button'])

@php
    $classes = 'inline-flex items-center justify-center gap-2 px-5 py-2.5 min-h-[44px] bg-primary border border-transparent rounded-lg font-semibold text-sm text-white hover:bg-primary-hover focus:bg-primary-hover active:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150';
@endphp

@if($tag === 'a')
    <a {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'submit', 'class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
```

## SecondaryButton
- Path: `resources/views/components/secondary-button.blade.php`
- Outlined surface button. Props: tag.

```blade
@props(['tag' => 'button'])

@php
    $classes = 'inline-flex items-center justify-center gap-2 px-5 py-2.5 min-h-[44px] bg-surface border border-border rounded-lg font-semibold text-sm text-text shadow-sm hover:bg-background hover:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150';
@endphp

@if($tag === 'a')
    <a {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
```

## DangerButton
- Path: `resources/views/components/danger-button.blade.php`
- Danger submit button.

```blade
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 px-5 py-2.5 min-h-[44px] bg-danger border border-transparent rounded-lg font-semibold text-sm text-white hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-danger focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
```

## Card
- Path: `resources/views/components/card.blade.php`
- Surface card with border. Props: class.

```blade
@props(['class' => ''])

<div {{ $attributes->merge(['class' => 'bg-surface overflow-hidden shadow-sm rounded-xl border border-border ' . $class]) }}>
    {{ $slot }}
</div>
```

## Badge
- Path: `resources/views/components/badge.blade.php`
- Status pill. Props: status.

```blade
@props(['status'])

@php
    $colors = match (strtolower($status)) {
        'approved', 'verified', 'available', 'completed', 'closed', 'accepted' => 'bg-success-light text-success',
        'pending', 'open', 'waitlisted' => 'bg-warning-light text-warning',
        'rejected', 'cancelled', 'unavailable' => 'bg-danger-light text-danger',
        'ongoing', 'planned' => 'bg-info-light text-info',
        'rescheduled', 'maintenance' => 'bg-secondary text-text-muted',
        default => 'bg-secondary text-text',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium " . $colors]) }}>
    {{ $status }}
</span>
```

## Alert
- Path: `resources/views/components/alert.blade.php`
- Dismissible alert. Props: type, title.

```blade
@props(['type' => 'info', 'title' => null])

@php
    $type = in_array($type, ['success', 'warning', 'danger', 'error', 'info']) ? $type : 'info';

    [$classes, $iconPath, $defaultTitle] = match ($type) {
        'success' => [
            'bg-success-light border-success text-emerald-900',
            'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            'Success',
        ],
        'warning' => [
            'bg-warning-light border-warning text-amber-900',
            'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
            'Please check',
        ],
        'danger', 'error' => [
            'bg-danger-light border-danger text-rose-900',
            'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
            'Something went wrong',
        ],
        default => [
            'bg-info-light border-info text-sky-900',
            'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            'Note',
        ],
    };
@endphp

<div
    x-data="{ visible: true }"
    x-show="visible"
    x-transition.opacity.duration.150ms
    {{ $attributes->merge(['class' => 'flex items-start gap-3 p-4 border-l-4 rounded-r-lg ' . $classes]) }}
    role="alert"
    aria-live="polite"
>
    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}" />
    </svg>
    <div class="min-w-0 flex-1">
        <p class="font-semibold text-sm">{{ $title ?? $defaultTitle }}</p>
        <div class="text-sm mt-0.5">
            {{ $slot }}
        </div>
    </div>
    <button type="button" @click="visible = false" class="shrink-0 rounded p-1 -m-1 opacity-70 hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-current" aria-label="Dismiss message">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
        </svg>
    </button>
</div>
```

## EmptyState
- Path: `resources/views/components/empty-state.blade.php`
- Dashed empty listing. Props: icon, title, description.

```blade
@props(['icon' => null, 'title', 'description' => null])

@php
    // Callers pass a name (e.g. "home"), not markup.
    $iconPath = match ($icon) {
        'home' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        'sparkles' => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
        'ticket' => 'M15 5v2m0 4v2m0 4v2M5 5h14a2 2 0 012 2v3a2 2 0 000 4v3a2 2 0 01-2 2H5a2 2 0 01-2-2v-3a2 2 0 000-4V7a2 2 0 012-2z',
        'search' => 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
        'calendar' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        default => null,
    };
@endphp

<div {{ $attributes->merge(['class' => 'text-center py-12 px-4 sm:px-6 lg:px-8 border-2 border-dashed border-border rounded-xl bg-surface']) }}>
    @if ($iconPath)
        <svg class="mx-auto h-12 w-12 text-text-muted opacity-60 mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}" />
        </svg>
    @endif
    <h3 class="text-base font-semibold text-text">{{ $title }}</h3>
    @if ($description)
        <p class="mt-2 text-sm text-text-muted max-w-md mx-auto">{{ $description }}</p>
    @endif
    @if (isset($action))
        <div class="mt-6">
            {{ $action }}
        </div>
    @endif
</div>
```

## LoadingCard
- Path: `resources/views/components/loading-card.blade.php`
- Pulse skeleton. Props: count.

```blade
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
```

## TextInput
- Path: `resources/views/components/text-input.blade.php`
- Text input. Props: disabled.

```blade
@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'border-border focus:border-primary focus:ring-primary rounded-lg shadow-sm']) !!}>
```

## InputLabel
- Path: `resources/views/components/input-label.blade.php`
- Form label. Props: value.

```blade
@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-text']) }}>
    {{ $value ?? $slot }}
</label>
```

## InputError
- Path: `resources/views/components/input-error.blade.php`
- Validation list. Props: messages.

```blade
@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'text-sm text-danger space-y-1']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
```

## NavLink
- Path: `resources/views/components/nav-link.blade.php`
- Desktop nav link. Props: active, onDark.

```blade
@props(['active' => false, 'onDark' => false])

@php
if ($onDark) {
    $classes = $active
        ? 'inline-flex items-center px-1 pt-1 border-b-2 border-white text-sm font-semibold leading-5 text-white focus:outline-none transition duration-150 ease-in-out'
        : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-white/80 hover:text-white hover:border-white/50 focus:outline-none focus:text-white transition duration-150 ease-in-out';
} else {
    $classes = $active
        ? 'inline-flex items-center px-1 pt-1 border-b-2 border-primary text-sm font-semibold leading-5 text-primary focus:outline-none transition duration-150 ease-in-out'
        : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-text-muted hover:text-primary hover:border-primary/40 focus:outline-none focus:text-primary transition duration-150 ease-in-out';
}
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if($active) aria-current="page" @endif>
    {{ $slot }}
</a>
```

## ResponsiveNavLink
- Path: `resources/views/components/responsive-nav-link.blade.php`
- Mobile nav link. Props: active.

```blade
@props(['active' => false])

@php
$classes = $active
            ? 'flex items-center w-full ps-4 pe-4 py-3 border-l-4 border-primary text-start text-base font-semibold text-primary bg-emerald-50 focus:outline-none transition duration-150 ease-in-out'
            : 'flex items-center w-full ps-4 pe-4 py-3 border-l-4 border-transparent text-start text-base font-medium text-text hover:text-primary hover:bg-background hover:border-primary/40 focus:outline-none focus:text-primary focus:bg-background transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if($active) aria-current="page" @endif>
    {{ $slot }}
</a>
```

## SectionHeading
- Path: `resources/views/components/section-heading.blade.php`
- Section title + rule. Props: title, eyebrow, align, tone.

```blade
@props(['title', 'eyebrow' => null, 'align' => 'center', 'tone' => 'dark'])

@php
    $wrap = $align === 'left' ? 'text-left' : 'text-center mx-auto';
    $titleColor = $tone === 'light' ? 'text-white' : 'text-primary';
    $bodyColor = $tone === 'light' ? 'text-white/85' : 'text-text-muted';
    $ruleColor = $tone === 'light' ? 'bg-white' : 'bg-primary';
@endphp

<div {{ $attributes->merge(['class' => 'max-w-3xl '.$wrap]) }}>
    @if($eyebrow)
        <p class="text-xs font-semibold uppercase tracking-[0.2em] {{ $tone === 'light' ? 'text-white/70' : 'text-accent' }} mb-3">{{ $eyebrow }}</p>
    @endif
    <h2 class="font-heading text-4xl md:text-5xl font-semibold {{ $titleColor }}">{{ $title }}</h2>
    <span class="mt-4 block h-1 w-16 rounded-full {{ $ruleColor }} {{ $align === 'left' ? '' : 'mx-auto' }}" aria-hidden="true"></span>
    @if(isset($slot) && trim($slot) !== '')
        <p class="mt-5 text-base {{ $bodyColor }} leading-relaxed">{{ $slot }}</p>
    @endif
</div>
```

## PriceDisplay
- Path: `resources/views/components/price-display.blade.php`
- Peso amount. Props: amount, class.

```blade
@props(['amount', 'class' => ''])

<span {{ $attributes->merge(['class' => 'tabular-nums font-semibold ' . $class]) }}>
    ₱{{ number_format($amount, 2) }}
</span>
```

## FacilityCard
- Path: `resources/views/components/facility-card.blade.php`
- Listing space card. Props: facility, showImage.

```blade
@props(['facility', 'showImage' => true])

@php
    $baseRate = $facility->rates->first();
    // Dates chosen on the availability bar follow the guest into the booking wizard.
    $stayQuery = array_filter(request()->only(['check_in', 'check_out', 'guests']));
    $bookable = $facility->isOnlineBookable();
@endphp

<x-card class="group relative flex flex-col h-full overflow-hidden transition duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:border-primary/40 focus-within:border-primary">
    <div class="relative w-full aspect-[16/10] bg-muted flex items-center justify-center text-text-muted overflow-hidden">
        @if($showImage && $facility->galleryImages->count() > 0)
            <img src="{{ $facility->galleryImages->first()->url() }}" alt="{{ $facility->name }}" loading="lazy" class="w-full h-full object-cover transition duration-500 group-hover:scale-105" />
        @else
            <svg class="w-12 h-12 opacity-20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        @endif

        @if($showImage && $facility->galleryImages->count() > 1)
            <span class="absolute bottom-3 right-3 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-black/70 text-white">
                {{ $facility->galleryImages->count() }} photos
            </span>
        @endif

        @if($facility->facilityType)
            <span class="absolute top-3 left-3 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-surface/95 text-text shadow-sm">
                {{ $facility->facilityType->name }}
            </span>
        @endif
    </div>

    <div class="flex flex-col flex-grow p-5">
        <h3 class="font-heading text-2xl font-semibold text-text leading-snug">
            {{-- Stretched link: the whole card is clickable, but only one link is announced. --}}
            <a href="{{ route('facilities.show', ['facility' => $facility, ...$stayQuery]) }}" class="focus:outline-none before:absolute before:inset-0 group-hover:text-primary transition-colors">
                <span class="relative">{{ $facility->name }}</span>
            </a>
        </h3>

        <p class="text-sm text-text-muted mt-2 line-clamp-2">
            {{ $facility->description }}
        </p>

        <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-sm text-text-muted">
            <span class="flex items-center gap-1.5">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                Up to {{ $facility->effectiveMaxCapacity() }} guests
            </span>

            @if($facility->amenities->count() > 0)
                <span class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                    {{ $facility->amenities->take(2)->pluck('name')->join(', ') }}@if($facility->amenities->count() > 2) +{{ $facility->amenities->count() - 2 }} more @endif
                </span>
            @endif
        </div>

        <div class="mt-auto pt-4 border-t border-border flex items-end justify-between gap-3">
            <div>
                @if($baseRate)
                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">From</p>
                    <p class="flex items-baseline gap-1">
                        <x-price-display :amount="$baseRate->amount" class="font-heading text-3xl font-semibold text-primary" />
                        <span class="text-sm text-text-muted">/ {{ $baseRate->unit }}</span>
                    </p>
                @else
                    <p class="text-sm font-medium text-text-muted">Ask us for the rate</p>
                @endif
            </div>

            @if($bookable)
                <a href="{{ route('customer.booking.create', ['facility' => $facility, ...$stayQuery]) }}"
                   class="relative z-10 inline-flex items-center gap-1 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition">
                    Check dates
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                </a>
            @else
                <span class="relative inline-flex items-center gap-1 text-sm font-semibold text-primary group-hover:underline">
                    View details
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                </span>
            @endif
        </div>
    </div>
</x-card>
```

## FeaturedFacilityCard
- Path: `resources/views/components/featured-facility-card.blade.php`
- Home featured stay. Props: facility.

```blade
@props(['facility'])

@php
    $coverImageUrl = $facility->cover_image_path
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($facility->cover_image_path)
        : null;
    $rate = $facility->base_rate_amount;
    $capacity = $facility->max_capacity ?? $facility->capacity;
@endphp

<article class="group flex h-full flex-col overflow-hidden rounded-3xl border border-border bg-surface shadow-sm transition duration-200 hover:-translate-y-1 hover:border-primary/40 hover:shadow-lg">
    <div class="relative aspect-[16/10] overflow-hidden bg-muted">
        @if($coverImageUrl)
            <img
                src="{{ $coverImageUrl }}"
                alt="{{ $facility->name }}"
                loading="lazy"
                decoding="async"
                class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
            >
        @else
            <div class="flex h-full items-center justify-center bg-gradient-to-br from-primary/90 to-primary-dark text-white/80">
                <svg class="h-12 w-12" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
            </div>
        @endif
        <span class="absolute left-4 top-4 inline-flex items-center gap-1.5 rounded-full bg-success-light/95 px-3 py-1 text-xs font-semibold text-success shadow-sm">
            <span class="h-1.5 w-1.5 rounded-full bg-success" aria-hidden="true"></span>
            Open to reserve
        </span>
    </div>

    <div class="flex flex-1 flex-col p-6">
        <h3 class="font-heading text-3xl font-semibold leading-tight text-text">
            <a href="{{ route('facilities.show', $facility) }}" class="hover:text-primary focus:outline-none focus:text-primary">
                {{ $facility->name }}
            </a>
        </h3>
        <p class="mt-3 line-clamp-2 text-sm leading-relaxed text-text-muted">{{ $facility->description }}</p>
        <p class="mt-4 flex items-center gap-1.5 text-sm text-text-muted">
            <svg class="h-4 w-4 shrink-0 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
            Up to {{ $capacity }} guests
        </p>

        <div class="mt-auto flex items-end justify-between gap-4 border-t border-border pt-5">
            <div>
                @if($rate !== null)
                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">From</p>
                    <p class="font-heading text-3xl font-semibold text-primary">₱{{ number_format((float) $rate, 2) }}</p>
                    @if($facility->base_rate_unit)
                        <p class="text-xs text-text-muted">per {{ $facility->base_rate_unit }}</p>
                    @endif
                @else
                    <p class="text-sm font-medium text-text-muted">Ask us for the rate</p>
                @endif
            </div>
            <a href="{{ route('customer.booking.create', $facility) }}" class="inline-flex min-h-[44px] items-center gap-1 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                Check dates
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
            </a>
        </div>
    </div>
</article>
```

## SocialLinks
- Path: `resources/views/components/social-links.blade.php`
- Facebook/Instagram. Props: size.

```blade
@props(['size' => 'md'])

@php
    $button = $size === 'sm'
        ? 'inline-flex items-center justify-center w-9 h-9 rounded-full text-white'
        : 'inline-flex items-center justify-center w-12 h-12 rounded-full text-white';
    $icon = $size === 'sm' ? 'w-4 h-4' : 'w-5 h-5';
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-3']) }}>
    <a
        href="{{ config('resort.facebook_url') }}"
        target="_blank"
        rel="noopener noreferrer"
        class="{{ $button }} bg-[#1877F2] hover:brightness-110"
        aria-label="{{ config('resort.facebook_label') }}"
    >
        <span class="font-bold {{ $size === 'sm' ? 'text-sm' : '' }}">f</span>
    </a>
    <a
        href="{{ config('resort.instagram_url') }}"
        target="_blank"
        rel="noopener noreferrer"
        class="{{ $button }} bg-[linear-gradient(45deg,#f9ce34,#ee2a7b,#6228d7)] hover:brightness-110"
        aria-label="{{ config('resort.instagram_label') }}"
    >
        <svg class="{{ $icon }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
            <rect x="3" y="3" width="18" height="18" rx="5" />
            <circle cx="12" cy="12" r="4" />
            <circle cx="17.5" cy="6.5" r="0.8" fill="currentColor" stroke="none" />
        </svg>
    </a>
</div>
```

## BackToTop
- Path: `resources/views/components/back-to-top.blade.php`
- Scroll-to-top FAB.

```blade
<button
    type="button"
    x-data="{ show: false }"
    x-init="show = window.scrollY > 400"
    @scroll.window.passive="show = window.scrollY > 400"
    x-show="show"
    x-cloak
    x-transition.opacity
    @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
    class="fixed bottom-20 right-5 z-40 inline-flex items-center justify-center w-12 h-12 rounded-full bg-primary text-white shadow-lg hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 sm:bottom-24 sm:right-7"
    aria-label="Back to top"
>
    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
</button>
```

