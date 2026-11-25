<x-customer-layout>
    @php
        $stayQuery = \App\Support\StayQuery::parameters(request());
        $checkIn = $stayQuery['check_in'] ?? null;
        $checkOut = $stayQuery['check_out'] ?? null;
        $guests = $stayQuery['guests'] ?? null;
        $hasStayQuery = filled($checkIn) || filled($checkOut) || filled($guests);
        $availability = $availability ?? null;
        $pools = $pools ?? collect();
        $minCapacity = $minCapacity ?? 0;
        $sort = $sort ?? 'recommended';
        $listingType = $listingType ?? 'rooms';
        $packageFamilies = $packageFamilies ?? collect();
        $ungroupedPackages = $ungroupedPackages ?? collect();
        $groupedFacilities = $groupedFacilities ?? (
            $listingType === 'rooms'
                ? $facilities->groupBy(fn ($facility) => $facility->listingCategory())
                : collect([($title ?? 'Rooms') => $facilities])
        );
        $heroHeadline = $heroHeadline ?? $title;
        $heroSupport = $heroSupport ?? ($description ?? null);
        $isServicesHero = in_array($listingType, ['rooms', 'halls', 'packages'], true);
        $tabs = [
            ['key' => 'rooms', 'label' => 'Rooms', 'route' => 'rooms'],
            ['key' => 'halls', 'label' => 'Function Halls', 'route' => 'halls'],
            ['key' => 'packages', 'label' => 'Packages', 'route' => 'packages'],
            ['key' => 'cottages', 'label' => 'Cottages', 'route' => 'cottages'],
            ['key' => 'pools', 'label' => 'Pools', 'route' => 'pools'],
        ];
        $capacityOptions = [0 => 'Any', 2 => '2+', 4 => '4+', 6 => '6+', 10 => '10+', 20 => '20+', 100 => '100+'];
        $noun = match ($listingType) {
            'halls' => 'function halls',
            'packages' => 'packages',
            'cottages' => 'cottages',
            'pools' => 'pools',
            default => 'rooms',
        };
        $catalogCount = match ($listingType) {
            'pools' => $pools->count(),
            'packages' => $packageFamilies->count() + $ungroupedPackages->count(),
            default => $facilities->count(),
        };
        $roomCategories = $listingType === 'rooms'
            ? $facilities->map(fn ($facility) => $facility->listingCategory())->unique()->count()
            : 0;
        $packageVariants = $listingType === 'packages'
            ? $facilities->filter(fn ($facility) => $facility->stay_category !== null)
                ->map(fn ($facility) => $facility->stay_category->value)
                ->unique()
                ->count()
            : 0;
        $countLabel = match ($listingType) {
            'halls' => $catalogCount.' '.strtoupper(\Illuminate\Support\Str::plural('function hall', $catalogCount)),
            'packages' => $catalogCount.' '.strtoupper(\Illuminate\Support\Str::plural('package', $catalogCount))
                .($packageVariants > 0 ? ' · '.$packageVariants.' '.strtoupper(\Illuminate\Support\Str::plural('variant', $packageVariants)) : ''),
            'cottages' => $catalogCount.' '.strtoupper(\Illuminate\Support\Str::plural('cottage', $catalogCount)),
            'pools' => $catalogCount.' '.strtoupper(\Illuminate\Support\Str::plural('pool', $catalogCount)),
            default => $catalogCount.' '.strtoupper(\Illuminate\Support\Str::plural('room', $catalogCount))
                .($roomCategories > 0 ? ' · '.$roomCategories.' '.strtoupper(\Illuminate\Support\Str::plural('category', $roomCategories)) : ''),
        };
        $listingHero = match ($listingType) {
            'cottages' => [
                'src' => asset('images/rooms-and-cottages.jpg'),
                'alt' => 'Wooden guest cottages at Villa Mercedes with peaked roofs along a garden path',
            ],
            default => [
                'src' => asset('images/resort-package.jpg'),
                'alt' => 'Evening pool deck at Villa Mercedes with lounge chairs, rattan seating, and lit umbrellas',
            ],
        };
        $showCatalog = $availability === null;
        $showInclusives = in_array($listingType, ['rooms', 'packages'], true);
        $listingInclusives = $showInclusives
            ? \App\Support\ListingInclusives::for($listingType, $facilities)
            : [];
        $breadcrumbCurrent = $isServicesHero ? 'Services' : $title;
        $listingHeroAttr = isset($listingHero['src'])
            ? 'style="background-image: url(\''.e($listingHero['src']).'\')"'
            : '';
    @endphp

    <section class="listing-hero" {!! $listingHeroAttr !!}>
        @if($listingHero)
            <span class="sr-only">{{ $listingHero['alt'] }}</span>
        @endif
        <div class="listing-hero-shade" aria-hidden="true"></div>

        <div class="relative z-10 flex flex-1 flex-col justify-center w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 pb-12 sm:pt-28 sm:pb-16 text-white">
            <div class="max-w-xl animate-hero-copy">
                <nav aria-label="Breadcrumb" class="text-sm text-white/75">
                    <ol class="flex flex-wrap items-center gap-2">
                        <li>
                            <a href="{{ route('home') }}" class="hover:text-white transition">Home</a>
                        </li>
                        <li aria-hidden="true">/</li>
                        <li class="text-white/90">{{ $breadcrumbCurrent }}</li>
                    </ol>
                </nav>

                @if($isServicesHero)
                    <p class="mt-6 text-[11px] sm:text-xs font-semibold uppercase tracking-[0.28em] text-white/85">
                        Stay &middot; Celebrate &middot; Unwind
                    </p>
                @endif

                <h1 class="mt-3 font-heading text-4xl sm:text-5xl md:text-[3.5rem] font-semibold leading-[1.08] tracking-tight drop-shadow">
                    {{ $heroHeadline }}
                </h1>

                @if(filled($heroSupport))
                    <p class="mt-5 text-sm sm:text-base text-white/95 max-w-lg leading-relaxed">
                        {{ $heroSupport }}
                    </p>
                @endif

                @if($hasStayQuery)
                    <p class="mt-6 inline-flex flex-wrap items-center gap-2 rounded-full bg-white/90 px-4 py-2 text-sm font-medium text-primary">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        Checking
                        @if(filled($checkIn) && filled($checkOut))
                            {{ \Illuminate\Support\Carbon::parse($checkIn)->format('M j') }} &ndash; {{ \Illuminate\Support\Carbon::parse($checkOut)->format('M j, Y') }}
                        @elseif(filled($checkIn))
                            from {{ \Illuminate\Support\Carbon::parse($checkIn)->format('M j, Y') }}
                        @endif
                        @if(filled($guests))
                            for {{ (int) $guests }} {{ \Illuminate\Support\Str::plural('guest', (int) $guests) }}
                        @endif
                    </p>
                @endif
            </div>
        </div>
    </section>

    <div class="listing-toolbar">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex flex-wrap gap-2" role="navigation" aria-label="Space type">
                @foreach($tabs as $tab)
                    <a
                        href="{{ route($tab['route'], $stayQuery) }}"
                        class="listing-tab"
                        @if($listingType === $tab['key']) aria-current="page" @endif
                    >{{ $tab['label'] }}</a>
                @endforeach
            </div>

            @if($showCatalog && $listingType !== 'pools')
                <form method="GET" action="{{ url()->current() }}" class="flex flex-wrap items-center gap-x-5 gap-y-3">
                    @foreach($stayQuery as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach

                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-text-muted">{{ $countLabel }}</p>

                    <div class="flex items-center gap-2">
                        <label class="text-sm text-text-muted whitespace-nowrap" for="listing-capacity">Min. capacity</label>
                        <select id="listing-capacity" name="min_capacity" onchange="this.form.submit()" class="listing-toolbar-select">
                            @foreach($capacityOptions as $value => $label)
                                <option value="{{ $value ?: '' }}" @selected((int) $minCapacity === (int) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="text-sm text-text-muted" for="listing-sort">Sort</label>
                        <select id="listing-sort" name="sort" onchange="this.form.submit()" class="listing-toolbar-select">
                            <option value="price-asc" @selected($sort === 'price-asc')>Price: Low to High</option>
                            <option value="price-desc" @selected($sort === 'price-desc')>Price: High to Low</option>
                            <option value="capacity" @selected($sort === 'capacity')>Largest Capacity</option>
                            <option value="recommended" @selected($sort === 'recommended')>Recommended</option>
                        </select>
                    </div>
                </form>
            @elseif($showCatalog)
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-text-muted">{{ $countLabel }}</p>
            @endif
        </div>
    </div>

    <div @class(['max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10', 'pb-10' => $showInclusives, 'pb-24' => ! $showInclusives]) x-reveal>
        @if($availability || $hasStayQuery)
            <div class="mb-8">
                <x-availability-bar variant="inline" submit-label="Update dates" />
            </div>
        @endif

        @if($availability)
            <x-availability-results :search="$availability" />
        @endif

        @if($showCatalog)
            @if($listingType === 'pools')
                @if($pools->count() > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                        @foreach($pools as $pool)
                            <article class="overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
                                @if($pool->imageUrl())
                                    <img src="{{ $pool->imageUrl() }}" alt="{{ $pool->name }}" class="h-48 w-full object-cover">
                                @endif
                                <div class="p-6">
                                    <div class="flex items-start justify-between gap-3">
                                        <h3 class="font-heading text-2xl font-semibold text-text">{{ $pool->name }}</h3>
                                        @if($pool->is_package_only)
                                            <span class="rounded-full bg-warning-light px-2.5 py-1 text-xs font-semibold text-warning">Package only</span>
                                        @endif
                                    </div>
                                    <p class="mt-3 text-text-muted">{{ $pool->description }}</p>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            @elseif($listingType === 'packages')
                @if($packageFamilies->isNotEmpty() || $ungroupedPackages->isNotEmpty())
                    @foreach(\App\Enums\PackageFamily::cases() as $family)
                        @continue(! $packageFamilies->has($family->value))
                        @php $group = $packageFamilies->get($family->value); @endphp
                        <section @class(['mb-12' => ! $loop->last || $ungroupedPackages->isNotEmpty()]) aria-labelledby="package-family-{{ $loop->index }}">
                            <div class="mb-5">
                                <h2 id="package-family-{{ $loop->index }}" class="font-heading text-2xl font-semibold text-text">{{ $family->label() }}</h2>
                                <p class="mt-1 text-sm text-text-muted">{{ $group->count() }} {{ \Illuminate\Support\Str::plural('variant', $group->count()) }} in this package. Photos and rates stay with each stay type.</p>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                                @foreach($group as $facility)
                                    <x-facility-card :facility="$facility" />
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                    @if($ungroupedPackages->isNotEmpty())
                        <section>
                            <div class="mb-5">
                                <h2 class="font-heading text-2xl font-semibold text-text">Other packages</h2>
                                <p class="mt-1 text-sm text-text-muted">{{ $ungroupedPackages->count() }} {{ \Illuminate\Support\Str::plural('package', $ungroupedPackages->count()) }} outside the published flyer catalog.</p>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                                @foreach($ungroupedPackages as $facility)
                                    <x-facility-card :facility="$facility" />
                                @endforeach
                            </div>
                        </section>
                    @endif
                @else
                    <div class="py-12">
                        <x-empty-state
                            icon="home"
                            title="{{ $minCapacity > 0 ? 'No spaces match that filter' : 'No packages currently available' }}"
                            description="{{ $minCapacity > 0 ? 'Try lowering the minimum capacity or browse another category.' : 'Check back later for available packages.' }}"
                        />
                    </div>
                @endif
            @else
                @if($facilities->count() > 0)
                    @foreach($groupedFacilities as $category => $group)
                        <section @class(['mb-12' => ! $loop->last]) @if($listingType === 'rooms') aria-labelledby="room-category-{{ $loop->index }}" @endif>
                            @if($listingType === 'rooms')
                                <div class="mb-5">
                                    <h2 id="room-category-{{ $loop->index }}" class="font-heading text-2xl font-semibold text-text">{{ $category }}</h2>
                                    <p class="mt-1 text-sm text-text-muted">{{ $group->count() }} {{ \Illuminate\Support\Str::plural('unit', $group->count()) }} in this category. Photos and rates stay with each room.</p>
                                </div>
                            @endif
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                                @foreach($group as $facility)
                                    <x-facility-card :facility="$facility" />
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                @else
                    <div class="py-12">
                        <x-empty-state
                            icon="home"
                            title="{{ $minCapacity > 0 ? 'No spaces match that filter' : 'No '.strtolower($title).' currently available' }}"
                            description="{{ $minCapacity > 0 ? 'Try lowering the minimum capacity or browse another category.' : 'Check back later for available '.strtolower($title).'.' }}"
                        />
                    </div>
                @endif
            @endif
        @endif
    </div>

    @if($showInclusives)
        <div class="bg-surface pt-10 pb-24">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <x-listing-inclusives :items="$listingInclusives" />
            </div>
        </div>
    @endif
</x-customer-layout>
