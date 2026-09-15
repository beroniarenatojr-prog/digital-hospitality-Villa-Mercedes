<x-customer-layout>
    @php
        $coverUrl = $facility->galleryImages->first()?->url();
        $checkInFrom = \Carbon\Carbon::createFromFormat('H:i', config('resort.stay.overnight_hours.check_in'))->format('g:i A');
        $checkOutUntil = \Carbon\Carbon::createFromFormat('H:i', config('resort.stay.overnight_hours.check_out'))->format('g:i A');
        $isHall = $facility->isFunctionHall();
        $isFlyerPackage = $facility->isFlyerPackage();
        $paxTiers = $facility->paxTiers();
        $selectedPaxTier = old('pax_tier', $prefill['pax_tier'] ?? ($paxTiers[0] ?? null));
        $guestCap = $selectedPaxTier ? min($facility->effectiveMaxCapacity(), (int) $selectedPaxTier) : $facility->effectiveMaxCapacity();
        $peopleLabel = $isHall ? 'attendees' : 'guests';
        $peopleSingular = $isHall ? 'attendee' : 'guest';
        $arrivalLabel = $isHall ? 'Event start' : 'Arrival';
        $departureLabel = $isHall ? 'Event end' : 'Departure';
        $peopleHeading = $isHall ? 'How many attendees?' : 'How many guests?';
        $extraHoursHint = $isHall ? 'Optional extra hours.' : 'Optional. Most guests can skip this.';
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
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/70">Secure reservation</p>
            <h1 class="mt-3 font-heading text-3xl sm:text-4xl lg:text-5xl font-semibold tracking-tight">Complete your reservation</h1>
            <p class="mt-3 text-white/75 max-w-xl">Check availability and submit your selection. Standard bookings require a 50% QR deposit within the saved payment deadline. Staff verify the transfer to confirm your booking. Function halls require a staff quotation and approval.</p>
        </div>
    </div>

    <div x-data="bookingWizard()" data-booking-wizard>
        <div class="sticky top-16 z-30 bg-background/95 backdrop-blur-md border-b border-border">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <ol class="flex items-center gap-2 sm:gap-4 overflow-x-auto" aria-label="Reservation progress">
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

                    <div x-show="error" x-cloak class="mb-6">
                        <x-alert type="danger" title="We couldn't continue">
                            <span x-text="error"></span>
                        </x-alert>
                    </div>

                    <div x-show="step === 1">
                        <form @submit.prevent="checkAvailability" class="space-y-6">
                            <div class="bg-surface border border-border rounded-2xl shadow-sm p-5 sm:p-7">
                                <h2 class="font-heading text-xl sm:text-2xl font-semibold text-text">When are you coming?</h2>
                                <p class="mt-1.5 text-sm text-text-muted">
                                    @if($isFlyerPackage)
                                        This {{ strtolower($facility->stay_category?->label() ?? 'package') }} is {{ $facility->stayScheduleLabel() }}. Choose a date and we will apply those hours.
                                    @elseif($isHall)
                                        Typical hall hours follow the day, night, or overnight presets below.
                                    @else
                                        Check-in from {{ $checkInFrom }} · Check-out until {{ $checkOutUntil }}
                                    @endif
                                </p>

                                @unless($isFlyerPackage)
                                <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <button type="button" @click="applyPreset('day_use')"
                                            :class="preset === 'day_use' ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-border hover:border-primary/50'"
                                            class="text-left rounded-xl border-2 px-4 py-3 transition focus:outline-none">
                                        <span class="block font-semibold text-text text-sm">Day use</span>
                                        <span class="block text-xs text-text-muted mt-0.5">{{ \Carbon\Carbon::parse(config('resort.stay.day_use_hours.start'))->format('g A') }}–{{ \Carbon\Carbon::parse(config('resort.stay.day_use_hours.end'))->format('g A') }}, same day</span>
                                    </button>
                                    <button type="button" @click="applyPreset('night_use')"
                                            :class="preset === 'night_use' ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-border hover:border-primary/50'"
                                            class="text-left rounded-xl border-2 px-4 py-3 transition focus:outline-none">
                                        <span class="block font-semibold text-text text-sm">Night use</span>
                                        <span class="block text-xs text-text-muted mt-0.5">{{ \Carbon\Carbon::parse(config('resort.stay.night_use_hours.start'))->format('g A') }}–{{ \Carbon\Carbon::parse(config('resort.stay.night_use_hours.end'))->format('g A') }}, no sleeping over</span>
                                    </button>
                                    <button type="button" @click="applyPreset('overnight')"
                                            :class="preset === 'overnight' ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-border hover:border-primary/50'"
                                            class="text-left rounded-xl border-2 px-4 py-3 transition focus:outline-none">
                                        <span class="block font-semibold text-text text-sm">Overnight</span>
                                        <span class="block text-xs text-text-muted mt-0.5">{{ \Carbon\Carbon::parse(config('resort.stay.overnight_hours.check_in'))->format('g A') }} to {{ \Carbon\Carbon::parse(config('resort.stay.overnight_hours.check_out'))->format('g A') }} next day</span>
                                    </button>
                                </div>
                                @endunless

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-6">
                                    <div>
                                        <x-input-label for="check_in" :value="$arrivalLabel" />
                                        <div class="relative mt-2">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <svg class="h-5 w-5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            </div>
                                            <x-text-input id="check_in" x-ref="checkInPicker" type="text" class="pl-10 block w-full" required placeholder="Choose date and time" />
                                        </div>
                                    </div>
                                    <div>
                                        <x-input-label for="check_out" :value="$departureLabel" />
                                        <div class="relative mt-2">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <svg class="h-5 w-5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            </div>
                                            <x-text-input id="check_out" x-ref="checkOutPicker" type="text" class="pl-10 block w-full" required placeholder="Choose date and time" />
                                        </div>
                                    </div>
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
                                <p x-show="form.guestCount > {{ $facility->capacity }}" x-cloak class="mt-3 text-sm text-accent font-medium">
                                    An extra {{ $peopleSingular }} charge will be added for <span x-text="form.guestCount - {{ $facility->capacity }}"></span> {{ $peopleSingular }}(s) above {{ $facility->capacity }}.
                                </p>
                                @endunless
                            </div>

                            @unless($isFlyerPackage)
                            <div class="bg-surface border border-border rounded-2xl shadow-sm p-5 sm:p-7">
                                <button type="button" @click="extraHours = ! extraHours" :aria-expanded="extraHours.toString()"
                                        class="flex items-center gap-2 text-left font-semibold text-primary hover:underline focus:outline-none">
                                    <svg class="w-4 h-4 transition-transform" :class="extraHours && 'rotate-90'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                    Need to arrive early or leave late?
                                </button>
                                <p class="text-sm text-text-muted mt-1 ms-6">{{ $extraHoursHint }}</p>

                                <div x-show="extraHours" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-5">
                                    <div>
                                        <x-input-label for="early_check_in_hours" value="Extra hours before arrival" />
                                        <x-text-input id="early_check_in_hours" x-model.number="form.early_check_in_hours" @input="quoted = false" type="number" min="0" :max="config('resort.stay.max_additional_hours', 12)" class="mt-2 block w-full" />
                                        <p class="text-sm text-text-muted mt-2">
                                            @if($earlyCheckInRate)
                                                ₱{{ number_format($earlyCheckInRate->amount, 2) }} per hour.
                                            @else
                                                Charged only if an early check-in rate is set for this space.
                                            @endif
                                        </p>
                                    </div>
                                    <div>
                                        <x-input-label for="late_check_out_hours" value="Extra hours after departure" />
                                        <x-text-input id="late_check_out_hours" x-model.number="form.late_check_out_hours" @input="quoted = false" type="number" min="0" :max="config('resort.stay.max_additional_hours', 12)" class="mt-2 block w-full" />
                                        <p class="text-sm text-text-muted mt-2">
                                            @if($lateCheckOutRate)
                                                ₱{{ number_format($lateCheckOutRate->amount, 2) }} per hour.
                                            @else
                                                Charged only if a late check-out rate is set for this space.
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>
                            @endunless

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
                            <p class="text-sm text-text-muted text-center sm:text-right">After submission, open your reservation to pay the required QR deposit.</p>
                        </form>
                    </div>

                    <div x-show="step === 2" x-cloak>
                        <form @submit.prevent="continueGuestSheet" class="space-y-6">
                            <div class="bg-surface border border-border rounded-2xl shadow-sm p-5 sm:p-7">
                                <div x-ref="guestSheet" tabindex="-1" class="focus:outline-none">
                                    <h2 class="font-heading text-xl sm:text-2xl font-semibold text-text">Guest information sheet</h2>
                                    <p class="mt-1.5 text-sm text-text-muted">The front desk uses this for hotel records and security. Name, phone, and email identify the booking; ID type, origin, nationality, and gender help staff verify guests on arrival.</p>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-6">
                                    <div>
                                        <x-input-label for="guest_name" value="Name" />
                                        <x-text-input id="guest_name" x-model="form.guest_name" type="text" class="mt-2 block w-full" required maxlength="255" autocomplete="name" />
                                        <x-input-error class="mt-1" :messages="$errors->get('guest_name')" />
                                    </div>
                                    <div>
                                        <x-input-label for="guest_gender" value="Gender" />
                                        <x-text-input
                                            id="guest_gender"
                                            type="text"
                                            class="mt-2 block w-full read-only:bg-white read-only:focus:border-border read-only:focus:ring-0"
                                            :value="auth()->user()?->gender?->label() ?? ''"
                                            readonly
                                            tabindex="-1"
                                        />
                                    </div>
                                    <div>
                                        <x-input-label for="guest_place_of_origin" value="Address" />
                                        <x-text-input id="guest_place_of_origin" x-model="form.guest_place_of_origin" type="text" class="mt-2 block w-full" required maxlength="255" placeholder="Street, barangay, city, province" autocomplete="street-address" />
                                        <x-input-error class="mt-1" :messages="$errors->get('guest_place_of_origin')" />
                                    </div>
                                    <div>
                                        <x-input-label for="guest_nationality" value="Nationality" />
                                        <x-text-input id="guest_nationality" x-model="form.guest_nationality" type="text" class="mt-2 block w-full" required maxlength="100" />
                                        <x-input-error class="mt-1" :messages="$errors->get('guest_nationality')" />
                                    </div>
                                    <div>
                                        <x-input-label for="guest_company" value="Company Name/Agency (optional)" />
                                        <x-text-input id="guest_company" x-model="form.guest_company" type="text" class="mt-2 block w-full" maxlength="255" />
                                        <x-input-error class="mt-1" :messages="$errors->get('guest_company')" />
                                    </div>
                                    <div>
                                        <x-input-label for="guest_valid_id" value="Valid ID type" />
                                        <select id="guest_valid_id" x-model="form.guest_valid_id" required class="mt-2 border-border focus:border-primary focus:ring-primary rounded-lg shadow-sm block w-full">
                                            <option value="">Select</option>
                                            @foreach(\App\Enums\GuestIdType::cases() as $idType)
                                                <option value="{{ $idType->value }}" @selected(old('guest_valid_id', auth()->user()?->valid_id_type?->value ?? '') === $idType->value)>{{ $idType->label() }}</option>
                                            @endforeach
                                        </select>
                                        <p class="mt-1 text-xs text-text-muted">Shown only to staff. Bring this ID so the desk can verify you at check-in.</p>
                                        <x-input-error class="mt-1" :messages="$errors->get('guest_valid_id')" />
                                    </div>
                                    <div>
                                        <x-input-label for="guest_phone" value="Contact #" />
                                        <x-text-input id="guest_phone" x-model="form.guest_phone" type="tel" class="mt-2 block w-full" required maxlength="32" placeholder="0917 123 4567" autocomplete="tel" />
                                        <x-input-error class="mt-1" :messages="$errors->get('guest_phone')" />
                                    </div>
                                    <div>
                                        <x-input-label for="guest_email" value="Email Add" />
                                        <x-text-input id="guest_email" x-model="form.guest_email" type="email" class="mt-2 block w-full" required maxlength="255" autocomplete="email" />
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
                                                Add companions up to this space's maximum of {{ $facility->effectiveMaxCapacity() }} guests, including the primary guest.
                                            </p>
                                        </div>
                                        <button type="button" @click="addOtherGuest()" x-show="canAddOtherGuest"
                                                class="shrink-0 inline-flex items-center px-3 py-1.5 rounded-lg border border-border text-xs font-semibold text-text bg-surface hover:bg-background">
                                            Add guest name
                                        </button>
                                    </div>
                                    <div class="mt-3 space-y-2" x-show="form.other_guest_names.length > 0">
                                        <template x-for="(name, index) in form.other_guest_names" :key="index">
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-semibold text-text-muted w-5" x-text="(index + 1) + '.'"></span>
                                                <input type="text" x-model="form.other_guest_names[index]" maxlength="255"
                                                       class="border-border focus:border-primary focus:ring-primary rounded-lg shadow-sm block w-full">
                                                <button type="button" @click="removeOtherGuest(index)" class="text-xs font-semibold text-text-muted hover:text-danger shrink-0">
                                                    Remove
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                    <p class="mt-2 text-xs text-text-muted" x-show="!canAddOtherGuest && maxCompanionSlots > 0 && form.other_guest_names.length >= maxCompanionSlots">
                                        Maximum other guest names for this space reached.
                                    </p>
                                    <x-input-error class="mt-1" :messages="$errors->get('other_guest_names')" />
                                </div>

                                <div class="mt-6">
                                    <x-input-label for="special_requests" value="Preferences & additional requests (optional)" />
                                    <textarea id="special_requests" x-model="form.special_requests" rows="3" maxlength="1000"
                                              class="mt-2 border-border focus:border-primary focus:ring-primary rounded-lg shadow-sm block w-full"
                                              placeholder="Anything staff should know for your stay? (celebration, accessibility, timing notes)"></textarea>
                                    <p class="mt-1 text-xs text-text-muted">Avoid sharing medical details unless needed to arrange a requested accommodation.</p>
                                    <x-input-error class="mt-1" :messages="$errors->get('special_requests')" />
                                </div>

                                <div class="mt-6 rounded-xl border border-border bg-background/60 px-4 py-3">
                                    <div class="flex items-start gap-3 text-sm text-text">
                                        <input id="policies_accepted" type="checkbox" x-model="form.policies_accepted" required
                                               class="mt-1 rounded border-border text-primary focus:ring-primary">
                                        <p>
                                            <label for="policies_accepted" class="cursor-pointer">I agree to the</label>
                                            <a href="{{ route('terms') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-primary hover:underline">resort policies and cancellation terms</a>.
                                        </p>
                                    </div>
                                    <x-input-error class="mt-1" :messages="$errors->get('policies_accepted')" />
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
                                        <span x-show="form.early_check_in_hours > 0"><span x-text="form.early_check_in_hours"></span> before arrival</span>
                                        <span x-show="form.early_check_in_hours > 0 && form.late_check_out_hours > 0">, </span>
                                        <span x-show="form.late_check_out_hours > 0"><span x-text="form.late_check_out_hours"></span> after departure</span>
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
                                    <div class="flex justify-between items-center">
                                        <span class="text-text-muted">Base amount</span>
                                        <span class="font-medium text-text">₱<span x-text="formatCurrency(displayPrice.base_amount)"></span></span>
                                    </div>
                                    <div class="flex justify-between items-center" x-show="displayPrice.early_check_in_charge > 0">
                                        <span class="text-text-muted">Early arrival (<span x-text="form.early_check_in_hours"></span> hr)</span>
                                        <span class="font-medium text-text">₱<span x-text="formatCurrency(displayPrice.early_check_in_charge)"></span></span>
                                    </div>
                                    <div class="flex justify-between items-center" x-show="displayPrice.late_check_out_charge > 0">
                                        <span class="text-text-muted">Late departure (<span x-text="form.late_check_out_hours"></span> hr)</span>
                                        <span class="font-medium text-text">₱<span x-text="formatCurrency(displayPrice.late_check_out_charge)"></span></span>
                                    </div>
                                    <div class="flex justify-between items-center" x-show="displayPrice.extra_person_charge > 0">
                                        <span class="text-text-muted">Extra {{ $peopleLabel }} (<span x-text="Math.max(0, form.guestCount - {{ $facility->capacity }})"></span>)</span>
                                        <span class="font-medium text-text">₱<span x-text="formatCurrency(displayPrice.extra_person_charge)"></span></span>
                                    </div>
                                    <div class="flex justify-between items-center pt-3 border-t border-border">
                                        <span class="font-display font-bold text-text text-lg">Total</span>
                                        <span class="font-display font-extrabold text-primary text-2xl">₱<span x-text="formatCurrency(displayPrice.total_amount)"></span></span>
                                    </div>
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-text-muted">Required deposit for standard bookings (50%)</span>
                                        <span class="font-medium text-text">₱<span x-text="formatCurrency(suggestedDown)"></span></span>
                                    </div>
                                    <p class="text-sm text-accent font-medium" x-show="loading" x-cloak>Updating price…</p>
                                    <p class="text-sm text-text-muted" x-show="!quoted && !loading" x-cloak>Confirm is available after the latest total is calculated.</p>
                                </div>
                            </div>

                            <x-alert type="info" title="After submission" class="mt-6">
                                Your saved reservation shows its deposit and deadline. Upload proof of your QR transfer there. Custom function halls wait for staff quotation and approval.
                            </x-alert>

                            <form action="{{ route('customer.reservations.store') }}" method="POST" class="mt-6" x-ref="confirmForm">
                                @csrf
                                <input type="hidden" name="facility_id" value="{{ $facility->id }}">
                                <input type="hidden" name="check_in" x-model="form.check_in">
                                <input type="hidden" name="check_out" x-model="form.check_out">
                                <input type="hidden" name="guestCount" x-model="form.guestCount">
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
                                <template x-for="(name, index) in form.other_guest_names" :key="'store-other-' + index">
                                    <input type="hidden" name="other_guest_names[]" :value="name">
                                </template>
                                <input type="hidden" name="special_requests" x-model="form.special_requests">
                                <input type="hidden" name="policies_accepted" :value="form.policies_accepted ? '1' : ''">

                                <div class="flex flex-col-reverse sm:flex-row gap-3">
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
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-text-muted">Check-in</p>
                                    <p class="mt-1 text-sm font-semibold text-text" x-text="form.check_in ? formatDay(form.check_in) : '—'"></p>
                                    <p class="text-[11px] text-text-muted mt-0.5">from {{ $checkInFrom }}</p>
                                </div>
                                <div class="rounded-xl bg-background p-3.5">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-text-muted">Check-out</p>
                                    <p class="mt-1 text-sm font-semibold text-text" x-text="form.check_out ? formatDay(form.check_out) : '—'"></p>
                                    <p class="text-[11px] text-text-muted mt-0.5">until {{ $checkOutUntil }}</p>
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

                            <div class="mt-5 pt-5 border-t border-border space-y-3 text-sm" x-show="form.check_in && form.check_out">
                                <div class="flex justify-between gap-3">
                                    <span class="text-text-muted">Base amount</span>
                                    <span class="font-medium text-text">₱<span x-text="formatCurrency(displayPrice.base_amount)"></span></span>
                                </div>
                                <div class="flex justify-between gap-3" x-show="displayPrice.extra_person_charge > 0">
                                    <span class="text-text-muted">Extra {{ $peopleLabel }}</span>
                                    <span class="font-medium text-text">₱<span x-text="formatCurrency(displayPrice.extra_person_charge)"></span></span>
                                </div>
                                <div class="flex justify-between gap-3" x-show="displayPrice.early_check_in_charge > 0">
                                    <span class="text-text-muted">Early arrival</span>
                                    <span class="font-medium text-text">₱<span x-text="formatCurrency(displayPrice.early_check_in_charge)"></span></span>
                                </div>
                                <div class="flex justify-between gap-3" x-show="displayPrice.late_check_out_charge > 0">
                                    <span class="text-text-muted">Late departure</span>
                                    <span class="font-medium text-text">₱<span x-text="formatCurrency(displayPrice.late_check_out_charge)"></span></span>
                                </div>
                                <div class="flex justify-between gap-3 pt-3 border-t border-border">
                                    <span class="font-semibold text-text">Total</span>
                                    <span class="font-heading text-xl font-semibold text-primary">₱<span x-text="formatCurrency(displayPrice.total_amount)"></span></span>
                                </div>
                                <p class="text-xs text-text-muted" x-show="!quoted && form.check_in" x-cloak>Estimate from published rates. The confirmed total appears after you check availability.</p>
                            </div>
                            <p class="mt-4 text-sm text-text-muted" x-show="!form.check_in || !form.check_out" x-cloak>Pick {{ strtolower($arrivalLabel) }} and {{ strtolower($departureLabel) }} to see a total.</p>

                            <div class="mt-5 rounded-2xl bg-primary text-white p-4" x-show="form.check_in && form.check_out && displayPrice.total_amount > 0">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-white/75">Down payment (50%)</span>
                                    <span class="font-bold tabular-nums">₱<span x-text="formatCurrency(suggestedDown)"></span></span>
                                </div>
                                <div class="flex items-center justify-between text-sm mt-2.5 pt-2.5 border-t border-white/15">
                                    <span class="text-white/75">Balance on arrival</span>
                                    <span class="font-semibold tabular-nums">₱<span x-text="formatCurrency(displayPrice.total_amount - suggestedDown)"></span></span>
                                </div>
                                <p class="mt-3 text-[11px] text-white/70">Standard bookings: pay the saved 50% deposit before its deadline. Remaining balance payable at the resort upon arrival.</p>
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

        <div class="lg:hidden fixed inset-x-0 bottom-0 z-50 border-t border-border bg-surface/95 backdrop-blur-md pr-24 pl-4 py-3" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px));">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-text-muted">Total</p>
                    <p class="font-heading text-xl font-semibold text-primary truncate">₱<span x-text="formatCurrency(displayPrice.total_amount)"></span></p>
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
            'prefill' => $prefill,
            'continueAfterAvailability' => (bool) ($prefill['availability_checked'] ?? false),
            'startStep' => $errors->any() ? 2 : 1,
            'maxCapacity' => (int) $guestCap,
            'includedCapacity' => (int) ($selectedPaxTier ?: $facility->capacity),
            'paxTiers' => $paxTiers,
            'isFlyerPackage' => $isFlyerPackage,
            'packageStay' => $isFlyerPackage ? [
                'start' => $facility->stayStartTimeString(),
                'end' => $facility->stayEndTimeString(),
                'overnight' => (bool) $facility->stay_spans_midnight,
            ] : null,
            'guestPrefill' => array_merge(
                \App\Support\GuestProfilePrefill::forUser(auth()->user()),
                [
                    'guest_name' => old('guest_name', auth()->user()?->name ?? ''),
                    'guest_place_of_origin' => old('guest_place_of_origin', auth()->user()?->address ?? ''),
                    'guest_nationality' => old('guest_nationality', auth()->user()?->nationality ?? ''),
                    'guest_company' => old('guest_company', auth()->user()?->company ?? ''),
                    'guest_valid_id' => old('guest_valid_id', auth()->user()?->valid_id_type?->value ?? ''),
                    'guest_phone' => old('guest_phone', auth()->user()?->phone ?? ''),
                    'guest_email' => old('guest_email', auth()->user()?->email ?? ''),
                    'other_guest_names' => array_values((array) old('other_guest_names', [])),
                    'special_requests' => old('special_requests', ''),
                    'policies_accepted' => (bool) old('policies_accepted', false),
                ]
            ),
            'rates' => [
                'baseCents' => $baseRate ? (int) round(((float) $baseRate->amount) * 100) : 0,
                'baseUnit' => $baseRate?->unit ?? 'per night',
                'extraPersonCents' => $extraPersonRate ? (int) round(((float) $extraPersonRate->amount) * 100) : 0,
                'earlyHourCents' => $earlyCheckInRate ? (int) round(((float) $earlyCheckInRate->amount) * 100) : 0,
                'lateHourCents' => $lateCheckOutRate ? (int) round(((float) $lateCheckOutRate->amount) * 100) : 0,
                'includedGuests' => (int) $facility->capacity,
            ],
            'presets' => [
                'day_use' => [
                    'start' => config('resort.stay.day_use_hours.start'),
                    'end' => config('resort.stay.day_use_hours.end'),
                    'overnight' => false,
                ],
                'night_use' => [
                    'start' => config('resort.stay.night_use_hours.start'),
                    'end' => config('resort.stay.night_use_hours.end'),
                    'overnight' => false,
                ],
                'overnight' => [
                    'start' => config('resort.stay.overnight_hours.check_in'),
                    'end' => config('resort.stay.overnight_hours.check_out'),
                    'overnight' => true,
                ],
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
                preset: '',
                extraHours: false,
                quoted: false,
                snappingStay: false,
                form: {
                    check_in: '',
                    check_out: '',
                    guestCount: bookingWizardConfig.defaultGuestCount,
                    pax_tier: (bookingWizardConfig.prefill && bookingWizardConfig.prefill.pax_tier) || (bookingWizardConfig.paxTiers && bookingWizardConfig.paxTiers[0]) || null,
                    early_check_in_hours: 0,
                    late_check_out_hours: 0,
                    guest_name: (bookingWizardConfig.guestPrefill && bookingWizardConfig.guestPrefill.guest_name) || '',
                    guest_place_of_origin: (bookingWizardConfig.guestPrefill && bookingWizardConfig.guestPrefill.guest_place_of_origin) || '',
                    guest_nationality: (bookingWizardConfig.guestPrefill && bookingWizardConfig.guestPrefill.guest_nationality) || '',
                    guest_company: (bookingWizardConfig.guestPrefill && bookingWizardConfig.guestPrefill.guest_company) || '',
                    guest_valid_id: (bookingWizardConfig.guestPrefill && bookingWizardConfig.guestPrefill.guest_valid_id) || '',
                    guest_phone: (bookingWizardConfig.guestPrefill && bookingWizardConfig.guestPrefill.guest_phone) || '',
                    guest_email: (bookingWizardConfig.guestPrefill && bookingWizardConfig.guestPrefill.guest_email) || '',
                    other_guest_names: (bookingWizardConfig.guestPrefill && bookingWizardConfig.guestPrefill.other_guest_names) || [],
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

                init() {
                    const fpConfig = {
                        enableTime: true,
                        dateFormat: "Y-m-d H:i",
                        minDate: "today",
                        altInput: true,
                        altFormat: "D, F j, Y at h:i K",
                        theme: "light"
                    };

                    this.checkInPicker = flatpickr(this.$refs.checkInPicker, {
                        ...fpConfig,
                        onChange: (selectedDates, dateStr) => {
                            this.form.check_in = dateStr;
                            this.quoted = false;
                            this.checkOutPicker.set('minDate', dateStr);
                            if (bookingWizardConfig.isFlyerPackage) {
                                this.applyPackageStay();
                            }
                        }
                    });

                    this.checkOutPicker = flatpickr(this.$refs.checkOutPicker, {
                        ...fpConfig,
                        onChange: (selectedDates, dateStr) => {
                            this.form.check_out = dateStr;
                            this.quoted = false;
                        }
                    });

                    this.applyPrefill();
                    if (bookingWizardConfig.isFlyerPackage && !this.form.check_in) {
                        this.applyPackageStay();
                    }
                    if (bookingWizardConfig.continueAfterAvailability && this.form.check_in && this.form.check_out) {
                        this.$nextTick(() => this.checkAvailability());
                    }
                },

                selectPaxTier(tier) {
                    this.form.pax_tier = Number(tier);
                    bookingWizardConfig.maxCapacity = Number(tier);
                    if (this.form.guestCount > tier) {
                        this.form.guestCount = tier;
                    }
                    this.quoted = false;
                },

                applyPackageStay() {
                    const stay = bookingWizardConfig.packageStay;
                    if (!stay || this.snappingStay) return;
                    this.snappingStay = true;
                    try {
                        this.applyPresetTimes(stay.start, stay.end, stay.overnight);
                    } finally {
                        this.snappingStay = false;
                    }
                },

                applyPresetTimes(start, end, overnight) {
                    const base = this.form.check_in
                        ? new Date(this.form.check_in.replace(/-/g, '/'))
                        : new Date(Date.now() + 86400000);

                    const [startHour, startMinute] = start.split(':').map(Number);
                    const [endHour, endMinute] = end.split(':').map(Number);

                    const startDate = new Date(base);
                    startDate.setHours(startHour, startMinute, 0, 0);

                    const endDate = new Date(base);
                    endDate.setHours(endHour, endMinute, 0, 0);

                    if (overnight || endDate <= startDate) {
                        endDate.setDate(endDate.getDate() + 1);
                    }

                    this.checkInPicker.setDate(startDate, true);
                    this.checkOutPicker.setDate(endDate, true);
                },

                applyPrefill() {
                    const prefill = bookingWizardConfig.prefill || {};
                    const guestPrefill = bookingWizardConfig.guestPrefill || {};

                    if (guestPrefill.guest_valid_id) {
                        this.form.guest_valid_id = guestPrefill.guest_valid_id;
                    }

                    if (prefill.guest_count) {
                        this.form.guestCount = prefill.guest_count;
                    }

                    if (!prefill.check_in || !prefill.check_out) return;

                    this.checkInPicker.setDate(prefill.check_in, true);
                    this.checkOutPicker.setDate(prefill.check_out, true);
                    this.preset = prefill.preset || '';
                },

                applyPreset(kind) {
                    const preset = bookingWizardConfig.presets[kind];
                    if (!preset) return;

                    this.preset = kind;
                    this.applyPresetTimes(preset.start, preset.end, preset.overnight);
                },

                async checkAvailability() {
                    if (!this.form.check_in || !this.form.check_out) {
                        this.showError('Please choose both an arrival and a departure date.');
                        return;
                    }

                    const ok = await this.refreshQuote();

                    if (ok) {
                        this.step = 2;
                        this.$nextTick(() => {
                            this.$refs.guestSheet.focus();
                            this.$el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        });
                    }
                },

                async refreshQuote() {
                    if (!this.form.check_in || !this.form.check_out) {
                        return false;
                    }

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
                                pax_tier: this.form.pax_tier,
                                early_check_in_hours: this.form.early_check_in_hours,
                                late_check_out_hours: this.form.late_check_out_hours
                            })
                        });

                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            this.showError(data.message || 'We could not work out a price for those dates. Please try different ones.');
                            return false;
                        }

                        this.price = data.data;
                        this.quoted = true;
                        return true;
                    } catch (err) {
                        this.showError('We could not reach the server. Please check your connection and try again.');
                        return false;
                    } finally {
                        this.loading = false;
                    }
                },

                async continueGuestSheet() {
                    const required = [
                        ['guest_name', 'Please enter the guest name.'],
                        ['guest_place_of_origin', 'Please enter the address.'],
                        ['guest_nationality', 'Please enter the nationality.'],
                        ['guest_valid_id', 'Please select a valid ID type.'],
                        ['guest_phone', 'Please enter a contact number.'],
                        ['guest_email', 'Please enter an email address.'],
                    ];

                    for (const [field, message] of required) {
                        if (!String(this.form[field] || '').trim()) {
                            this.showError(message);
                            return;
                        }
                    }

                    if (! this.form.policies_accepted) {
                        this.showError('Please agree to the resort policies and cancellation terms.');
                        return;
                    }

                    const ok = await this.refreshQuote();

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

                    this.form.other_guest_names.push('');
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

                    if (this.step >= 2 && this.form.check_in && this.form.check_out) {
                        this.refreshQuote();
                    }
                },

                syncOtherGuestSlots() {
                    const maxSlots = Math.min(this.maxCompanionSlots, Math.max(0, this.form.guestCount - 1));

                    if (this.form.other_guest_names.length > maxSlots) {
                        this.form.other_guest_names = this.form.other_guest_names.slice(0, maxSlots);
                    }
                },

                showError(message) {
                    this.error = message;
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

                formatCurrency(cents) {
                    return (cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },

                occupancyWindowLabel() {
                    if (!this.form.check_in || !this.form.check_out) return '';

                    const start = new Date(this.form.check_in.replace(/-/g, '/'));
                    const end = new Date(this.form.check_out.replace(/-/g, '/'));

                    start.setHours(start.getHours() - (this.form.early_check_in_hours || 0));
                    end.setHours(end.getHours() + (this.form.late_check_out_hours || 0));

                    const options = { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' };

                    return start.toLocaleString('en-US', options) + ' until ' + end.toLocaleString('en-US', options);
                },

                nightCount() {
                    if (!this.form.check_in || !this.form.check_out) return 0;

                    const start = new Date(this.form.check_in.replace(/-/g, '/'));
                    const end = new Date(this.form.check_out.replace(/-/g, '/'));
                    const from = Date.UTC(start.getFullYear(), start.getMonth(), start.getDate());
                    const to = Date.UTC(end.getFullYear(), end.getMonth(), end.getDate());

                    return Math.max(1, Math.round((to - from) / 86400000));
                },

                get nightsLabel() {
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
                    const extraGuests = Math.max(0, (this.form.guestCount || 0) - (rates.includedGuests || 0));
                    const base = (rates.baseCents || 0) * multiplier;
                    const extra = extraGuests * (rates.extraPersonCents || 0) * multiplier;
                    const early = (this.form.early_check_in_hours || 0) * (rates.earlyHourCents || 0);
                    const late = (this.form.late_check_out_hours || 0) * (rates.lateHourCents || 0);

                    return {
                        base_amount: base,
                        extra_person_charge: extra,
                        early_check_in_charge: early,
                        late_check_out_charge: late,
                        total_amount: base + extra + early + late,
                    };
                },

                get displayPrice() {
                    return this.quoted ? this.price : this.estimate;
                },

                get suggestedDown() {
                    return Math.ceil(Math.round((this.displayPrice.total_amount || 0) * 100) / 2) / 100;
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

                    if (this.quoted && this.$refs.confirmForm) {
                        this.$refs.confirmForm.requestSubmit();
                    }
                }
            }));
        });
    </script>
    @endpush
</x-customer-layout>
