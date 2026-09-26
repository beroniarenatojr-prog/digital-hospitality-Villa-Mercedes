<x-customer-layout>
    <x-slot name="title">Unified Resort Booking Workspace · {{ config('resort.name') }}</x-slot>

    <div class="bg-primary text-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-7 sm:pt-12 sm:pb-10">
            <nav class="flex flex-wrap items-center gap-2 text-xs text-white/60 mb-5" aria-label="Breadcrumb">
                <a class="hover:text-white transition-colors" href="{{ route('home') }}">Home</a>
                <span>/</span>
                <span class="text-white font-medium">Unified Booking Workspace</span>
            </nav>
            <h1 class="mt-3 font-heading text-3xl sm:text-4xl lg:text-5xl font-semibold tracking-tight">Reserve your stay &amp; services</h1>
            <p class="mt-3 text-white/75 max-w-2xl text-base sm:text-lg">Select rooms, cottages, function halls, and resort packages in one combined request. Live availability and estimates are verified in real time before one easy approval decision.</p>
        </div>
    </div>

    @php
        $workspaceConfig = [
            'facilities' => $bookableFacilities->map(fn($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'type' => $f->facilityType?->slug,
                'type_label' => $f->facilityType?->name ?? 'Space',
                'max_capacity' => $f->maxGuestCount(),
                'included_capacity' => $f->includedGuestCount(),
                'pax_tiers' => $f->paxTiers(),
                'is_cottage' => $f->isCottage(),
                'is_hall' => $f->isFunctionHall(),
                'is_room' => $f->isRoom(),
                'is_package' => $f->isPackage(),
                'description' => $f->description,
                'photos' => collect([$f->cover_image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($f->cover_image_path) : null])
                    ->merge($f->galleryImages->where('is_active', true)->map(fn ($image) => $image->url()))->filter()->unique()->values(),
                'amenities' => $f->amenities->pluck('name'),
                'rate' => ($rate = $f->rateForPaxTier(null)) ? ['amount' => $rate->amount, 'unit' => $rate->unit] : null,
                'schedule' => app(\App\Services\ReservationSchedule::class)->configuration($f),
            ]),
            'sessions' => $swimmingSessions->map(fn($s) => [
                'id' => $s->id,
                'slug' => $s->slug,
                'name' => $s->name,
                'start_time' => substr($s->starts_at, 0, 5),
                'end_time' => substr($s->ends_at, 0, 5),
            ]),
            'initialLines' => $initialLines,
            'initialCategory' => request()->query('type', 'All'),
            'guestProfile' => $guestProfile,
            'availabilityUrl' => route('customer.grouped-booking.availability'),
            'intentUrl' => route('reservation.intent'),
            'confirmUrl' => route('customer.grouped-booking.store'),
            'loginUrl' => route('login'),
            'registerUrl' => route('register'),
            'isAuthenticated' => auth()->check(),
            'resumeAfterAuth' => request()->boolean('resume'),
            'csrfToken' => csrf_token(),
            'today' => \App\Support\ResortTime::today()->toDateString(),
        ];
    @endphp

    <div x-data="unifiedBookingWorkspace()" data-unified-booking-workspace class="bg-background min-h-[600px] py-8 sm:py-12 pb-32">
        <div x-show="detailFacility" x-cloak @keydown.escape.window="closeDetails()" @keydown.tab="trapDetailFocus($event)" @popstate.window="closeDetails(false)" class="fixed inset-0 z-50" role="presentation">
            <div class="absolute inset-0 bg-black/60" @click="closeDetails()"></div>
            <section role="dialog" aria-modal="true" aria-labelledby="service-detail-title" class="absolute inset-y-0 right-0 w-full sm:max-w-xl overflow-y-auto bg-surface shadow-2xl p-6" x-ref="detailSheet" tabindex="-1">
                <div class="flex items-start justify-between gap-4"><h2 id="service-detail-title" class="font-heading text-2xl font-semibold" x-text="detailFacility?.name"></h2><button type="button" @click="closeDetails()" class="rounded-lg border px-3 py-2" aria-label="Close service details">Close</button></div>
                <template x-if="detailFacility?.photos.length"><div class="mt-5 flex gap-2 overflow-x-auto"><template x-for="photo in detailFacility.photos" :key="photo"><img :src="photo" :alt="detailFacility.name" class="h-48 w-64 shrink-0 rounded-xl object-cover"></template></div></template>
                <p class="mt-5 text-text-muted" x-text="detailFacility?.description || 'Description not yet published.'"></p>
                <h3 class="mt-6 font-semibold">Amenities and inclusions</h3><p class="text-sm text-text-muted" x-text="detailFacility?.amenities.length ? detailFacility.amenities.join(' · ') : 'No inclusions published.'"></p>
                <p class="mt-4 text-sm" x-text="detailFacility ? 'Capacity: ' + detailFacility.included_capacity + ' included, up to ' + detailFacility.max_capacity + ' guests' : ''"></p>
                <p class="mt-2 font-semibold text-primary" x-text="detailFacility?.rate ? formatPesos(detailFacility.rate.amount) + ' ' + detailFacility.rate.unit : 'Rate available on request'"></p>
                <p class="mt-2 text-sm text-text-muted" x-text="detailFacility ? 'Schedule: ' + detailFacility.schedule.mode : ''"></p>
                <p class="mt-5 text-sm text-text-muted">Select this service to set its schedule and check live availability in your booking.</p>
                <button type="button" @click="addLine(detailFacility.id); closeDetails()" :disabled="lines.some(line => Number(line.facility_id) === detailFacility?.id)" class="mt-5 w-full rounded-xl bg-primary px-4 py-3 font-semibold text-white disabled:opacity-50">Add to booking</button>
            </section>
        </div>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            
            {{-- Stage Progress Indicator (1. Services -> 2. Guest Details -> 3. Review) --}}
            <nav aria-label="Booking steps" class="mb-8">
                <ol class="flex items-center justify-between max-w-xl mx-auto border border-border bg-surface rounded-xl p-3 shadow-sm">
                    <li class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm"
                              :class="step === 1 ? 'bg-primary text-white ring-4 ring-primary/20' : (step > 1 ? 'bg-success text-white' : 'bg-muted text-text-muted')">
                            <template x-if="step > 1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            </template>
                            <template x-if="step <= 1"><span>1</span></template>
                        </span>
                        <span class="text-sm font-semibold" :class="step === 1 ? 'text-primary' : 'text-text-muted'">Services</span>
                    </li>
                    <span class="w-8 sm:w-16 h-0.5 bg-border"></span>
                    <li class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm"
                              :class="step === 2 ? 'bg-primary text-white ring-4 ring-primary/20' : (step > 2 ? 'bg-success text-white' : 'bg-muted text-text-muted')">
                            <template x-if="step > 2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            </template>
                            <template x-if="step <= 2"><span>2</span></template>
                        </span>
                        <span class="text-sm font-semibold" :class="step === 2 ? 'text-primary' : 'text-text-muted'">Guest details</span>
                    </li>
                    <span class="w-8 sm:w-16 h-0.5 bg-border"></span>
                    <li class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm"
                              :class="step === 3 ? 'bg-primary text-white ring-4 ring-primary/20' : 'bg-muted text-text-muted'">
                            3
                        </span>
                        <span class="text-sm font-semibold" :class="step === 3 ? 'text-primary' : 'text-text-muted'">Review</span>
                    </li>
                </ol>
            </nav>

            {{-- Error Summary --}}
            <div x-show="errorMessage" x-cloak class="mb-6 rounded-xl border border-danger bg-danger-light p-4 text-danger flex items-start gap-3" role="alert">
                <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <div class="flex-1">
                    <p class="font-semibold text-sm">Please check your inputs</p>
                    <p class="text-sm mt-0.5" x-text="errorMessage"></p>
                </div>
                <button type="button" @click="errorMessage = ''" class="text-danger hover:opacity-75" aria-label="Dismiss">&times;</button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-[1.55fr_1fr] gap-8 items-start">
                
                {{-- Left Column: Step Content --}}
                <div class="space-y-6">

                    {{-- STEP 1: Services Picker & Controls --}}
                    <div x-show="step === 1" class="space-y-6">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <h2 class="font-heading text-2xl font-semibold text-text">Explore resort services</h2>
                                <p class="text-sm text-text-muted">Choose a service, then set its published schedule to check availability.</p>
                            </div>
                        </div>

                        <div class="flex gap-2 overflow-x-auto pb-2" aria-label="Service categories">
                            <template x-for="category in ['All', 'room', 'cottage', 'function_hall', 'resort_package', 'other']" :key="category">
                                <button type="button" @click="categoryFilter = category" :aria-pressed="categoryFilter === category" class="shrink-0 rounded-full px-4 py-2 text-sm font-medium border border-border focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary" :class="categoryFilter === category ? 'bg-primary text-white' : 'bg-surface text-text'" x-text="category === 'All' ? 'All services' : category.replaceAll('_', ' ')"></button>
                            </template>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <template x-for="fac in filteredFacilities" :key="fac.id">
                                <article class="overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
                                    <template x-if="fac.photos.length"><img :src="fac.photos[0]" :alt="fac.name" class="h-44 w-full object-cover" loading="lazy"></template>
                                    <template x-if="!fac.photos.length"><div class="h-44 bg-muted flex items-center justify-center text-sm text-text-muted">Photo not available</div></template>
                                    <div class="p-4 space-y-2">
                                        <p class="text-xs uppercase tracking-wide text-text-muted" x-text="fac.type_label"></p>
                                        <h3 class="font-heading text-lg font-semibold" x-text="fac.name"></h3>
                                        <p class="text-sm text-text-muted line-clamp-2" x-text="fac.description || 'Description not yet published.'"></p>
                                        <p class="text-xs text-text-muted" x-text="'Up to ' + fac.max_capacity + ' guests · ' + fac.schedule.mode"></p>
                                        <p class="text-xs text-text-muted" x-text="fac.amenities.slice(0, 3).join(' · ')"></p>
                                        <p class="font-semibold text-primary" x-text="fac.rate ? formatPesos(fac.rate.amount) + ' ' + fac.rate.unit : 'Rate available on request'"></p>
                                        <p class="text-xs text-text-muted">Choose dates to check availability</p>
                                        <div class="flex gap-2 pt-1">
                                            <button type="button" @click="openDetails(fac, $event.currentTarget)" class="rounded-lg border border-primary px-3 py-2 text-sm text-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary">View details</button>
                                            <button type="button" @click="addLine(fac.id)" class="rounded-lg bg-primary px-3 py-2 text-sm text-white disabled:opacity-50" :disabled="lines.some(line => Number(line.facility_id) === fac.id)">Add to booking</button>
                                        </div>
                                    </div>
                                </article>
                            </template>
                        </div>
                        <h2 id="selected-services" class="font-heading text-xl font-semibold pt-4">Your selected services</h2>

                        <template x-if="lines.length === 0">
                            <div class="rounded-2xl border border-dashed border-border bg-surface p-8 text-center">
                                <p class="text-text-muted">No resort services selected yet.</p>
                                <p class="mt-2 text-sm text-text-muted">Use Add to booking on a service card above.</p>
                            </div>
                        </template>

                        {{-- Service Line Cards --}}
                        <div class="space-y-4">
                            <template x-for="(line, index) in lines" :key="index">
                                <div class="rounded-2xl border border-border bg-surface p-5 sm:p-6 shadow-sm relative transition-all"
                                     :class="lineStatusClass(index)">
                                    <div class="flex items-start justify-between gap-4 mb-4">
                                        <div class="flex items-center gap-2">
                                            <span class="w-6 h-6 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center" x-text="index + 1"></span>
                                            <h3 class="font-heading text-lg font-semibold text-text" x-text="facilityName(line.facility_id)"></h3>
                                        </div>
                                        <button type="button" @click="removeLine(index)" class="text-text-muted hover:text-danger text-sm font-medium flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            Remove
                                        </button>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <p class="sm:col-span-2 text-sm text-text-muted">To change the service, remove this line and choose another catalog card.</p>

                                        {{-- Conditional Controls for Cottages (Visit Date + Session) --}}
                                        <template x-if="isCottage(line.facility_id)">
                                            <div class="contents">
                                                <div>
                                                    <label class="block text-xs font-semibold uppercase tracking-wide text-text-muted mb-1">Visit date</label>
                                                    <input type="date" x-model="line.date" @change="onInputChanged(index)" :min="today" class="w-full rounded-lg border-border focus:border-primary focus:ring-primary">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold uppercase tracking-wide text-text-muted mb-1">Swimming session</label>
                                                    <select x-model.number="line.swimming_session_id" @change="onInputChanged(index)" class="w-full rounded-lg border-border focus:border-primary focus:ring-primary">
                                                        <option value="">Choose a session…</option>
                                                        @foreach($swimmingSessions as $session)
                                                            <option value="{{ $session->id }}">{{ $session->name }} ({{ \Carbon\Carbon::parse($session->starts_at)->format('g:i A') }}–{{ \Carbon\Carbon::parse($session->ends_at)->format('g:i A') }})</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </template>

                                        {{-- Published schedule controls; the server validates the resulting interval. --}}
                                        <template x-if="!isCottage(line.facility_id)">
                                            <div class="contents">
                                                <template x-if="isRoom(line.facility_id)">
                                                    <div class="contents">
                                                        <div><label class="block text-xs font-semibold uppercase tracking-wide text-text-muted mb-1">Arrival date · 2:00 PM</label><input type="date" :value="datePart(line.check_in)" @change="line.check_in = $event.target.value + ' 14:00'; onInputChanged(index)" :min="today" class="w-full rounded-lg border-border"></div>
                                                        <div><label class="block text-xs font-semibold uppercase tracking-wide text-text-muted mb-1">Departure date · 12:00 PM</label><input type="date" :value="datePart(line.check_out)" @change="line.check_out = $event.target.value + ' 12:00'; onInputChanged(index)" :min="datePart(line.check_in) || today" class="w-full rounded-lg border-border"></div>
                                                    </div>
                                                </template>
                                                <template x-if="!isRoom(line.facility_id) && facility(line.facility_id)?.schedule.mode === 'package'">
                                                    <div><label class="block text-xs font-semibold uppercase tracking-wide text-text-muted mb-1">Package arrival date</label><input type="date" :value="datePart(line.check_in)" @change="setPackageDate(line, $event.target.value); onInputChanged(index)" :min="today" class="w-full rounded-lg border-border"><p class="mt-1 text-xs text-text-muted" x-text="facility(line.facility_id)?.schedule.label || 'Published package schedule'"></p></div>
                                                </template>
                                                <template x-if="!isRoom(line.facility_id) && isHall(line.facility_id)">
                                                    <div class="contents">
                                                        <div><label class="block text-xs font-semibold uppercase tracking-wide text-text-muted mb-1">Event date</label><input type="date" :value="datePart(line.check_in)" @change="setHallSlot(line, $event.target.value, line.hall_slot || (facility(line.facility_id)?.schedule.mode === 'opal' ? '09:00|17:00' : '09:00|13:00')); onInputChanged(index)" :min="today" class="w-full rounded-lg border-border"></div>
                                                        <div><label class="block text-xs font-semibold uppercase tracking-wide text-text-muted mb-1">Published time block</label><select x-model="line.hall_slot" @change="setHallSlot(line, datePart(line.check_in), line.hall_slot); onInputChanged(index)" class="w-full rounded-lg border-border"><template x-if="facility(line.facility_id)?.schedule.mode === 'opal'"><optgroup label="Opal Hall"><option value="09:00|17:00">9:00 AM–5:00 PM</option><option value="17:00|22:00">5:00 PM–10:00 PM</option></optgroup></template><template x-if="facility(line.facility_id)?.schedule.mode !== 'opal'"><optgroup label="Standard four-hour blocks"><option value="09:00|13:00">9:00 AM–1:00 PM</option><option value="13:00|17:00">1:00 PM–5:00 PM</option><option value="17:00|21:00">5:00 PM–9:00 PM</option></optgroup></template></select></div>
                                                    </div>
                                                </template>
                                                <template x-if="!isRoom(line.facility_id) && !isHall(line.facility_id) && facility(line.facility_id)?.schedule.mode === 'other'"><p class="text-sm text-text-muted">This service has no published online schedule. Please contact the resort.</p></template>
                                            </div>
                                        </template>

                                        {{-- Guests & Capacity --}}
                                        <div>
                                            <label class="block text-xs font-semibold uppercase tracking-wide text-text-muted mb-1">Party / Guest count</label>
                                            <input type="number" x-model.number="line.guest_count" @change="onInputChanged(index)" min="1" :max="maxCapacity(line.facility_id)" class="w-full rounded-lg border-border focus:border-primary focus:ring-primary">
                                            <p class="text-xs text-text-muted mt-1">Included: <span x-text="includedCapacity(line.facility_id)"></span> · Max: <span x-text="maxCapacity(line.facility_id)"></span></p>
                                        </div>

                                        {{-- Early Check-in Hours for Rooms --}}
                                        <template x-if="isRoom(line.facility_id)">
                                            <div>
                                                <label class="block text-xs font-semibold uppercase tracking-wide text-text-muted mb-1">Early arrival (hours)</label>
                                                <select x-model.number="line.early_check_in_hours" @change="onInputChanged(index)" class="w-full rounded-lg border-border focus:border-primary focus:ring-primary">
                                                    <option value="0">Standard 2:00 PM</option>
                                                    <option value="1">1 hour earlier (1:00 PM)</option>
                                                    <option value="2">2 hours earlier (12:00 PM)</option>
                                                </select>
                                            </div>
                                        </template>
                                    </div>

                                    {{-- Line Result Banner --}}
                                    <div class="mt-4 pt-3 border-t border-border flex items-center justify-between text-sm">
                                        <div class="flex items-center gap-2">
                                            <template x-if="lineStatus(index) === 'checking'">
                                                <span class="text-text-muted flex items-center gap-1.5"><svg class="animate-spin w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Checking availability…</span>
                                            </template>
                                            <template x-if="lineStatus(index) === 'available'">
                                                <span class="text-success font-semibold flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Available</span>
                                            </template>
                                            <template x-if="lineStatus(index) === 'unavailable'">
                                                <span class="text-danger font-semibold" x-text="lineReason(index)"></span>
                                            </template>
                                            <template x-if="lineStatus(index) === 'stale'">
                                                <span class="text-warning font-medium">Inputs changed · Recheck required</span>
                                            </template>
                                        </div>
                                        <div class="font-heading font-semibold text-base text-text">
                                            <template x-if="lineEstimate(index) !== null">
                                                <span x-text="formatPesos(lineEstimate(index))"></span>
                                            </template>
                                            <template x-if="lineEstimate(index) === null">
                                                <span class="text-text-muted text-xs">Awaiting quote</span>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- STEP 2: Guest Details (Auth Handoff or Account Sheet) --}}
                    <div x-show="step === 2" class="space-y-6">
                        <div class="bg-surface rounded-2xl border border-border p-6 shadow-sm">
                            <h2 class="font-heading text-2xl font-semibold text-text">Guest Information Sheet</h2>
                            <p class="mt-1 text-sm text-text-muted">Villa Mercedes requires registered guest identification for booking verification.</p>

                            <template x-if="!config.isAuthenticated">
                                <div class="mt-6 rounded-xl border border-primary/20 bg-primary-light/30 p-5">
                                    <h3 class="font-heading text-lg font-semibold text-text">Sign in or create an account</h3>
                                    <p class="mt-1 text-sm text-text-muted">Your selected services and dates will be preserved.</p>
                                    <div class="mt-4 flex flex-wrap gap-3">
                                        <button type="button" @click="saveAndSignIn()" class="px-5 py-2.5 rounded-lg bg-primary text-white font-semibold hover:bg-primary-hover">Sign in</button>
                                        <button type="button" @click="saveAndRegister()" class="px-5 py-2.5 rounded-lg border border-primary text-primary font-semibold hover:bg-primary/10">Create account</button>
                                    </div>
                                </div>
                            </template>

                            <template x-if="config.isAuthenticated">
                                <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-text-muted mb-1">Full name</label>
                                        <input type="text" x-model="guest.guest_name" class="w-full rounded-lg border-border focus:border-primary focus:ring-primary" required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-text-muted mb-1">Email address</label>
                                        <input type="email" x-model="guest.guest_email" class="w-full rounded-lg border-border focus:border-primary focus:ring-primary" required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-text-muted mb-1">Phone number</label>
                                        <input type="text" x-model="guest.guest_phone" placeholder="09171234567" class="w-full rounded-lg border-border focus:border-primary focus:ring-primary" required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-text-muted mb-1">Place of origin / Address</label>
                                        <input type="text" x-model="guest.guest_place_of_origin" class="w-full rounded-lg border-border focus:border-primary focus:ring-primary" required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-text-muted mb-1">Nationality</label>
                                        <input type="text" x-model="guest.guest_nationality" class="w-full rounded-lg border-border focus:border-primary focus:ring-primary" required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-text-muted mb-1">Company / Organization (optional)</label>
                                        <input type="text" x-model="guest.guest_company" class="w-full rounded-lg border-border focus:border-primary focus:ring-primary">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-text-muted mb-1">Valid government ID type</label>
                                        <select x-model="guest.guest_valid_id" class="w-full rounded-lg border-border focus:border-primary focus:ring-primary" required>
                                            <option value="">Select ID type…</option>
                                            <option value="philsys">PhilSys National ID</option>
                                            <option value="passport">Passport</option>
                                            <option value="drivers_license">Driver's License</option>
                                            <option value="sss">SSS / UMID</option>
                                            <option value="tin">TIN ID</option>
                                            <option value="voters">Voter's ID / Certification</option>
                                            <option value="postal">Postal ID</option>
                                            <option value="prc">PRC ID</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-text-muted mb-1">Gender</label>
                                        <select x-model="guest.guest_gender" class="w-full rounded-lg border-border focus:border-primary focus:ring-primary" required>
                                            <option value="">Select gender…</option>
                                            <option value="female">Female</option>
                                            <option value="male">Male</option>
                                            <option value="non_binary">Non-binary</option>
                                            <option value="prefer_not_to_say">Prefer not to say</option>
                                        </select>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-text-muted mb-1">Special requests or arrangements (optional)</label>
                                        <textarea x-model="specialRequests" rows="3" class="w-full rounded-lg border-border focus:border-primary focus:ring-primary" placeholder="Early arrival requests, dietary preferences, event arrangements…"></textarea>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- STEP 3: Review & Terms Agreement --}}
                    <div x-show="step === 3" class="space-y-6">
                        <div class="bg-surface rounded-2xl border border-border p-6 shadow-sm">
                            <h2 class="font-heading text-2xl font-semibold text-text">Review your grouped request</h2>
                            <p class="mt-1 text-sm text-text-muted">The resort reviews every combined request as one unified decision. You will pay the 50% deposit after staff review and approval.</p>

                            <div class="mt-6 divide-y divide-border">
                                <template x-for="(line, index) in lines" :key="index">
                                    <div class="py-4 flex items-start justify-between gap-4">
                                        <div>
                                            <h4 class="font-heading font-semibold text-text" x-text="facilityName(line.facility_id)"></h4>
                                            <p class="text-xs text-text-muted mt-0.5" x-text="lineScheduleLabel(line)"></p>
                                            <p class="text-xs text-text-muted" x-text="line.guest_count + ' guests'"></p>
                                        </div>
                                        <div class="text-right">
                                            <span class="font-heading font-semibold text-text" x-text="formatPesos(lineEstimate(index))"></span>
                                            <button type="button" @click="step = 1" class="block text-xs text-primary hover:underline mt-1">Edit</button>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div class="mt-6 pt-4 border-t border-border bg-muted/30 rounded-xl p-4">
                                <div class="flex items-center justify-between text-base font-medium text-text">
                                    <span>Total Quoted Estimate</span>
                                    <span class="font-heading text-xl font-bold text-primary" x-text="formatPesos(totalEstimate)"></span>
                                </div>
                                <div class="flex items-center justify-between text-xs text-text-muted mt-1">
                                    <span>Required 50% Down Payment (upon approval)</span>
                                    <span class="font-semibold text-text" x-text="formatPesos(depositEstimate)"></span>
                                </div>
                            </div>

                            <div class="mt-6 space-y-3">
                                <label class="flex items-start gap-2.5 text-sm text-text cursor-pointer">
                                    <input type="checkbox" x-model="policiesAccepted" class="mt-0.5 rounded border-border text-primary focus:ring-primary" required>
                                    <span>I agree to the <a href="{{ route('terms') }}" target="_blank" class="text-primary hover:underline">Resort Policies and Rescheduling Terms</a>. I understand that bookings are non-refundable and approval is required prior to QR deposit payment.</span>
                                </label>
                            </div>
                        </div>
                    </div>

                </div>

                {{-- Right Column: Persistent Sticky Summary & Actions Bar --}}
                <div id="booking-summary" class="lg:sticky lg:top-24 space-y-5">
                    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
                        <div class="flex items-center justify-between border-b border-border pb-4">
                            <h3 class="font-heading text-lg font-semibold text-text">Summary</h3>
                            <button type="button" @click="checkAvailability()" :disabled="checking" class="text-xs font-semibold text-primary hover:underline flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': checking }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                Refresh availability
                            </button>
                        </div>

                        <div class="py-4 space-y-2 text-sm">
                            <div class="flex justify-between text-text-muted">
                                <span>Services selected</span>
                                <span class="font-semibold text-text" x-text="lines.length"></span>
                            </div>
                            <div class="flex justify-between text-text-muted">
                                <span>Last verified</span>
                                <span class="text-xs text-text" x-text="checkedAt || 'Not yet checked'"></span>
                            </div>
                            <div class="flex justify-between text-text font-medium pt-2 border-t border-border">
                                <span>Estimated Total</span>
                                <span class="font-heading text-lg font-bold text-primary" x-text="formatPesos(totalEstimate)"></span>
                            </div>
                            <div class="flex justify-between text-xs text-text-muted">
                                <span>50% Deposit on Approval</span>
                                <span class="font-medium text-text" x-text="formatPesos(depositEstimate)"></span>
                            </div>
                        </div>
                        <div x-show="lines.length && !allAvailable" x-cloak class="mb-4 rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm" role="status">
                            <p class="font-semibold">Set a schedule for every service</p>
                            <p class="mt-1" x-text="basketGuidance"></p>
                            <a href="#selected-services" class="mt-2 inline-block font-semibold text-primary underline">Go to selected services</a>
                        </div>

                        <div class="pt-2">
                            <template x-if="step === 1">
                                <button type="button" @click="continueToGuestDetails()"
                                        :disabled="checking || lines.length === 0"
                                        class="w-full py-3 rounded-xl bg-primary text-white font-semibold hover:bg-primary-hover disabled:opacity-50 transition shadow-sm">
                                    <span x-show="!checking" x-text="allAvailable ? 'Continue to Guest details →' : 'Set schedules to continue →'"></span>
                                    <span x-show="checking">Verifying availability…</span>
                                </button>
                            </template>

                            <template x-if="step === 2">
                                <div class="flex flex-col gap-2">
                                    <button type="button" @click="continueToReview()"
                                            :disabled="checking || !config.isAuthenticated"
                                            class="w-full py-3 rounded-xl bg-primary text-white font-semibold hover:bg-primary-hover disabled:opacity-50 transition shadow-sm">
                                        Review grouped request &rarr;
                                    </button>
                                    <button type="button" @click="step = 1" class="text-sm text-text-muted hover:underline text-center py-1">
                                        &larr; Back to services
                                    </button>
                                </div>
                            </template>

                            <template x-if="step === 3">
                                <div class="flex flex-col gap-2">
                                    <button type="button" @click="submitGroupBooking()"
                                            :disabled="submitting || !policiesAccepted || !allAvailable"
                                            class="w-full py-3.5 rounded-xl bg-primary text-white font-semibold hover:bg-primary-hover disabled:opacity-50 transition shadow-sm">
                                        <span x-show="!submitting">Submit for Resort Approval</span>
                                        <span x-show="submitting">Submitting request…</span>
                                    </button>
                                    <button type="button" @click="step = 2" class="text-sm text-text-muted hover:underline text-center py-1">
                                        &larr; Back to guest details
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <div x-show="lines.length && step === 1" x-cloak class="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-surface p-3 shadow-xl lg:hidden">
            <a href="#selected-services" class="flex items-center justify-between rounded-xl bg-primary px-4 py-3 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                <span><strong x-text="lines.length"></strong> <span x-text="lines.length === 1 ? 'service' : 'services'"></span> · View booking</span>
                <strong x-text="formatPesos(totalEstimate)"></strong>
            </a>
        </div>
    </div>

    <script type="application/json" id="unified-workspace-config">@json($workspaceConfig)</script>

    <script>
        document.addEventListener('alpine:init', () => {
            const config = JSON.parse(document.getElementById('unified-workspace-config').textContent);

            Alpine.data('unifiedBookingWorkspace', () => ({
                config: config,
                step: 1,
                today: config.today,
                lines: [],
                evaluation: null,
                checking: false,
                submitting: false,
                errorMessage: '',
                versionToken: null,
                checkedAt: null,
                policiesAccepted: false,
                specialRequests: '',
                categoryFilter: config.initialCategory,
                detailFacility: null,
                detailOpener: null,

                get filteredFacilities() { return this.categoryFilter === 'All' ? this.config.facilities : this.config.facilities.filter(f => f.type === this.categoryFilter); },
                openDetails(facility, opener) {
                    this.detailFacility = facility;
                    this.detailOpener = opener;
                    document.body.style.overflow = 'hidden';
                    history.pushState({ serviceDetail: facility.id }, '');
                    this.$nextTick(() => this.$refs.detailSheet?.focus());
                },
                closeDetails(updateHistory = true) {
                    if (!this.detailFacility) return;
                    this.detailFacility = null;
                    document.body.style.overflow = '';
                    this.$nextTick(() => this.detailOpener?.focus());
                    if (updateHistory && history.state?.serviceDetail) history.back();
                },
                trapDetailFocus(event) {
                    const controls = [...this.$refs.detailSheet.querySelectorAll('button:not([disabled]), a[href], input, select')];
                    if (!controls.length) return;
                    const first = controls[0], last = controls[controls.length - 1];
                    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
                },

                guest: {
                    guest_name: config.guestProfile.name || '',
                    guest_email: config.guestProfile.email || '',
                    guest_phone: config.guestProfile.phone || '',
                    guest_place_of_origin: config.guestProfile.address || '',
                    guest_nationality: config.guestProfile.nationality || 'Filipino',
                    guest_company: config.guestProfile.company || '',
                    guest_valid_id: config.guestProfile.valid_id_type || '',
                    guest_gender: config.guestProfile.gender || '',
                },

                init() {
                    if (this.config.initialLines && this.config.initialLines.length > 0) {
                        this.lines = this.config.initialLines.map(l => ({
                            facility_id: l.facility_id,
                            check_in: l.check_in || '',
                            check_out: l.check_out || '',
                            date: l.date || '',
                            swimming_session_id: Number(l.swimming_session_id || this.config.sessions.find(s => s.slug === l.session)?.id || 0) || '',
                            session: l.session || '',
                            guest_count: l.guest_count || 1,
                            pax_tier: l.pax_tier || null,
                            early_check_in_hours: l.early_check_in_hours || 0,
                            late_check_out_hours: l.late_check_out_hours || 0,
                        }));
                    }

                    if (this.lines.length) this.checkAvailability().then(() => {
                        if (this.config.resumeAfterAuth && this.config.isAuthenticated && this.allAvailable) this.step = 2;
                    });
                },

                addLine(facilityId) {
                    if (this.lines.some(line => Number(line.facility_id) === Number(facilityId))) return;
                    const defaultFac = this.facility(facilityId);
                    this.lines.push({
                        facility_id: defaultFac ? defaultFac.id : '',
                        check_in: '',
                        check_out: '',
                        date: '',
                        swimming_session_id: '',
                        session: '',
                        guest_count: 1,
                        pax_tier: null,
                        early_check_in_hours: 0,
                        late_check_out_hours: 0,
                    });
                    this.onInputChanged();
                    this.$nextTick(() => document.getElementById('selected-services')?.scrollIntoView({ block: 'start', behavior: 'auto' }));
                },

                removeLine(index) {
                    this.lines.splice(index, 1);
                    this.onInputChanged();
                },

                facility(id) {
                    return this.config.facilities.find(f => f.id === Number(id));
                },

                facilityName(id) {
                    const fac = this.facility(id);
                    return fac ? fac.name : 'Choose a service';
                },

                isCottage(id) {
                    const fac = this.facility(id);
                    return fac ? fac.is_cottage : false;
                },

                isRoom(id) {
                    const fac = this.facility(id);
                    return fac ? fac.is_room : false;
                },
                isHall(id) { return Boolean(this.facility(id)?.is_hall); },
                datePart(value) { return value ? String(value).slice(0, 10) : ''; },
                setPackageDate(line, date) {
                    const schedule = this.facility(line.facility_id)?.schedule;
                    if (!date || !schedule?.start || !schedule?.end) return;
                    line.check_in = date + ' ' + schedule.start;
                    const end = new Date(date + 'T12:00:00');
                    if (schedule.overnight) end.setDate(end.getDate() + 1);
                    line.check_out = end.toISOString().slice(0, 10) + ' ' + schedule.end;
                },
                setHallSlot(line, date, slot) {
                    if (!date) return;
                    const [start, end] = slot.split('|');
                    line.hall_slot = slot;
                    line.check_in = date + ' ' + start;
                    line.check_out = date + ' ' + end;
                },

                maxCapacity(id) {
                    const fac = this.facility(id);
                    return fac ? fac.max_capacity : 10;
                },

                includedCapacity(id) {
                    const fac = this.facility(id);
                    return fac ? fac.included_capacity : 1;
                },

                onInputChanged() {
                    this.versionToken = null;
                    clearTimeout(this.debounceTimer);
                    this.debounceTimer = setTimeout(() => {
                        this.checkAvailability();
                    }, 500);
                },

                async checkAvailability() {
                    if (this.lines.length === 0) return;
                    this.checking = true;
                    this.errorMessage = '';

                    try {
                        const res = await fetch(this.config.availabilityUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.config.csrfToken,
                            },
                            body: JSON.stringify({ lines: this.lines }),
                        });

                        const payload = await res.json();
                        if (res.ok && payload.success) {
                            this.evaluation = payload.data;
                            this.versionToken = payload.data.version_token;
                            this.checkedAt = payload.data.checked_at;
                        } else {
                            this.errorMessage = payload.message || 'Could not verify availability.';
                        }
                    } catch (e) {
                        this.errorMessage = 'Network error while checking availability.';
                    } finally {
                        this.checking = false;
                    }
                },

                get allAvailable() {
                    return this.evaluation && this.evaluation.all_available && Boolean(this.versionToken);
                },

                get totalEstimate() {
                    return this.evaluation ? this.evaluation.total_estimate : 0;
                },

                get depositEstimate() {
                    return this.evaluation ? this.evaluation.deposit_estimate : 0;
                },
                get basketGuidance() {
                    if (this.checking) return 'Checking your selected schedules…';
                    if (!this.evaluation) return 'Choose dates and sessions below, then check availability.';
                    const failed = this.evaluation.lines?.find(line => !line.is_available);
                    return failed ? (failed.facility_name + ': ' + (failed.reason || 'Choose a valid schedule.')) : 'Refresh availability for the changed schedules.';
                },

                lineStatus(index) {
                    if (this.checking) return 'checking';
                    if (!this.versionToken) return 'stale';
                    const evaluated = this.evaluation?.lines?.[index];
                    if (!evaluated) return 'stale';
                    return evaluated.is_available ? 'available' : 'unavailable';
                },

                lineStatusClass(index) {
                    const status = this.lineStatus(index);
                    if (status === 'unavailable') return 'border-danger/60 bg-danger-light/10';
                    if (status === 'available') return 'border-success/60';
                    return '';
                },

                lineReason(index) {
                    return this.evaluation?.lines?.[index]?.reason || 'Service line is unavailable.';
                },

                lineEstimate(index) {
                    return this.evaluation?.lines?.[index]?.quoted_amount ?? null;
                },

                lineScheduleLabel(line) {
                    const evaluated = this.evaluation?.lines?.[this.lines.indexOf(line)];
                    if (evaluated?.check_in && evaluated?.check_out) return this.formatManila(evaluated.check_in) + ' to ' + this.formatManila(evaluated.check_out);
                    if (this.isCottage(line.facility_id)) return line.date ? line.date + ' · Choose swimming session' : 'Choose visit date and session';
                    return line.check_in && line.check_out ? this.formatManila(line.check_in) + ' to ' + this.formatManila(line.check_out) : 'Select schedule';
                },
                formatManila(value) {
                    const normalized = String(value).replace(' ', 'T').slice(0, 19);
                    const date = new Date(normalized + '+08:00');
                    if (Number.isNaN(date.getTime())) return value;
                    return new Intl.DateTimeFormat('en-PH', { timeZone: 'Asia/Manila', month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true }).format(date) + ' (PHT)';
                },

                formatPesos(amount) {
                    return window.pesos(amount);
                },

                continueToGuestDetails() {
                    if (!this.allAvailable) {
                        this.errorMessage = this.basketGuidance;
                        this.$nextTick(() => document.getElementById('selected-services')?.scrollIntoView({ block: 'start', behavior: 'auto' }));
                        return;
                    }
                    this.step = 2;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },

                async continueToReview() {
                    if (!this.guest.guest_name || !this.guest.guest_email || !this.guest.guest_phone || !this.guest.guest_valid_id) {
                        this.errorMessage = 'Please complete all required guest sheet fields.';
                        return;
                    }
                    await this.checkAvailability();
                    if (!this.allAvailable) { this.errorMessage = 'Review the current availability for each service before continuing.'; return; }
                    this.step = 3;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },

                async saveAndSignIn() { await this.saveAndAuthenticate(this.config.loginUrl); },
                async saveAndRegister() { await this.saveAndAuthenticate(this.config.registerUrl); },
                async saveAndAuthenticate(destination) {
                    if (!this.allAvailable) { this.errorMessage = this.basketGuidance; return; }
                    try {
                        const response = await fetch(this.config.intentUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.config.csrfToken },
                            body: JSON.stringify({ lines: this.lines }),
                        });
                        if (!response.ok) throw new Error('Could not save your booking. Please try again.');
                        window.location.href = destination;
                    } catch (error) { this.errorMessage = error.message || 'Could not save your booking.'; }
                },

                async submitGroupBooking() {
                    if (!this.policiesAccepted) {
                        this.errorMessage = 'Please accept the resort policies and rescheduling terms.';
                        return;
                    }
                    this.submitting = true;
                    this.errorMessage = '';

                    try {
                        const res = await fetch(this.config.confirmUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.config.csrfToken,
                            },
                            body: JSON.stringify({
                                lines: this.lines,
                                version_token: this.versionToken,
                                special_requests: this.specialRequests,
                                policies_accepted: true,
                                ...this.guest,
                            }),
                        });

                        const data = await res.json();
                        if (res.ok && data.success) {
                            window.location.href = data.redirect_url;
                        } else {
                            this.errorMessage = data.message || 'Submission failed. Please try again.';
                        }
                    } catch (e) {
                        this.errorMessage = 'Failed to submit booking request. Please check your connection.';
                    } finally {
                        this.submitting = false;
                    }
                }
            }));
        });
    </script>
</x-customer-layout>
