<x-customer-layout>
    <section class="bg-gradient-to-br from-primary via-primary-dark to-emerald-950 text-white pt-28 pb-16">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-white/75">Swim, gather, and dine</p>
            <h1 class="mt-3 font-heading text-4xl sm:text-6xl font-semibold">Resort &amp; Pools</h1>
            <p class="mt-5 max-w-3xl text-lg text-white/85">Choose a swimming session, reserve your preferred cottage, and review the pool rules and Café &amp; Restobar menu before you visit.</p>
        </div>
    </section>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-14 space-y-20">
        <section aria-labelledby="pools-heading">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-wider text-primary">Public and private swimming</p>
                <h2 id="pools-heading" class="mt-2 font-heading text-3xl sm:text-4xl font-semibold text-text">Choose the pool experience that fits your visit</h2>
            </div>
            <div class="mt-8 grid gap-6 md:grid-cols-3">
                @foreach($pools as $pool)
                    <article class="overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
                        @if($pool->imageUrl())
                            <img src="{{ $pool->imageUrl() }}" alt="{{ $pool->name }}" class="h-48 w-full object-cover">
                        @else
                            <div class="h-48 bg-gradient-to-br from-sky-100 to-emerald-100 flex items-center justify-center text-primary">
                                <svg class="h-16 w-16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 15c1.5-1 2.5-1 4 0s2.5 1 4 0 2.5-1 4 0 2.5 1 4 0M4 19c1.5-1 2.5-1 4 0s2.5 1 4 0 2.5-1 4 0 2.5 1 4 0M12 11V4m0 0 3 3m-3-3L9 7"/></svg>
                            </div>
                        @endif
                        <div class="p-6">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="font-heading text-2xl font-semibold text-text">{{ $pool->name }}</h3>
                                @if($pool->is_package_only)<span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900">Package only</span>@endif
                            </div>
                            <p class="mt-3 text-text-muted">{{ $pool->description }}</p>
                            @if($pool->audience_note)<p class="mt-3 text-sm font-medium text-text">{{ $pool->audience_note }}</p>@endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section id="cottage-reservations" aria-labelledby="sessions-heading" class="scroll-mt-24">
            <div class="grid gap-8 lg:grid-cols-[1fr_1.1fr]">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wider text-primary">Plan your swim</p>
                    <h2 id="sessions-heading" class="mt-2 font-heading text-3xl sm:text-4xl font-semibold text-text">Entrance is paid when you arrive</h2>
                    <p class="mt-4 text-text-muted">Cottage fees are shown per swimming session. Pool entrance is separate and is not included in the online cottage total.</p>
                    <div class="mt-6 space-y-4">
                        @foreach($sessions as $session)
                            <article class="rounded-2xl border border-border bg-surface p-5">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-lg font-semibold text-text">{{ $session->name }}</h3>
                                        <p class="text-sm text-text-muted">{{ \Carbon\Carbon::parse($session->starts_at)->format('g:i A') }}–{{ \Carbon\Carbon::parse($session->ends_at)->format('g:i A') }}</p>
                                    </div>
                                    <span class="font-heading text-2xl font-semibold text-primary">₱{{ number_format((float) $session->adult_entrance_fee) }}</span>
                                </div>
                                <p class="mt-3 text-sm text-text-muted">Adult entrance · pay at the entrance</p>
                                <p class="mt-1 text-sm font-medium text-text">Cottage reservation: ₱{{ number_format((float) $session->cottage_fee) }} per session</p>
                                @if($session->children_allowed && $session->child_entrance_fee !== null)
                                    <p class="mt-1 text-sm text-text">{{ $session->child_qualifier }}: ₱{{ number_format((float) $session->child_entrance_fee) }}</p>
                                @elseif(! $session->children_allowed)
                                    <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-900">Children are not allowed to swim during this session.</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
            </div>

                <div class="rounded-3xl border border-border bg-surface p-6 sm:p-8 shadow-lg" x-data="cottageFinder()">
                    <h2 class="font-heading text-3xl font-semibold text-text">Reserve a cottage</h2>
                    <p class="mt-2 text-text-muted">Select a date and session to see exactly which cottages are available.</p>
                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <div>
                            <x-input-label for="cottage-date" value="Visit date" />
                            <x-text-input id="cottage-date" type="date" x-model="date" @change="checked = false" min="{{ now()->toDateString() }}" class="mt-2 block w-full" />
                        </div>
                        <div>
                            <x-input-label for="cottage-session" value="Swimming session" />
                            <select id="cottage-session" x-model="session" @change="checked = false" class="mt-2 block w-full rounded-lg border-border focus:border-primary focus:ring-primary">
                                @foreach($sessions as $session)
                                    <option value="{{ $session->slug }}">{{ $session->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <button type="button" @click="check" :disabled="loading" class="mt-5 inline-flex w-full justify-center rounded-lg bg-primary px-5 py-3 font-semibold text-white hover:bg-primary-hover disabled:opacity-60">
                        <span x-text="loading ? 'Checking…' : 'Show available cottages'"></span>
                    </button>
                    <p x-show="error" x-cloak class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800" x-text="error"></p>
                    <div x-show="checked && !error" x-cloak class="mt-6">
                        <p class="text-sm font-semibold text-text"><span x-text="cottages.length"></span> cottages available</p>
                        <div class="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <template x-for="cottage in cottages" :key="cottage.id">
                                <a :href="cottage.booking_url + '&guests=' + guests" :aria-label="'Reserve ' + cottage.name" class="rounded-xl border border-border px-3 py-3 text-center text-sm font-semibold text-text hover:border-primary hover:text-primary" x-text="cottage.name"></a>
                            </template>
                        </div>
                        <p x-show="cottages.length === 0" class="mt-3 text-sm text-text-muted">No cottage is open for this session. Try another date or session.</p>
                    </div>
                </div>
            </div>
        </section>

        <section aria-labelledby="rules-heading">
            <details class="rounded-2xl border border-border bg-surface p-6 group">
                <summary id="rules-heading" class="cursor-pointer list-none flex items-center justify-between gap-4 font-heading text-2xl font-semibold text-text">
                    Pool rules to know before you visit
                    <span class="text-primary group-open:rotate-45 transition text-3xl" aria-hidden="true">+</span>
                </summary>
                <ol class="mt-6 grid gap-3 sm:grid-cols-2 list-decimal pl-6 text-text-muted">
                    @foreach($rules as $rule)<li class="pl-1">{{ $rule->text }}</li>@endforeach
                </ol>
            </details>
        </section>

        <section aria-labelledby="menu-heading">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wider text-primary">Café &amp; Restobar</p>
                    <h2 id="menu-heading" class="mt-2 font-heading text-3xl sm:text-4xl font-semibold text-text">Food and room service</h2>
                </div>
                <div class="text-sm text-text-muted sm:text-right">
                    <p>{{ $settings['cafe_hours'] ?? '' }}</p>
                    <p>Reservations and room service via Viber: <strong class="text-text">{{ $settings['cafe_viber'] ?? '' }}</strong></p>
                </div>
            </div>
            <div class="mt-8 grid gap-6 lg:grid-cols-3">
                @foreach($menuCategories as $category)
                    <article class="rounded-2xl border border-border bg-surface p-6">
                        <h3 class="font-heading text-2xl font-semibold text-text">{{ $category->name }}</h3>
                        @if($category->subtitle)<p class="mt-1 text-sm text-text-muted">{{ $category->subtitle }}</p>@endif
                        <dl class="mt-5 space-y-4">
                            @foreach($category->items as $item)
                                <div class="flex justify-between gap-4 border-b border-border/70 pb-3 last:border-0">
                                    <div><dt class="font-medium text-text">{{ $item->name }}</dt>@if($item->note)<dd class="text-xs text-text-muted">{{ $item->note }}</dd>@endif</div>
                                    <dd class="font-semibold text-primary">₱{{ number_format((float) $item->price) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </article>
                @endforeach
            </div>
            <div class="mt-6 rounded-2xl bg-amber-50 border border-amber-200 p-5 text-sm text-amber-950">
                <p>{{ $settings['cafe_outside_food_notice'] ?? '' }}</p>
                <p class="mt-1">Listed menu prices are subject to a {{ $settings['cafe_service_charge'] ?? '3' }}% service charge.</p>
            </div>
        </section>

        <section aria-labelledby="fees-heading">
            <h2 id="fees-heading" class="font-heading text-3xl sm:text-4xl font-semibold text-text">Corkage and other fees</h2>
            <p class="mt-3 text-text-muted">Fees are charged per listed unit. Alcohol and glass remain prohibited in or around the pool.</p>
            <div class="mt-6 overflow-hidden rounded-2xl border border-border bg-surface">
                @foreach($fees as $fee)
                    <div class="grid gap-2 border-b border-border p-4 last:border-0 sm:grid-cols-[1fr_auto] sm:items-center">
                        <div><h3 class="font-semibold text-text">{{ $fee->name }}</h3>@if($fee->description)<p class="text-sm text-text-muted">{{ $fee->description }}</p>@endif</div>
                        <p class="font-heading text-xl font-semibold text-primary">₱{{ number_format((float) $fee->amount) }} <span class="text-xs font-normal text-text-muted">{{ $fee->unit }}</span></p>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => Alpine.data('cottageFinder', () => ({
                date: @js(request('date', request('check_in', now()->addDay()->toDateString()))),
                guests: @js(max(1, (int) request('guests', 1))),
                session: @js($sessions->firstWhere('slug', request('session'))?->slug ?? $sessions->first()?->slug ?? ''), cottages: [], loading: false, checked: false, error: '',
                async check() {
                    this.loading = true; this.checked = false; this.error = '';
                    const date = this.date, session = this.session;
                    try {
                        const url = new URL(@js(route('resort.cottages.availability')), window.location.origin);
                        url.searchParams.set('date', this.date); url.searchParams.set('session', this.session);
                        const response = await fetch(url, { headers: { Accept: 'application/json' } });
                        const data = await response.json();
                        if (!response.ok) throw new Error(data.message || 'Unable to check cottage availability.');
                        if (date !== this.date || session !== this.session) return;
                        this.cottages = data.data.filter(cottage => cottage.available); this.checked = true;
                    } catch (error) { this.error = error.message; } finally { this.loading = false; }
                }
            })));
        </script>
    @endpush
</x-customer-layout>
