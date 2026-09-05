<x-customer-layout>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-12 pb-24">

        <a href="{{ route('facilities.show', $facility) }}" class="inline-flex items-center gap-1 text-sm font-medium text-text-muted hover:text-primary mb-6">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
            Back to {{ $facility->name }}
        </a>

        <h1 class="font-display text-3xl md:text-4xl font-extrabold text-text mb-2 tracking-tight">Book {{ $facility->name }}</h1>
        <p class="text-text-muted mb-8">Checking the price is free — you only confirm on the next step.</p>

        <div x-data="bookingWizard()" class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden relative">

            {{-- Progress --}}
            <div class="px-6 md:px-8 pt-6">
                <div class="flex items-center gap-3" aria-label="Booking progress">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-full inline-flex items-center justify-center text-sm font-bold"
                              :class="step === 1 ? 'bg-primary text-white' : 'bg-success text-white'">
                            <template x-if="step === 1"><span>1</span></template>
                            <template x-if="step > 1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            </template>
                        </span>
                        <span class="text-sm font-semibold" :class="step === 1 ? 'text-text' : 'text-text-muted'">Your details</span>
                    </div>
                    <div class="flex-1 h-0.5 bg-border rounded">
                        <div class="h-full bg-primary rounded transition-all duration-300" :style="step === 1 ? 'width:0%' : 'width:100%'"></div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-full inline-flex items-center justify-center text-sm font-bold"
                              :class="step === 2 ? 'bg-primary text-white' : 'bg-muted text-text-muted'">2</span>
                        <span class="text-sm font-semibold" :class="step === 2 ? 'text-text' : 'text-text-muted'">Review</span>
                    </div>
                </div>
            </div>

            {{-- Facility Summary --}}
            <div class="m-6 md:m-8 mb-0 md:mb-0 bg-background rounded-xl p-4 flex gap-4 items-center">
                @if($facility->galleryImages->count() > 0)
                    <img src="{{ $facility->galleryImages->first()->url() }}" alt="" class="w-20 h-20 rounded-lg object-cover shrink-0">
                @else
                    <div class="w-20 h-20 rounded-lg bg-muted flex items-center justify-center shrink-0">
                        <svg class="w-7 h-7 text-text-muted opacity-50" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                @endif
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-accent">{{ $facility->facilityType->name }}</p>
                    <h2 class="font-display text-lg font-bold text-text truncate">{{ $facility->name }}</h2>
                    <p class="text-sm text-text-muted mt-0.5">Fits up to {{ $facility->effectiveMaxCapacity() }} guests</p>
                </div>
            </div>

            {{-- Wizard Body --}}
            <div class="p-6 md:p-8 relative">

                {{-- Loader Overlay --}}
                <div x-show="loading" x-cloak x-transition.opacity class="absolute inset-0 z-10 bg-surface/80 backdrop-blur-sm flex flex-col items-center justify-center">
                    <svg class="animate-spin h-10 w-10 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="mt-4 font-medium text-primary">Checking availability…</span>
                </div>

                {{-- Inline error, so the message is not missed if the toast is --}}
                <div x-show="error" x-cloak class="mb-6">
                    <x-alert type="danger" title="We couldn't continue">
                        <span x-text="error"></span>
                    </x-alert>
                </div>

                {{-- Step 1: Inputs --}}
                <div x-show="step === 1">
                    <form @submit.prevent="checkAvailability" class="space-y-8">

                        <fieldset>
                            <legend class="text-lg font-bold text-text">When are you coming?</legend>
                            <p class="text-sm text-text-muted mt-1 mb-4">
                                Start with one of the usual visit types — it fills in the times for you. You can still adjust them after.
                            </p>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
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

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-6">
                                <div>
                                    <x-input-label for="check_in" value="Arrival" />
                                    <div class="relative mt-2">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <svg class="h-5 w-5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        </div>
                                        <x-text-input id="check_in" x-ref="checkInPicker" type="text" class="pl-10 block w-full" required placeholder="Choose date and time" />
                                    </div>
                                </div>
                                <div>
                                    <x-input-label for="check_out" value="Departure" />
                                    <div class="relative mt-2">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <svg class="h-5 w-5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        </div>
                                        <x-text-input id="check_out" x-ref="checkOutPicker" type="text" class="pl-10 block w-full" required placeholder="Choose date and time" />
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="pt-6 border-t border-border">
                            <legend class="text-lg font-bold text-text">How many guests?</legend>
                            <p class="text-sm text-text-muted mt-1 mb-4">
                                Included in the base price: {{ $facility->capacity }} guests. Up to {{ $facility->effectiveMaxCapacity() }} can be accommodated, with an extra charge per additional guest.
                            </p>
                            <div class="flex items-center gap-3">
                                <button type="button" @click="form.guestCount = Math.max(1, form.guestCount - 1)"
                                        class="w-11 h-11 rounded-lg border border-border text-text hover:bg-background focus:outline-none focus:ring-2 focus:ring-primary text-xl font-semibold"
                                        aria-label="Remove one guest">&minus;</button>
                                <x-text-input id="guestCount" x-model.number="form.guestCount" type="number" min="1" :max="$facility->effectiveMaxCapacity()" class="w-24 text-center" required />
                                <button type="button" @click="form.guestCount = Math.min({{ $facility->effectiveMaxCapacity() }}, form.guestCount + 1)"
                                        class="w-11 h-11 rounded-lg border border-border text-text hover:bg-background focus:outline-none focus:ring-2 focus:ring-primary text-xl font-semibold"
                                        aria-label="Add one guest">+</button>
                                <span class="text-sm text-text-muted">guests</span>
                            </div>
                            <p x-show="form.guestCount > {{ $facility->capacity }}" x-cloak class="mt-3 text-sm text-accent font-medium">
                                An extra guest charge will be added for <span x-text="form.guestCount - {{ $facility->capacity }}"></span> guest(s) above {{ $facility->capacity }}.
                            </p>
                        </fieldset>

                        {{-- Advanced options stay collapsed so most guests never have to think about them. --}}
                        <div class="pt-6 border-t border-border">
                            <button type="button" @click="extraHours = ! extraHours" :aria-expanded="extraHours.toString()"
                                    class="flex items-center gap-2 text-left font-semibold text-primary hover:underline focus:outline-none">
                                <svg class="w-4 h-4 transition-transform" :class="extraHours && 'rotate-90'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                Need to arrive early or leave late?
                            </button>
                            <p class="text-sm text-text-muted mt-1 ms-6">Optional. Most guests can skip this.</p>

                            <div x-show="extraHours" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-5">
                                <div>
                                    <x-input-label for="early_check_in_hours" value="Extra hours before arrival" />
                                    <x-text-input id="early_check_in_hours" x-model.number="form.early_check_in_hours" type="number" min="0" :max="config('resort.stay.max_additional_hours', 12)" class="mt-2 block w-full" />
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
                                    <x-text-input id="late_check_out_hours" x-model.number="form.late_check_out_hours" type="number" min="0" :max="config('resort.stay.max_additional_hours', 12)" class="mt-2 block w-full" />
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

                        <div class="pt-6 border-t border-border">
                            <x-primary-button type="submit" class="w-full !py-3 !text-base" x-bind:disabled="loading">
                                See price and availability
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                            </x-primary-button>
                            <p class="text-sm text-text-muted text-center mt-3">You won't be charged yet.</p>
                        </div>
                    </form>
                </div>

                {{-- Step 2: Review & Submit --}}
                <div x-show="step === 2" x-cloak>
                    <h3 class="text-lg font-bold text-text mb-1">Please review your booking</h3>
                    <p class="text-sm text-text-muted mb-6">Check the details below, then confirm.</p>

                    <dl class="bg-background rounded-xl p-5 grid grid-cols-1 sm:grid-cols-2 gap-5 mb-5">
                        <div>
                            <dt class="text-sm text-text-muted">Arrival</dt>
                            <dd class="font-semibold text-text mt-0.5" x-text="formatDate(form.check_in)"></dd>
                        </div>
                        <div>
                            <dt class="text-sm text-text-muted">Departure</dt>
                            <dd class="font-semibold text-text mt-0.5" x-text="formatDate(form.check_out)"></dd>
                        </div>
                        <div>
                            <dt class="text-sm text-text-muted">Guests</dt>
                            <dd class="font-semibold text-text mt-0.5" x-text="form.guestCount + ' guest' + (form.guestCount === 1 ? '' : 's')"></dd>
                        </div>
                        <div x-show="form.early_check_in_hours > 0 || form.late_check_out_hours > 0">
                            <dt class="text-sm text-text-muted">Extra hours</dt>
                            <dd class="font-semibold text-text mt-0.5">
                                <span x-show="form.early_check_in_hours > 0"><span x-text="form.early_check_in_hours"></span> before arrival</span>
                                <span x-show="form.early_check_in_hours > 0 && form.late_check_out_hours > 0">, </span>
                                <span x-show="form.late_check_out_hours > 0"><span x-text="form.late_check_out_hours"></span> after departure</span>
                            </dd>
                        </div>
                        <div class="sm:col-span-2 bg-primary/5 border border-primary/20 rounded-lg p-4">
                            <dt class="text-sm font-semibold text-text">The space is held for you from</dt>
                            <dd class="text-text mt-1" x-text="occupancyWindowLabel()"></dd>
                            <p class="text-sm text-text-muted mt-1">Extra hours are part of your reservation, so no one else can book this space during that time.</p>
                        </div>
                    </dl>

                    <div class="border border-border rounded-xl p-5 mb-6">
                        <h4 class="font-bold text-text mb-4">Price breakdown</h4>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between items-center">
                                <span class="text-text-muted">Base amount</span>
                                <span class="font-medium text-text">₱<span x-text="formatCurrency(price.base_amount)"></span></span>
                            </div>
                            <div class="flex justify-between items-center" x-show="price.early_check_in_charge > 0">
                                <span class="text-text-muted">Early arrival (<span x-text="form.early_check_in_hours"></span> hr)</span>
                                <span class="font-medium text-text">₱<span x-text="formatCurrency(price.early_check_in_charge)"></span></span>
                            </div>
                            <div class="flex justify-between items-center" x-show="price.late_check_out_charge > 0">
                                <span class="text-text-muted">Late departure (<span x-text="form.late_check_out_hours"></span> hr)</span>
                                <span class="font-medium text-text">₱<span x-text="formatCurrency(price.late_check_out_charge)"></span></span>
                            </div>
                            <div class="flex justify-between items-center" x-show="price.extra_person_charge > 0">
                                <span class="text-text-muted">Extra guests (<span x-text="Math.max(0, form.guestCount - {{ $facility->capacity }})"></span>)</span>
                                <span class="font-medium text-text">₱<span x-text="formatCurrency(price.extra_person_charge)"></span></span>
                            </div>
                            <div class="flex justify-between items-center pt-3 border-t border-border">
                                <span class="font-display font-bold text-text text-lg">Total</span>
                                <span class="font-display font-extrabold text-primary text-2xl">₱<span x-text="formatCurrency(price.total_amount)"></span></span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-info-light border-l-4 border-info rounded-r-lg p-4 mb-6">
                        <p class="text-sm font-semibold text-sky-900">What happens next</p>
                        <p class="text-sm text-sky-900 mt-1">
                            We'll hold this reservation and email you. You can pay the down payment online or at the resort from your bookings page.
                        </p>
                    </div>

                    <form action="{{ route('customer.reservations.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="facility_id" value="{{ $facility->id }}">
                        <input type="hidden" name="check_in" x-model="form.check_in">
                        <input type="hidden" name="check_out" x-model="form.check_out">
                        <input type="hidden" name="guestCount" x-model="form.guestCount">
                        <input type="hidden" name="early_check_in_hours" x-model="form.early_check_in_hours">
                        <input type="hidden" name="late_check_out_hours" x-model="form.late_check_out_hours">

                        <div class="flex flex-col-reverse sm:flex-row gap-3">
                            <x-secondary-button type="button" @click="step = 1; error = ''" class="sm:w-auto w-full">
                                Change details
                            </x-secondary-button>
                            @auth
                                <x-primary-button type="submit" class="flex-1 !py-3 !text-base">
                                    Confirm reservation
                                </x-primary-button>
                            @else
                                <x-primary-button tag="a" href="{{ route('login') }}" class="flex-1 !py-3 !text-base">
                                    Log in to confirm
                                </x-primary-button>
                            @endauth
                        </div>
                        @guest
                            <p class="text-sm text-text-muted text-center mt-3">
                                No account yet? <a href="{{ route('register') }}" class="text-primary font-medium hover:underline">Create one in a minute</a> — your details are kept.
                            </p>
                        @endguest
                    </form>
                </div>

            </div>
        </div>
    </div>

    @push('scripts')
    @php
        $bookingWizardConfig = [
            'defaultGuestCount' => (int) $facility->capacity,
            'calculateUrl' => route('customer.booking.calculate', $facility),
            'csrfToken' => csrf_token(),
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
                step: 1,
                loading: false,
                error: '',
                preset: '',
                extraHours: false,
                form: {
                    check_in: '',
                    check_out: '',
                    guestCount: bookingWizardConfig.defaultGuestCount,
                    early_check_in_hours: 0,
                    late_check_out_hours: 0
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
                        disableMobile: true,
                        theme: "light"
                    };

                    this.checkInPicker = flatpickr(this.$refs.checkInPicker, {
                        ...fpConfig,
                        onChange: (selectedDates, dateStr) => {
                            this.form.check_in = dateStr;
                            this.checkOutPicker.set('minDate', dateStr);
                        }
                    });

                    this.checkOutPicker = flatpickr(this.$refs.checkOutPicker, {
                        ...fpConfig,
                        onChange: (selectedDates, dateStr) => {
                            this.form.check_out = dateStr;
                        }
                    });
                },

                applyPreset(kind) {
                    const preset = bookingWizardConfig.presets[kind];
                    if (!preset) return;

                    this.preset = kind;

                    // Keep whichever day the guest already picked; otherwise start tomorrow.
                    const base = this.form.check_in
                        ? new Date(this.form.check_in.replace(/-/g, '/'))
                        : new Date(Date.now() + 86400000);

                    const [startHour, startMinute] = preset.start.split(':').map(Number);
                    const [endHour, endMinute] = preset.end.split(':').map(Number);

                    const start = new Date(base);
                    start.setHours(startHour, startMinute, 0, 0);

                    const end = new Date(base);
                    end.setHours(endHour, endMinute, 0, 0);

                    if (preset.overnight || end <= start) {
                        end.setDate(end.getDate() + 1);
                    }

                    this.checkInPicker.setDate(start, true);
                    this.checkOutPicker.setDate(end, true);
                },

                async checkAvailability() {
                    if (!this.form.check_in || !this.form.check_out) {
                        this.showError('Please choose both an arrival and a departure date.');
                        return;
                    }

                    this.loading = true;
                    this.error = '';

                    try {
                        const response = await fetch(bookingWizardConfig.calculateUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': bookingWizardConfig.csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(this.form)
                        });

                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            this.showError(data.message || 'We could not work out a price for those dates. Please try different ones.');
                        } else {
                            this.price = data.data;
                            this.step = 2;
                            this.$el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    } catch (err) {
                        this.showError('We could not reach the server. Please check your connection and try again.');
                    } finally {
                        this.loading = false;
                    }
                },

                showError(message) {
                    this.error = message;

                    Toastify({
                        text: message,
                        duration: 5000,
                        close: true,
                        gravity: "top",
                        position: "right",
                        style: {
                            background: "#e11d48",
                            color: "#fff",
                            borderRadius: "12px",
                            boxShadow: "0 10px 15px -3px rgba(0, 0, 0, 0.1)"
                        }
                    }).showToast();
                },

                formatDate(dateString) {
                    if (!dateString) return '';
                    // Safari cannot parse "Y-m-d H:i", so normalise the separators first.
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
                }
            }));
        });
    </script>
    @endpush
</x-customer-layout>
