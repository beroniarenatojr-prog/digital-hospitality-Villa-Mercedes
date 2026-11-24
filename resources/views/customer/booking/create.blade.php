<x-customer-layout>
    @php
        $coverUrl = $facility->galleryImages->first()?->url();
        $isHall = $facility->isFunctionHall();
        $isFlyerPackage = $facility->isFlyerPackage();
        $paxTiers = $facility->paxTiers();
        $selectedPaxTier = old('pax_tier', $prefill['pax_tier'] ?? ($paxTiers[0] ?? null));
        $guestCap = $selectedPaxTier ? min($facility->effectiveMaxCapacity(), (int) $selectedPaxTier) : $facility->effectiveMaxCapacity();
        $peopleLabel = $isHall ? 'attendees' : 'guests';
        $peopleSingular = $isHall ? 'attendee' : 'guest';
        $arrivalLabel = $isHall ? 'Event start' : 'Check in';
        $departureLabel = $isHall ? 'Event end' : 'Check out';
        $peopleHeading = $isHall ? 'How many attendees?' : 'How many guests?';
        $schedulePrefill = array_replace($prefill, array_intersect_key(session()->getOldInput(), array_flip(['check_in', 'check_out', 'early_check_in_hours', 'late_check_out_hours'])));
    @endphp

    <div class="bg-primary text-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-7 sm:pt-12 sm:pb-10">
            <nav class="flex flex-wrap items-center gap-2 text-xs text-white/60 mb-5" aria-label="Breadcrumb">
                <a class="hover:text-white transition-colors" href="{{ route('customer.reservations.index') }}">My reservations</a>
                <span>/</span>
                <a class="hover:text-white transition-colors" href="{{ route('customer.facilities.show', $facility) }}">{{ $facility->name }}</a>
                <span>/</span>
                <span class="text-white font-medium">Reservation</span>
            </nav>
            <h1 class="mt-3 font-heading text-3xl sm:text-4xl lg:text-5xl font-semibold tracking-tight">Complete your reservation</h1>
            <p class="mt-3 text-white/75 max-w-xl">Check availability and submit your selection. The resort reviews every request together with your deposit proof &mdash; staff verify the transfer to confirm your booking and issue your check in pass. Function halls require a staff quotation and approval.</p>
        </div>
    </div>

    <div x-data="bookingWizard()" data-booking-wizard>
        <div class="sticky top-20 z-30 bg-background/95 backdrop-blur-md border-b border-border">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <ol class="flex items-center gap-2 sm:gap-4" aria-label="Reservation progress">
                    <li class="flex items-center gap-2 shrink-0">
                        <span class="w-7 h-7 rounded-full inline-flex items-center justify-center text-sm font-bold bg-success text-white">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                        </span>
                        <span class="hidden sm:block text-sm font-semibold text-text-muted">Space</span>
                    </li>
                    <li class="flex-1 h-0.5 bg-border rounded min-w-[1.5rem]" aria-hidden="true">
                        <div class="h-full bg-primary rounded transition-all duration-300" :style="step === 1 ? 'width:0%' : 'width:100%'"></div>
                    </li>
                    <li class="flex items-center gap-2 shrink-0">
                        <span class="w-7 h-7 rounded-full inline-flex items-center justify-center text-sm font-bold"
                              :class="step === 1 ? 'bg-primary text-white' : 'bg-success text-white'">
                            <template x-if="step === 1"><span>2</span></template>
                            <template x-if="step > 1"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg></template>
                        </span>
                        <span class="text-sm font-semibold" :class="step === 1 ? 'text-text' : 'text-text-muted'">Plan</span>
                    </li>
                    <li class="flex-1 h-0.5 bg-border rounded min-w-[1.5rem]" aria-hidden="true">
                        <div class="h-full bg-primary rounded transition-all duration-300" :style="step < 2 ? 'width:0%' : 'width:100%'"></div>
                    </li>
                    <li class="flex items-center gap-2 shrink-0">
                        <span class="w-7 h-7 rounded-full inline-flex items-center justify-center text-sm font-bold"
                              :class="step === 2 ? 'bg-primary text-white' : (step > 2 ? 'bg-success text-white' : 'bg-muted text-text-muted')">
                            <template x-if="step < 2"><span>3</span></template>
                            <template x-if="step === 2"><span>3</span></template>
                            <template x-if="step > 2"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg></template>
                        </span>
                        <span class="hidden sm:block text-sm font-semibold" :class="step === 2 ? 'text-text' : 'text-text-muted'">Guests</span>
                    </li>
                    <li class="flex-1 h-0.5 bg-border rounded min-w-[1.5rem]" aria-hidden="true">
                        <div class="h-full bg-primary rounded transition-all duration-300" :style="step < 3 ? 'width:0%' : 'width:100%'"></div>
                    </li>
                    <li class="flex items-center gap-2 shrink-0">
                        <span class="w-7 h-7 rounded-full inline-flex items-center justify-center text-sm font-bold" :class="step === 3 ? 'bg-primary text-white' : 'bg-muted text-text-muted'">4</span>
                        <span class="hidden sm:block text-sm font-semibold" :class="step === 3 ? 'text-text' : 'text-text-muted'">Confirm</span>
                    </li>
                    </ol>
                    <p class="mt-3 text-sm font-semibold text-text sm:hidden" x-text="stepLabel"></p>
            </div>
        </div>

        @if($resume !== null)
            {{--
                The guest left mid-flow, so say what happened and give them the one
                click that undoes it. A resumed form with no explanation reads as a
                page that remembered something it should not have.
            --}}
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
                <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 rounded-2xl border border-primary/30 bg-primary/5 px-5 py-4 sm:px-6">
                    <p class="text-sm text-text">
                        <span class="font-semibold">Picking up where you left off.</span>
                        Your dates, guests and any companion names are filled in. Nothing has been submitted yet.
                    </p>
                    <a href="{{ route('customer.booking.create', ['facility' => $facility, 'fresh' => 1]) }}" class="text-sm font-semibold text-primary hover:underline">
                        Start over
                    </a>
                </div>
            </div>
        @endif

        @if($errors->any())
            <div id="booking-validation-summary" role="alert" tabindex="-1" x-init="$nextTick(() => $el.focus())" class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
                <x-alert type="danger" title="Review your booking details">
                    <p>We could not submit the reservation yet. Fix the following {{ Str::plural('detail', $errors->count()) }} and try again.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            </div>
        @endif

        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14 pb-40 lg:pb-24">
            <div class="grid grid-cols-1 lg:grid-cols-[1.55fr_1fr] gap-8 lg:gap-12 items-start">
                <div class="relative">
                    <div x-show="loading" x-cloak x-transition.opacity class="absolute inset-0 z-10 rounded-2xl bg-surface/80 backdrop-blur-sm flex flex-col items-center justify-center">
                        <svg class="animate-spin h-10 w-10 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="mt-4 font-medium text-primary">Checking availability…</span>
                    </div>

                    <div x-show="error" x-cloak x-ref="errorSummary" id="booking-error-summary" role="alert" aria-live="assertive" tabindex="-1" class="mb-6">
                        <x-alert type="danger" title="We couldn't continue">
                            <span x-text="error"></span>
                            <p class="mt-1 text-sm">Review the message, correct the detail, then try again.</p>
                        </x-alert>
                    </div>

                    <div x-show="step === 1">
                        <form @submit.prevent="checkAvailability" class="space-y-6">
                            <div class="bg-surface border border-border rounded-2xl shadow-sm p-5 sm:p-7">
                                <h2 class="font-heading text-xl sm:text-2xl font-semibold text-text">When are you coming?</h2>
                                <div class="mt-4" @schedule-changed="scheduleChanged($event.detail)" @schedule-adjusted="step = 1; quoted = false">
                                    <x-reservation-schedule :facility="$facility" :initial="$schedulePrefill" prefix="booking-schedule" error-expression="error" />
                                </div>
                            </div>

                            <div class="bg-surface border border-border rounded-2xl shadow-sm p-5 sm:p-7">
                                <h2 class="font-heading text-xl sm:text-2xl font-semibold text-text">{{ $peopleHeading }}</h2>
                                @if(count($paxTiers) > 1)
                                    <p class="mt-1.5 text-sm text-text-muted">Select a pax tier first, then enter how many guests are coming (up to that cap). There is no extra-person fee.</p>
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        @foreach($paxTiers as $tier)
                                            <button type="button" @click="selectPaxTier({{ $tier }})"
                                                    :class="form.pax_tier === {{ $tier }} ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-border hover:border-primary/50'"
                                                    class="rounded-xl border-2 px-4 py-2 text-sm font-semibold text-text">
                                                {{ $tier }} pax
                                            </button>
                                        @endforeach
                                    </div>
                                @elseif($isFlyerPackage)
                                    <p class="mt-1.5 text-sm text-text-muted">Good for {{ $guestCap }} {{ $peopleLabel }}. Guest count cannot exceed this package category.</p>
                                @else
                                    <p class="mt-1.5 text-sm text-text-muted">
                                        Included in the base price: {{ $facility->capacity }} {{ $peopleLabel }}. Up to {{ $facility->effectiveMaxCapacity() }} can be accommodated, with an extra charge per additional {{ $peopleSingular }}.
                                    </p>
                                @endif
                                <div class="mt-5 flex items-center gap-3">
                                    <button type="button" @click="changeGuestCount(form.guestCount - 1)"
                                            class="w-11 h-11 rounded-lg border border-border text-text hover:bg-background focus:outline-none focus:ring-2 focus:ring-primary text-xl font-semibold"
                                            aria-label="Remove one {{ $peopleSingular }}">&minus;</button>
                                    <x-text-input id="guestCount" x-model.number="form.guestCount" @change="changeGuestCount(form.guestCount)" type="number" min="1" :max="$guestCap" class="w-24 text-center" required />
                                    <button type="button" @click="changeGuestCount(form.guestCount + 1)"
                                            class="w-11 h-11 rounded-lg border border-border text-text hover:bg-background focus:outline-none focus:ring-2 focus:ring-primary text-xl font-semibold"
                                            aria-label="Add one {{ $peopleSingular }}">+</button>
                                    <span class="text-sm text-text-muted">{{ $peopleLabel }}</span>
                                </div>
                                @unless($isFlyerPackage)
                                <p x-show="form.guestCount - form.children_count > {{ $facility->capacity }}" x-cloak class="mt-3 text-sm text-accent font-medium">
                                    An extra {{ $peopleSingular }} charge will be added for <span x-text="form.guestCount - form.children_count - {{ $facility->capacity }}"></span> {{ $peopleSingular }}(s) above {{ $facility->capacity }}.
                                </p>
                                @endunless
                            </div>

                            @if($facility->isRoom() || $facility->isPackage())
                                <div class="grid gap-3 rounded-xl border border-border p-4 sm:grid-cols-2">
                                    <label>Infants under one (free; excluded from capacity)<input type="number" min="0" max="500" x-model.number="form.infants_count" class="admin-input w-full"></label>
                                    <label>Older children (included in guest count)<input type="number" min="0" :max="form.guestCount" x-model.number="form.children_count" @change="quoted = false; rememberTotal()" class="admin-input w-full"></label>
                                    <p class="text-sm text-text-muted sm:col-span-2">Owner decides older-child charges after the base deposit. Disputed charges are held for staff discussion.</p>
                                </div>
                            @endif
                            <label class="flex items-start gap-2 text-sm"><input type="checkbox" x-model="form.pets_included" @change="quoted = false; rememberTotal()"> Pets included — <x-amount :value="500" /> once per booking. Diapers and a cage or leash required; pets cannot enter swimming pools.</label>

                            <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                                <a href="{{ route('customer.reservations.new', ['type' => $facility->facilityType->slug]) }}" class="inline-flex items-center justify-center gap-1 w-full sm:w-auto min-h-[44px] px-4 rounded-lg border border-border bg-surface text-sm font-semibold text-text hover:bg-background">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                                    Change space
                                </a>
                                <x-primary-button type="submit" class="w-full sm:w-auto !py-3 !text-base" x-bind:disabled="loading">
                                    Continue to guest information
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                </x-primary-button>
                            </div>
                            <p class="text-sm text-text-muted text-center sm:text-right">After submission, your request waits in Review pending. Staff must verify at least the 50% down payment before confirmation. Any remaining balance is due at physical check-in.</p>
                        </form>
                    </div>

                    <div x-show="step === 2" x-cloak>
                        @php
                            // The guest sheet is read-only here: the values come from the
                            // profile and are corrected there. A muted fill is what makes
                            // that visible — `read-only:bg-white` was the one panel fill in
                            // the account area that was not a token, so the control looked
                            // editable on the surface it sits on with no fill at all.
                            $profileFieldClass = 'mt-2 block w-full read-only:bg-muted read-only:text-text-muted read-only:focus:border-border read-only:focus:ring-0 cursor-default';
                        @endphp
                        <form @submit.prevent="continueGuestSheet" class="space-y-6">
                            <div class="bg-surface border border-border rounded-2xl shadow-sm p-5 sm:p-7">
                                <div x-ref="guestSheet" tabindex="-1" class="focus:outline-none">
                                    <h2 class="font-heading text-xl sm:text-2xl font-semibold text-text">Guest information sheet</h2>
                                    <p class="mt-1.5 text-sm text-text-muted">
                                        These details come from your account and can’t be edited here.
                                        <a href="{{ route('profile.edit') }}" class="font-semibold text-primary hover:text-primary-hover underline-offset-2 hover:underline">Update your profile</a>
                                        if something needs to change. You can still add companion names and preferences below.
                                    </p>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-6">
                                    <div>
                                        <x-input-label for="guest_name" value="Name" />
                                        <x-text-input id="guest_name" x-model="form.guest_name" type="text" class="{{ $profileFieldClass }}" readonly tabindex="-1" maxlength="255" autocomplete="name" />
                                        <x-input-error class="mt-1" :messages="$errors->get('guest_name')" />
                                    </div>
                                    <div>
                                        <x-input-label for="guest_gender" value="Gender" />
                                        <x-text-input
                                            id="guest_gender"
                                            type="text"
                                            class="{{ $profileFieldClass }}"
                                            :value="auth()->user()?->gender?->label() ?? ''"
                                            readonly
                                            tabindex="-1"
                                        />
                                    </div>
                                    <div>
                                        <x-input-label for="guest_place_of_origin" value="Address" />
                                        <x-text-input id="guest_place_of_origin" x-model="form.guest_place_of_origin" type="text" class="{{ $profileFieldClass }}" readonly tabindex="-1" maxlength="255" autocomplete="street-address" />
                                        <x-input-error class="mt-1" :messages="$errors->get('guest_place_of_origin')" />
                                    </div>
                                    <div>
                                        <x-input-label for="guest_nationality" value="Nationality" />
                                        <x-text-input id="guest_nationality" x-model="form.guest_nationality" type="text" class="{{ $profileFieldClass }}" readonly tabindex="-1" maxlength="100" />
                                        <x-input-error class="mt-1" :messages="$errors->get('guest_nationality')" />
                                    </div>
                                    <div>
                                        <x-input-label for="guest_company" value="Company Name/Agency (optional)" />
                                        <x-text-input id="guest_company" x-model="form.guest_company" type="text" class="{{ $profileFieldClass }}" readonly tabindex="-1" maxlength="255" />
                                        <x-input-error class="mt-1" :messages="$errors->get('guest_company')" />
                                    </div>
                                    <div>
                                        <x-input-label for="guest_valid_id" value="Valid ID type" />
                                        <x-text-input
                                            id="guest_valid_id"
                                            type="text"
                                            class="{{ $profileFieldClass }}"
                                            :value="auth()->user()?->valid_id_type?->label() ?? ''"
                                            readonly
                                            tabindex="-1"
                                        />
                                        <p class="mt-1 text-xs text-text-muted">Shown only to staff. Bring this ID so the desk can verify you at check-in.</p>
                                        <x-input-error class="mt-1" :messages="$errors->get('guest_valid_id')" />
                                    </div>
                                    <div>
                                        <x-input-label for="guest_phone" value="Contact #" />
                                        <x-text-input id="guest_phone" x-model="form.guest_phone" type="tel" class="{{ $profileFieldClass }}" readonly tabindex="-1" maxlength="32" autocomplete="tel" />
                                        <x-input-error class="mt-1" :messages="$errors->get('guest_phone')" />
                                    </div>
                                    <div>
                                        <x-input-label for="guest_email" value="Email Add" />
                                        <x-text-input id="guest_email" x-model="form.guest_email" type="email" class="{{ $profileFieldClass }}" readonly tabindex="-1" maxlength="255" autocomplete="email" />
                                        <x-input-error class="mt-1" :messages="$errors->get('guest_email')" />
                                    </div>
                                    <div>
                                        <x-input-label value="Room #" />
                                        <p class="mt-2 text-sm font-semibold text-text">{{ $facility->name }}</p>
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <x-input-label value="Other Guest Names" />
                                            <p class="mt-1 text-sm text-text-muted">
                                                Add companion names and a valid ID type in case they arrive early, or they are the ones staying.
                                            </p>
                                        </div>
                                        <button type="button" @click="addOtherGuest()" x-show="canAddOtherGuest"
                                                class="shrink-0 inline-flex items-center px-3 py-1.5 rounded-lg border border-border text-xs font-semibold text-text bg-surface hover:bg-background">
                                            Add guest name
                                        </button>
                                    </div>
                                    <div class="mt-3 space-y-3" x-show="form.other_guest_names.length > 0">
                                        <template x-for="(guest, index) in form.other_guest_names" :key="index">
                                            <div class="rounded-xl border border-border bg-background/50 p-3 sm:p-4 space-y-3">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs font-semibold text-text-muted w-5" x-text="(index + 1) + '.'"></span>
                                                    <input type="text" x-model="guest.name" maxlength="255" placeholder="Companion full name"
                                                           @input="guest.name = String($event.target.value || '').replace(/[^\p{L}\p{M} .'’\-]/gu, '')"
                                                           :aria-label="'Companion ' + (index + 1) + ' name'"
                                                           class="border-border focus:border-primary focus:ring-primary rounded-lg shadow-sm block w-full">
                                                    <button type="button" @click="removeOtherGuest(index)" class="text-xs font-semibold text-text-muted hover:text-danger shrink-0">
                                                        Remove
                                                    </button>
                                                </div>
                                                <div class="sm:pl-7">
                                                    <label class="block text-xs font-medium text-text-muted" :for="'other_guest_valid_id_' + index">Valid ID type (optional)</label>
                                                    <select :id="'other_guest_valid_id_' + index" x-model="guest.valid_id"
                                                            class="mt-1.5 border-border focus:border-primary focus:ring-primary rounded-lg shadow-sm block w-full">
                                                        <option value="">Select if available</option>
                                                        @foreach(\App\Enums\GuestIdType::cases() as $idType)
                                                            <option value="{{ $idType->value }}">{{ $idType->label() }}</option>
                                                        @endforeach
                                                    </select>
                                                    <p class="mt-1 text-xs text-text-muted">Needed if this companion arrives early or is the one staying.</p>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                    <p class="mt-2 text-xs text-text-muted" x-show="!canAddOtherGuest && maxCompanionSlots > 0 && form.other_guest_names.length >= maxCompanionSlots">
                                        Maximum other guest names for this space reached.
                                    </p>
                                    <x-input-error class="mt-1" :messages="$errors->get('other_guest_names')" />
                                    <x-input-error class="mt-1" :messages="$errors->get('other_guest_names.*.name')" />
                                    <x-input-error class="mt-1" :messages="$errors->get('other_guest_names.*.valid_id')" />
                                </div>

                                <div class="mt-6">
                                    <x-input-label for="special_requests" value="Preferences & additional requests (optional)" />
                                    <textarea id="special_requests" x-model="form.special_requests" rows="3" maxlength="1000"
                                              class="mt-2 border-border focus:border-primary focus:ring-primary rounded-lg shadow-sm block w-full"
                                              placeholder="Anything staff should know for your stay? (celebration, accessibility, timing notes)"></textarea>
                                    <p class="mt-1 text-xs text-text-muted">Avoid sharing medical details unless needed to arrange a requested accommodation.</p>
                                    <x-input-error class="mt-1" :messages="$errors->get('special_requests')" />
                                </div>

                                <p class="mt-6 text-sm text-text-muted">This information form is for the hotel's record — filing and security purpose. Thank you!</p>
                            </div>

                            <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                                <x-secondary-button type="button" @click="step = 1; quoted = false; error = ''" class="sm:w-auto w-full">
                                    Change stay
                                </x-secondary-button>
                                <x-primary-button type="submit" class="w-full sm:w-auto !py-3 !text-base">
                                    Continue to confirm
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                </x-primary-button>
                            </div>
                        </form>
                    </div>

                    <div x-show="step === 3" x-cloak>
                        <div class="bg-surface border border-border rounded-2xl shadow-sm p-5 sm:p-7">
                            <div x-ref="review" tabindex="-1" class="focus:outline-none">
                                <h2 class="font-heading text-xl sm:text-2xl font-semibold text-text">Ready to confirm?</h2>
                                <p class="mt-1.5 text-sm text-text-muted">Your space, schedule, guest sheet, and total are below.</p>
                            </div>

                            <dl class="mt-6 bg-background rounded-xl p-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <dt class="text-sm text-text-muted">{{ $arrivalLabel }}</dt>
                                    <dd class="font-semibold text-text mt-0.5" x-text="formatDate(form.check_in)"></dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-text-muted">{{ $departureLabel }}</dt>
                                    <dd class="font-semibold text-text mt-0.5" x-text="formatDate(form.check_out)"></dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-text-muted">Name</dt>
                                    <dd class="font-semibold text-text mt-0.5" x-text="form.guest_name"></dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-text-muted">Contact #</dt>
                                    <dd class="font-semibold text-text mt-0.5" x-text="form.guest_phone"></dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-text-muted">{{ ucfirst($peopleLabel) }}</dt>
                                    <dd class="font-semibold text-text mt-0.5" x-text="form.guestCount + ' {{ $peopleSingular }}' + (form.guestCount === 1 ? '' : 's')"></dd>
                                </div>
                                <div x-show="form.early_check_in_hours > 0 || form.late_check_out_hours > 0">
                                    <dt class="text-sm text-text-muted">Extra hours</dt>
                                    <dd class="font-semibold text-text mt-0.5">
                                        <span x-show="form.early_check_in_hours > 0"><span x-text="form.early_check_in_hours"></span> before check in</span>
                                        <span x-show="form.early_check_in_hours > 0 && form.late_check_out_hours > 0">, </span>
                                        <span x-show="form.late_check_out_hours > 0"><span x-text="form.late_check_out_hours"></span> after check out</span>
                                    </dd>
                                </div>
                                <div x-show="form.special_requests.trim()" class="sm:col-span-2">
                                    <dt class="text-sm text-text-muted">Preferences & requests</dt>
                                    <dd class="font-semibold text-text mt-0.5 whitespace-pre-line" x-text="form.special_requests"></dd>
                                </div>
                                <div class="sm:col-span-2 bg-primary/5 border border-primary/20 rounded-lg p-4">
                                    <dt class="text-sm font-semibold text-text">The space is held for you from</dt>
                                    <dd class="text-text mt-1" x-text="occupancyWindowLabel()"></dd>
                                    <p class="text-sm text-text-muted mt-1">Extra hours are part of your reservation, so no one else can reserve this space during that time.</p>
                                </div>
                            </dl>

                            <div class="mt-6 border border-border rounded-xl p-5">
                                <h3 class="font-bold text-text mb-4">Price breakdown</h3>
                                <div class="space-y-3 text-sm">
                                    @include('customer.booking.partials.price-summary', ['deposit' => true])
                                    <p class="text-sm text-accent font-medium" x-show="loading" x-cloak>Updating price…</p>
                                    <p class="text-sm text-text-muted" x-show="!quoted && !loading" x-cloak>Confirm is available after the latest total is calculated.</p>
                                </div>
                            </div>

                            <x-alert type="info" title="After submission" class="mt-6">
                                Your request waits in Review pending on My reservations, and we email you when the resort approves it. Open the reservation then to scan the QR, transfer the 50%, and upload your proof &mdash; staff verify the transfer and issue your check in pass. Custom function halls wait for staff quotation and approval.
                            </x-alert>

                            <form action="{{ route('customer.reservations.store') }}" method="POST" class="mt-6" x-ref="confirmForm" @submit="submitReservation($event)">
                                @csrf
                                <input type="hidden" name="facility_id" value="{{ $facility->id }}">
                                <input type="hidden" name="check_in" x-model="form.check_in">
                                <input type="hidden" name="check_out" x-model="form.check_out">
                                <input type="hidden" name="guestCount" x-model="form.guestCount">
                                <input type="hidden" name="infants_count" x-model="form.infants_count">
                                <input type="hidden" name="children_count" x-model="form.children_count">
                                <input type="hidden" name="pets_included" :value="form.pets_included ? '1' : '0'">
                                <input type="hidden" name="pax_tier" x-model="form.pax_tier">
                                <input type="hidden" name="early_check_in_hours" x-model="form.early_check_in_hours">
                                <input type="hidden" name="late_check_out_hours" x-model="form.late_check_out_hours">
                                <input type="hidden" name="guest_name" x-model="form.guest_name">
                                <input type="hidden" name="guest_place_of_origin" x-model="form.guest_place_of_origin">
                                <input type="hidden" name="guest_nationality" x-model="form.guest_nationality">
                                <input type="hidden" name="guest_company" x-model="form.guest_company">
                                <input type="hidden" name="guest_valid_id" x-model="form.guest_valid_id">
                                <input type="hidden" name="guest_phone" x-model="form.guest_phone">
                                <input type="hidden" name="guest_email" x-model="form.guest_email">
                                <template x-for="(guest, index) in form.other_guest_names" :key="'store-other-' + index">
                                    <template x-if="String(guest.name || '').trim() !== ''">
                                        <span>
                                            <input type="hidden" :name="'other_guest_names[' + index + '][name]'" :value="guest.name">
                                            <input type="hidden" :name="'other_guest_names[' + index + '][valid_id]'" :value="guest.valid_id || ''">
                                        </span>
                                    </template>
                                </template>
                                <input type="hidden" name="special_requests" x-model="form.special_requests">
                                <input type="hidden" name="policies_accepted" :value="form.policies_accepted ? '1' : ''">

                                <div class="rounded-xl border border-border bg-background/60 px-4 py-3">
                                    <div class="flex items-start gap-3 text-sm text-text">
                                        <input id="policies_accepted" type="checkbox" x-model="form.policies_accepted" required
                                               class="mt-1 rounded border-border text-primary focus:ring-primary">
                                        <p>
                                            <label for="policies_accepted" class="cursor-pointer">I agree to the</label>
                                            <a href="{{ route('terms') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-primary hover:underline">resort policies and rescheduling terms</a>.
                                        </p>
                                    </div>
                                    <x-input-error class="mt-1" :messages="$errors->get('policies_accepted')" />
                                </div>

                                <div class="mt-6 flex flex-col-reverse sm:flex-row gap-3">
                                    <x-secondary-button type="button" @click="step = 2; error = ''" class="sm:w-auto w-full">
                                        Change guest details
                                    </x-secondary-button>
                                    <x-primary-button type="submit" class="flex-1 !py-3 !text-base" x-bind:disabled="!quoted || loading">
                                        Confirm reservation
                                    </x-primary-button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <aside class="lg:sticky lg:top-[8.5rem]">
                    <div class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
                        <div class="relative aspect-[16/9] bg-primary-light">
                            @if($coverUrl)
                                <img src="{{ $coverUrl }}" alt="" class="w-full h-full object-cover">
                            @endif
                            <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent"></div>
                            <div class="absolute bottom-4 left-5 right-5 text-white">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-white/70">{{ $facility->facilityType->name }}</p>
                                <p class="font-heading text-xl font-semibold mt-1">{{ $facility->name }}</p>
                            </div>
                        </div>

                        <div class="p-5 sm:p-6">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-accent">Reservation summary</p>

                            <div class="mt-4 grid grid-cols-2 gap-3">
                                <div class="rounded-xl bg-background p-3.5">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-text-muted">{{ $arrivalLabel }}</p>
                                    <p class="mt-1 text-sm font-semibold text-text" x-text="form.check_in ? formatDay(form.check_in) : '—'"></p>
                                    <p class="text-[11px] text-text-muted mt-0.5" x-text="timeLabel(form.check_in)"></p>
                                </div>
                                <div class="rounded-xl bg-background p-3.5">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-text-muted">{{ $departureLabel }}</p>
                                    <p class="mt-1 text-sm font-semibold text-text" x-text="form.check_out ? formatDay(form.check_out) : '—'"></p>
                                    <p class="text-[11px] text-text-muted mt-0.5" x-text="timeLabel(form.check_out)"></p>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap items-center gap-2 text-xs font-semibold text-text">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-background px-2.5 py-1" x-show="form.check_in && form.check_out" x-cloak>
                                    <span x-text="nightsLabel"></span>
                                </span>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-background px-2.5 py-1">
                                    <span x-text="form.guestCount + ' {{ $peopleSingular }}' + (form.guestCount === 1 ? '' : 's')"></span>
                                </span>
                            </div>

                            <div class="mt-5 pt-5 border-t border-border" x-show="form.check_in && form.check_out">
                                @include('customer.booking.partials.price-summary', ['compact' => true])
                            </div>
                            <p class="mt-4 text-sm text-text-muted" x-show="!form.check_in || !form.check_out" x-cloak>Pick {{ strtolower($arrivalLabel) }} and {{ strtolower($departureLabel) }} to see a total.</p>

                            <div class="mt-5 rounded-2xl bg-primary text-white p-4" x-show="form.check_in && form.check_out && displayPrice.total_amount > 0">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-white/75">Down payment (50%)</span>
                                    <span class="font-bold tabular-nums"><span x-text="pesosFromCents(suggestedDown)"></span></span>
                                </div>
                                <div class="flex items-center justify-between text-sm mt-2.5 pt-2.5 border-t border-white/15">
                                    <span class="text-white/75">Balance on check in</span>
                                    <span class="font-semibold tabular-nums"><span x-text="pesosFromCents(displayPrice.total_amount - suggestedDown)"></span></span>
                                </div>
                                <p class="mt-3 text-[11px] text-white/70">At least 50% down payment is required before confirmation; 100% online payment is allowed. Proof remains under review until verified. Any remaining balance is due at physical check-in.</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-surface border border-border rounded-2xl shadow-sm p-5 mt-5">
                        <div class="flex items-center gap-3">
                            <span class="w-9 h-9 grid place-items-center rounded-full bg-primary-light text-primary flex-none">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.95.68l1.5 4.5a1 1 0 01-.5 1.21l-2.26 1.13a11.04 11.04 0 005.52 5.52l1.13-2.26a1 1 0 011.21-.5l4.5 1.5a1 1 0 01.68.95V19a2 2 0 01-2 2h-1C9.72 21 3 14.28 3 6V5z" /></svg>
                            </span>
                            <div class="text-sm">
                                <p class="font-semibold text-text">Need help reserving?</p>
                                <p class="text-xs text-text-muted mt-0.5"><a href="{{ config('resort.phone_href') }}" class="hover:text-primary">{{ config('resort.phone') }}</a></p>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        {{--
            The bar owns the full width and the display's own insets. It used to
            hold back its right hundred pixels for the Messenger launcher, which
            meant the launcher was painted over the bar inside the bar's own
            gutter; the launcher now climbs above whatever is docked, so the CTA
            gets the width and nothing lands on it.

            `--dock-bottom` is the stylesheet's guess at this bar's height, which
            is what the launcher and back-to-top read. Alpine measures the real
            one on boot and on every resize, because the bar grows a fourth line
            when the total has moved since the guest first saw it.
        --}}
        <div class="lg:hidden fixed inset-x-0 bottom-0 z-50 border-t border-border bg-surface/95 backdrop-blur-md pt-3 pl-[max(1rem,var(--safe-left))] pr-[max(1rem,var(--safe-right))] pb-[calc(0.75rem+var(--safe-bottom))]"
             x-init="(() => {
                 const dock = () => document.documentElement.style.setProperty('--dock-bottom', $el.offsetHeight + 'px');
                 dock();
                 // One measurement at boot is a measurement of the wrong bar: it
                 // grows a fourth line when the total moves, and the webfont
                 // lands after first paint. Observe it instead, so the helpers
                 // above it are never a few pixels into the bar they clear.
                 if (window.ResizeObserver) {
                     $el._dock = new ResizeObserver(dock);
                     $el._dock.observe($el);
                 } else {
                     window.addEventListener('resize', dock);
                 }
             })()">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-text-muted">Total</p>
                    <p class="font-heading text-xl font-semibold text-primary truncate"><span x-text="pesosFromCents(displayPrice.total_amount)"></span></p>
                    {{-- The bar is the number a guest checks on the way out, so it
                         says which total it is and how it moved. --}}
                    <p class="text-[11px] truncate" :class="changeNote ? 'font-semibold text-accent' : 'text-text-muted'"
                       x-text="changeNote ? changeNoteHeadline : totalLabel"></p>
                    <p class="text-[11px] text-text-muted truncate" x-show="changeNoteReasons" x-cloak x-text="changeNoteReasons"></p>
                </div>
                <button type="button"
                        class="shrink-0 inline-flex items-center justify-center min-h-[44px] px-4 rounded-lg bg-primary text-sm font-semibold text-white hover:bg-primary-hover disabled:opacity-60"
                        x-bind:disabled="loading || (step === 3 && !quoted)"
                        @click="bottomBarAction()"
                        x-text="bottomBarLabel">
                </button>
            </div>
        </div>
    </div>

    @push('scripts')
    @php
        $bookingWizardConfig = [
            'defaultGuestCount' => (int) ($selectedPaxTier ?: $facility->capacity),
            'calculateUrl' => route('customer.booking.calculate', $facility),
            'csrfToken' => csrf_token(),
            'prefill' => $schedulePrefill,
            'continueAfterAvailability' => (bool) ($prefill['availability_checked'] ?? false),
            // A resumed draft opens on the step the guest left, and re-prices
            // itself on the way in (`resumed`), so Confirm is never a dead button
            // on a total that was calculated before they walked away.
            'resumed' => $resume !== null,
            'startStep' => $errors->hasAny(['check_in', 'check_out', 'early_check_in_hours', 'late_check_out_hours']) ? 1 : ($errors->any() ? 2 : (int) ($resume['step'] ?? 1)),
            'maxCapacity' => (int) $guestCap,
            'includedCapacity' => (int) ($selectedPaxTier ?: $facility->capacity),
            'paxTiers' => $paxTiers,
            'isFlyerPackage' => $isFlyerPackage,
            'isRoom' => $facility->isRoom(),
            'packageStay' => $isFlyerPackage ? [
                'start' => $facility->stayStartTimeString(),
                'end' => $facility->stayEndTimeString(),
                'overnight' => (bool) $facility->stay_spans_midnight,
            ] : null,
            'guestPrefill' => array_merge(
                \App\Support\GuestProfilePrefill::forUser(auth()->user()),
                [
                    // Companions and preferences are the only parts of the guest
                    // sheet a guest types by hand, so they are the only parts a
                    // draft hands back. A rejected submission still beats both.
                    'other_guest_names' => array_map(
                        static fn (array $guest): array => [
                            'name' => $guest['name'],
                            'valid_id' => $guest['valid_id'] ?? '',
                        ],
                        \App\Support\GuestInformation::normalizeOtherGuests(
                            old('other_guest_names', $resume['other_guest_names'] ?? []),
                            keepEmpty: true
                        )
                    ),
                    'special_requests' => old('special_requests', $resume['special_requests'] ?? ''),
                    // Agreement to the policies is asked for at submission, not
                    // restored: consent is given for a booking, not remembered for one.
                    'policies_accepted' => (bool) old('policies_accepted', false),
                ]
            ),
            // The words the price lines are said in. They live here rather than in
            // the browser so a room and a function hall describe the same charge
            // with their own noun.
            'priceLabels' => [
                'base' => 'Base amount',
                'extraPerson' => 'Extra '.$peopleLabel,
                'early' => 'Early check in',
                'late' => 'Late check out',
            ],
            'guestWords' => [
                'one' => $peopleSingular,
                'many' => $peopleLabel,
            ],
            'rates' => [
                'baseCents' => $baseRate ? (int) round(((float) $baseRate->amount) * 100) : 0,
                'baseUnit' => $baseRate?->unit ?? 'per night',
                'extraPersonCents' => $extraPersonRate ? (int) round(((float) $extraPersonRate->amount) * 100) : 0,
                'earlyHourCents' => $earlyCheckInRate ? (int) round(((float) $earlyCheckInRate->amount) * 100) : 0,
                'extensionHourCents' => app(\App\Services\ReservationSchedule::class)->extensionRateCents($facility),
                'lateHourCents' => $lateCheckOutRate ? (int) round(((float) $lateCheckOutRate->amount) * 100) : 0,
                'includedGuests' => (int) $facility->capacity,
            ],

        ];
    @endphp
    <script type="application/json" id="booking-wizard-config">@json($bookingWizardConfig)</script>
    <script>
        const bookingWizardConfig = JSON.parse(document.getElementById('booking-wizard-config').textContent);

        document.addEventListener('alpine:init', () => {
            Alpine.data('bookingWizard', () => ({
                step: bookingWizardConfig.startStep || 1,
                loading: false,
                error: '',
                quoted: false,
                requestNumber: 0,
                form: {
                    check_in: '',
                    check_out: '',
                    guestCount: bookingWizardConfig.defaultGuestCount,
                    infants_count: @json((int) old('infants_count', 0)),
                    children_count: @json((int) old('children_count', 0)),
                    pets_included: @json((bool) old('pets_included', false)),
                    pax_tier: (bookingWizardConfig.prefill && bookingWizardConfig.prefill.pax_tier) || (bookingWizardConfig.paxTiers && bookingWizardConfig.paxTiers[0]) || null,
                    early_check_in_hours: 0,
                    late_check_out_hours: 0,
                    hall_extension_hours: 0,
                    guest_name: (bookingWizardConfig.guestPrefill && bookingWizardConfig.guestPrefill.guest_name) || '',
                    guest_place_of_origin: (bookingWizardConfig.guestPrefill && bookingWizardConfig.guestPrefill.guest_place_of_origin) || '',
                    guest_nationality: (bookingWizardConfig.guestPrefill && bookingWizardConfig.guestPrefill.guest_nationality) || '',
                    guest_company: (bookingWizardConfig.guestPrefill && bookingWizardConfig.guestPrefill.guest_company) || '',
                    guest_valid_id: (bookingWizardConfig.guestPrefill && bookingWizardConfig.guestPrefill.guest_valid_id) || '',
                    guest_phone: (bookingWizardConfig.guestPrefill && bookingWizardConfig.guestPrefill.guest_phone) || '',
                    guest_email: (bookingWizardConfig.guestPrefill && bookingWizardConfig.guestPrefill.guest_email) || '',
                    other_guest_names: ((bookingWizardConfig.guestPrefill && bookingWizardConfig.guestPrefill.other_guest_names) || []).map((guest) => ({
                        name: typeof guest === 'string' ? guest : String(guest?.name || ''),
                        valid_id: typeof guest === 'string' ? '' : String(guest?.valid_id || ''),
                    })),
                    special_requests: (bookingWizardConfig.guestPrefill && bookingWizardConfig.guestPrefill.special_requests) || '',
                    policies_accepted: Boolean(bookingWizardConfig.guestPrefill && bookingWizardConfig.guestPrefill.policies_accepted)
                },
                price: {
                    base_amount: 0,
                    early_check_in_charge: 0,
                    late_check_out_charge: 0,
                    extra_person_charge: 0,
                    total_amount: 0
                },
                // The total the guest was last shown, and how it has moved since.
                // A figure that changes without saying why cannot be checked, so
                // every movement leaves a note beside the total (see
                // `resources/js/price-change.js` for the wording).
                lastPriced: null,
                changeNote: null,

                init() {
                    const prefill = bookingWizardConfig.prefill || {};
                    if (prefill.guest_count) this.form.guestCount = prefill.guest_count;
                },

                scheduleChanged(detail) {
                    Object.assign(this.form, detail);
                    this.quoted = false;
                    this.error = '';
                    this.rememberTotal();
                    if (!detail.initial) {
                        clearTimeout(this.scheduleTimer);
                        this.scheduleTimer = setTimeout(() => this.refreshQuote(), 300);
                    } else if (bookingWizardConfig.resumed || bookingWizardConfig.continueAfterAvailability) {
                        this.$nextTick(() => this.refreshQuote());
                    }
                },

                selectPaxTier(tier) {
                    this.form.pax_tier = Number(tier);
                    bookingWizardConfig.maxCapacity = Number(tier);
                    if (this.form.guestCount > tier) {
                        this.form.guestCount = tier;
                    }
                    this.quoted = false;
                    this.rememberTotal();
                },

                /**
                 * The guest changed a quantity the total is built from.
                 *
                 * The number beside them moves, so it has to say why — and it has
                 * to say it now, not when the next quote comes back, because until
                 * then the guest is looking at a figure they cannot account for.
                 */
                priceInputChanged() {
                    this.quoted = false;
                    this.rememberTotal();
                },

                async checkAvailability() {
                    if (!this.form.check_in || !this.form.check_out) {
                        this.showError('Please choose both a check in and a check out date.');
                        return;
                    }

                    // Step 2 is where this succeeds to, so that is the step the
                    // draft records if the guest never comes back.
                    const ok = await this.refreshQuote(2);

                    if (ok) {
                        this.step = 2;
                        this.$nextTick(() => {
                            this.$refs.guestSheet.focus();
                            this.$el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        });
                    }
                },

                async refreshQuote(nextStep = null) {
                    if (!this.form.check_in || !this.form.check_out) {
                        return false;
                    }

                    const requestNumber = ++this.requestNumber;
                    const quotedSelection = JSON.stringify(window.bookingPrice.inputsFrom(this.form));
                    this.loading = true;
                    this.quoted = false;
                    this.error = '';

                    try {
                        const response = await fetch(bookingWizardConfig.calculateUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': bookingWizardConfig.csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                check_in: this.form.check_in,
                                check_out: this.form.check_out,
                                guestCount: this.form.guestCount,
                                children_count: this.form.children_count,
                                pets_included: this.form.pets_included,
                                pax_tier: this.form.pax_tier,
                                early_check_in_hours: this.form.early_check_in_hours,
                                late_check_out_hours: this.form.late_check_out_hours,
                                // Which step this outcome leads to, and the guest-sheet
                                // work in hand: the server keeps both so the guest can
                                // leave on any step and still be given it back.
                                step: nextStep || this.step,
                                other_guest_names: this.form.other_guest_names,
                                special_requests: this.form.special_requests
                            })
                        });

                        const data = await response.json();
                        if (requestNumber !== this.requestNumber || quotedSelection !== JSON.stringify(window.bookingPrice.inputsFrom(this.form))) return false;

                        if (!response.ok || !data.success) {
                            const validationMessage = (data.errors && Object.values(data.errors).flat().find(Boolean))
                                || data.message
                                || 'We could not work out a price for those dates. Please try different ones.';
                            this.showError(validationMessage, nextStep !== null);
                            return false;
                        }

                        this.price = data.data;
                        this.quoted = true;
                        // The resort's answer can differ from the estimate the guest
                        // was reading; if it does, that is a change they are told
                        // about rather than a figure that quietly corrects itself.
                        this.rememberTotal();
                        return true;
                    } catch (err) {
                        if (requestNumber === this.requestNumber) this.showError('We could not reach the server. Please check your connection and try again.', nextStep !== null);
                        return false;
                    } finally {
                        if (requestNumber === this.requestNumber) this.loading = false;
                    }
                },

                async continueGuestSheet() {
                    const required = [
                        ['guest_name', 'Your profile is missing a name. Update your profile to continue.'],
                        ['guest_place_of_origin', 'Your profile is missing an address. Update your profile to continue.'],
                        ['guest_nationality', 'Your profile is missing a nationality. Update your profile to continue.'],
                        ['guest_valid_id', 'Your profile is missing a valid ID type. Update your profile to continue.'],
                        ['guest_phone', 'Your profile is missing a contact number. Update your profile to continue.'],
                        ['guest_email', 'Your profile is missing an email address. Update your profile to continue.'],
                    ];

                    for (const [field, message] of required) {
                        if (!String(this.form[field] || '').trim()) {
                            this.showError(message);
                            return;
                        }
                    }

                    const ok = await this.refreshQuote(3);

                    if (! ok) {
                        return;
                    }

                    this.error = '';
                    this.step = 3;
                    this.$nextTick(() => {
                        this.$refs.review.focus();
                        this.$el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                },

                submitReservation(event) {
                    if (! this.form.policies_accepted) {
                        event.preventDefault();
                        this.showError('Please agree to the resort policies and rescheduling terms.');
                        return;
                    }

                    if (! this.quoted || this.loading) {
                        event.preventDefault();
                        this.showError('Confirm is available after the latest total is calculated.');
                    }
                },

                get maxCapacity() {
                    if (this.form.pax_tier) {
                        return Number(this.form.pax_tier);
                    }
                    return Number(bookingWizardConfig.maxCapacity || 1);
                },

                get maxCompanionSlots() {
                    return Math.max(0, this.maxCapacity - 1);
                },

                get canAddOtherGuest() {
                    return this.form.other_guest_names.length < this.maxCompanionSlots;
                },

                addOtherGuest() {
                    if (! this.canAddOtherGuest) {
                        return;
                    }

                    this.form.other_guest_names.push({ name: '', valid_id: '' });
                    this.changeGuestCount(Math.max(this.form.guestCount, this.form.other_guest_names.length + 1));
                },

                removeOtherGuest(index) {
                    this.form.other_guest_names.splice(index, 1);
                    this.changeGuestCount(Math.min(this.form.guestCount, this.form.other_guest_names.length + 1));
                },

                changeGuestCount(value) {
                    const next = Math.min(this.maxCapacity, Math.max(1, Number(value) || 1));
                    this.form.guestCount = next;
                    this.syncOtherGuestSlots();
                    this.quoted = false;
                    this.rememberTotal();

                    if (this.step >= 2 && this.form.check_in && this.form.check_out) {
                        this.refreshQuote();
                    }
                },

                /**
                 * Record the total the guest is looking at, and what moved it.
                 *
                 * Called after every edit and after every quote, so the two numbers
                 * in a change — the one before and the one after — are always two
                 * figures the guest was actually shown.
                 */
                rememberTotal() {
                    // Without both dates there is no booking to price, and the figure
                    // on the page is not a total at all — so there is nothing to
                    // compare a later total against, and nothing to explain.
                    if (! this.form.check_in || ! this.form.check_out) {
                        this.lastPriced = null;
                        this.changeNote = null;
                        return;
                    }

                    const priced = this.quoted ? this.price : this.estimate;
                    const now = {
                        ...priced,
                        total_amount: priced.total_amount || 0,
                        inputs: window.bookingPrice.inputsFrom(this.form),
                    };

                    this.changeNote = window.bookingPrice.priceChange(this.lastPriced, now);
                    this.lastPriced = now;
                },

                syncOtherGuestSlots() {
                    const maxSlots = Math.min(this.maxCompanionSlots, Math.max(0, this.form.guestCount - 1));

                    if (this.form.other_guest_names.length > maxSlots) {
                        this.form.other_guest_names = this.form.other_guest_names.slice(0, maxSlots);
                    }
                },

                showError(message, focus = true) {
                    this.error = message;
                    if (!focus) return;
                    this.$nextTick(() => {
                        this.$refs.errorSummary?.focus();
                    });
                },

                timeLabel(dateString) {
                    if (!dateString) return '';
                    return new Date(dateString.replace(' ', 'T')).toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit'});
                },

                formatDate(dateString) {
                    if (!dateString) return '';
                    const date = new Date(dateString.replace(/-/g, '/'));
                    return date.toLocaleString('en-US', {
                        weekday: 'short',
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric',
                        hour: 'numeric',
                        minute: '2-digit'
                    });
                },

                formatDay(dateString) {
                    if (!dateString) return '—';
                    const date = new Date(dateString.replace(/-/g, '/'));
                    return date.toLocaleString('en-US', {
                        weekday: 'short',
                        month: 'short',
                        day: 'numeric'
                    });
                },

                // Money is formatted by the shared helper (window.pesosFromCents,
                // defined in resources/js/money.js) so this preview and the saved
                // reservation cannot print the same amount two different ways.
                // The amounts from the pricing API are in cents.

                occupancyWindowLabel() {
                    if (!this.form.check_in || !this.form.check_out) return '';

                    const start = new Date(this.form.check_in.replace(/-/g, '/'));
                    const end = new Date(this.form.check_out.replace(/-/g, '/'));

                    start.setHours(start.getHours() - (this.form.early_check_in_hours || 0));
                    end.setHours(end.getHours() + (this.form.late_check_out_hours || 0));

                    const options = { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' };

                    return start.toLocaleString('en-US', options) + ' until ' + end.toLocaleString('en-US', options);
                },

                // One implementation of "how many nights is this", shared with the
                // price terms, so the label and the arithmetic cannot disagree.
                nightCount() {
                    return window.bookingPrice.stayNights(this.form.check_in, this.form.check_out);
                },

                get nightsLabel() {
                    if (bookingWizardConfig.isRoom && this.form.check_in && this.form.check_out
                        && this.form.check_in.slice(0, 10) === this.form.check_out.slice(0, 10)) {
                        return 'Same-day use';
                    }
                    const unit = (bookingWizardConfig.rates && bookingWizardConfig.rates.baseUnit) || 'per night';

                    if (unit !== 'per night') {
                        return unit;
                    }

                    const nights = this.nightCount();

                    return nights + (nights === 1 ? ' night' : ' nights');
                },

                get estimate() {
                    const rates = bookingWizardConfig.rates || {};
                    const nights = this.nightCount();
                    const perNight = (rates.baseUnit || 'per night') === 'per night';
                    const multiplier = perNight ? Math.max(1, nights) : 1;
                    const extraGuests = Math.max(0, (this.form.guestCount || 0) - (this.form.children_count || 0) - (rates.includedGuests || 0));
                    const base = (rates.baseCents || 0) * multiplier;
                    const extra = extraGuests * (rates.extraPersonCents || 0) * multiplier;
                    const early = (this.form.early_check_in_hours || 0) * (rates.earlyHourCents || 0);
                    const late = 0;
                    const extension = (this.form.hall_extension_hours || 0) * (rates.extensionHourCents || 0);

                    return {
                        base_amount: base,
                        extra_person_charge: extra,
                        early_check_in_charge: early,
                        late_check_out_charge: late,
                        hall_extension_charge: extension,
                        pet_fee: this.form.pets_included ? 50000 : 0,
                        total_amount: base + extra + early + late + extension + (this.form.pets_included ? 50000 : 0),
                    };
                },

                get displayPrice() {
                    return this.quoted ? this.price : this.estimate;
                },

                /**
                 * The lines behind the total, with their arithmetic — one
                 * definition for every place the wizard quotes a price.
                 */
                get terms() {
                    return window.bookingPrice.priceTerms(this.displayPrice, {
                        inputs: window.bookingPrice.inputsFrom(this.form),
                        labels: bookingWizardConfig.priceLabels,
                        words: bookingWizardConfig.guestWords,
                        unit: (bookingWizardConfig.rates && bookingWizardConfig.rates.baseUnit) || 'per night',
                        includedGuests: (bookingWizardConfig.rates && bookingWizardConfig.rates.includedGuests) || 0,
                    });
                },

                /** Whether the figure on the page is ours or the resort's. */
                get totalLabel() {
                    return this.quoted ? 'Confirmed total' : 'Estimate from published rates';
                },

                get changeNoteHeadline() {
                    if (! this.changeNote) {
                        return '';
                    }

                    const direction = this.changeNote.delta > 0 ? 'up' : 'down';

                    return 'Was ' + pesosFromCents(this.changeNote.from) + ' — '
                        + direction + ' ' + pesosFromCents(Math.abs(this.changeNote.delta));
                },

                get changeNoteReasons() {
                    return this.changeNote && this.changeNote.reasons.length
                        ? this.changeNote.reasons.join(' · ')
                        : '';
                },

                get suggestedDown() {
                    return Math.ceil((this.displayPrice.total_amount || 0) / 2);
                },

                get stepLabel() {
                    const titles = { 1: 'Plan your stay', 2: 'Guest details', 3: 'Confirm reservation' };

                    return 'Step ' + (this.step + 1) + ' of 4: ' + (titles[this.step] || 'Plan your stay');
                },

                get bottomBarLabel() {
                    if (this.loading) {
                        return 'Updating…';
                    }

                    if (this.step === 1) {
                        return 'Continue';
                    }

                    if (this.step === 2) {
                        return 'Review total';
                    }

                    return 'Confirm';
                },

                bottomBarAction() {
                    if (this.step === 1) {
                        this.checkAvailability();
                        return;
                    }

                    if (this.step === 2) {
                        this.continueGuestSheet();
                        return;
                    }

                    if (this.step === 3 && this.quoted && this.$refs.confirmForm) {
                        this.$refs.confirmForm.requestSubmit();
                    }
                }
            }));
        });
    </script>
    @endpush
</x-customer-layout>
