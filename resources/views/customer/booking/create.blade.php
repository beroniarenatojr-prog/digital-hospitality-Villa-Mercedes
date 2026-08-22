<x-customer-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-12 pb-24">
        <h1 class="font-display text-3xl md:text-5xl font-extrabold text-text mb-3 tracking-tight">Book Your Reservation</h1>
        <p class="text-lg text-muted-foreground mb-10">Complete the details below to reserve {{ $facility->name }}.</p>

        <div x-data="bookingWizard()" class="glass rounded-3xl overflow-hidden relative">
            
            {{-- Facility Summary --}}
            <div class="bg-primary/5 p-6 md:p-8 border-b border-border/50 flex flex-col sm:flex-row gap-6 items-start sm:items-center">
                @if($facility->galleryImages->count() > 0)
                    <img src="{{ Storage::url($facility->galleryImages->first()->path) }}" alt="{{ $facility->name }}" class="w-24 h-24 md:w-32 md:h-32 rounded-2xl object-cover border border-white/50 shadow-md">
                @else
                    <div class="w-24 h-24 md:w-32 md:h-32 rounded-2xl bg-muted/50 flex items-center justify-center border border-white/50 shadow-md">
                        <svg class="w-8 h-8 text-muted-foreground opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                @endif
                <div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-accent text-white mb-2 shadow-sm">{{ $facility->facilityType->name }}</span>
                    <h2 class="font-display text-2xl md:text-3xl font-bold text-text">{{ $facility->name }}</h2>
                    <p class="text-sm text-muted-foreground mt-2 flex items-center gap-2">
                        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        Up to {{ $facility->effectiveMaxCapacity() }} pax
                    </p>
                </div>
            </div>

            {{-- Wizard Body --}}
            <div class="p-6 md:p-10 relative">
                
                {{-- Loader Overlay --}}
                <div x-show="loading" x-transition.opacity class="absolute inset-0 z-10 bg-surface/60 backdrop-blur-sm flex flex-col items-center justify-center rounded-b-3xl">
                    <svg class="animate-spin -ml-1 mr-3 h-10 w-10 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="mt-4 font-medium text-primary">Calculating availability...</span>
                </div>

                {{-- Step 1: Inputs --}}
                <div x-show="step === 1" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                    <h3 class="font-display text-xl md:text-2xl font-bold text-text mb-8 flex items-center gap-3">
                        <span class="bg-primary text-white w-8 h-8 rounded-full inline-flex items-center justify-center text-sm shadow-md">1</span>
                        Reservation Details
                    </h3>
                    
                    <form @submit.prevent="checkAvailability" class="space-y-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div>
                                <x-input-label for="check_in" value="Check-in Date & Time" />
                                <div class="relative mt-2">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </div>
                                    <x-text-input id="check_in" x-ref="checkInPicker" type="text" class="pl-10 block w-full bg-white/50" required placeholder="Select check-in" />
                                </div>
                            </div>
                            <div>
                                <x-input-label for="check_out" value="Check-out Date & Time" />
                                <div class="relative mt-2">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </div>
                                    <x-text-input id="check_out" x-ref="checkOutPicker" type="text" class="pl-10 block w-full bg-white/50" required placeholder="Select check-out" />
                                </div>
                            </div>
                        </div>

                        <div>
                            <x-input-label for="guestCount" value="Guest Count" />
                            <x-text-input id="guestCount" x-model.number="form.guestCount" type="number" min="1" :max="$facility->effectiveMaxCapacity()" class="mt-2 block w-full md:w-1/2 bg-white/50" required />
                            <p class="text-sm text-muted-foreground mt-2">Maximum capacity allowed is <span class="font-bold text-text">{{ $facility->effectiveMaxCapacity() }}</span>. Additional charges apply for exceeding standard capacity ({{ $facility->capacity }}).</p>
                        </div>

                        <div class="flex justify-end pt-6 border-t border-border/50">
                            <x-primary-button type="submit" class="w-full sm:w-auto shadow-lg hover:-translate-y-0.5 transition-all duration-300">
                                Check Availability & Price
                                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                            </x-primary-button>
                        </div>
                    </form>
                </div>

                {{-- Step 2: Review & Submit --}}
                <div x-show="step === 2" style="display: none;" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                    <h3 class="font-display text-xl md:text-2xl font-bold text-text mb-8 flex items-center gap-3">
                        <span class="bg-primary text-white w-8 h-8 rounded-full inline-flex items-center justify-center text-sm shadow-md">2</span>
                        Review & Confirm
                    </h3>
                    
                    <div class="bg-white/60 backdrop-blur-md border border-white rounded-2xl p-6 md:p-8 mb-8 shadow-sm">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <div>
                                <p class="text-sm text-muted-foreground uppercase tracking-wider font-semibold mb-1">Check-in</p>
                                <p class="font-bold text-text text-lg" x-text="formatDate(form.check_in)"></p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground uppercase tracking-wider font-semibold mb-1">Check-out</p>
                                <p class="font-bold text-text text-lg" x-text="formatDate(form.check_out)"></p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground uppercase tracking-wider font-semibold mb-1">Guest Count</p>
                                <p class="font-bold text-text text-lg" x-text="form.guestCount + ' pax'"></p>
                            </div>
                        </div>
                        
                        <div class="bg-background rounded-xl p-6 border border-border">
                            <h4 class="font-display text-lg font-bold text-text mb-4">Price Breakdown</h4>
                            <div class="space-y-4 text-sm md:text-base">
                                <div class="flex justify-between items-center">
                                    <span class="text-muted-foreground">Base Amount</span>
                                    <span class="font-medium text-text">₱<span x-text="formatCurrency(price.base_amount)"></span></span>
                                </div>
                                <div class="flex justify-between items-center" x-show="price.extra_person_charge > 0">
                                    <span class="text-muted-foreground">Extra Person Charge <span class="text-xs ml-2 px-2 py-0.5 bg-accent/10 text-accent rounded-full" x-text="'+' + (form.guestCount - {{ $facility->capacity }}) + ' pax'"></span></span>
                                    <span class="font-medium text-text">₱<span x-text="formatCurrency(price.extra_person_charge)"></span></span>
                                </div>
                                <div class="flex justify-between items-center pt-4 border-t border-border mt-2">
                                    <span class="font-display font-bold text-text text-lg md:text-xl">Estimated Total</span>
                                    <span class="font-display font-extrabold text-primary text-2xl md:text-3xl">₱<span x-text="formatCurrency(price.total_amount)"></span></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form action="{{ route('customer.reservations.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="facility_id" value="{{ $facility->id }}">
                        <input type="hidden" name="check_in" x-model="form.check_in">
                        <input type="hidden" name="check_out" x-model="form.check_out">
                        <input type="hidden" name="guestCount" x-model="form.guestCount">
                        
                        <div class="flex flex-col sm:flex-row gap-4 justify-end">
                            <x-secondary-button type="button" @click="step = 1" class="w-full sm:w-auto justify-center hover:bg-gray-100 transition-colors">
                                Edit Details
                            </x-secondary-button>
                            <x-primary-button type="submit" class="w-full sm:w-auto justify-center shadow-lg hover:-translate-y-0.5 transition-all duration-300">
                                Confirm & Submit Reservation
                            </x-primary-button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('bookingWizard', () => ({
                step: 1,
                loading: false,
                form: {
                    check_in: '',
                    check_out: '',
                    guestCount: {{ $facility->capacity }}
                },
                price: {
                    base_amount: 0,
                    extra_person_charge: 0,
                    total_amount: 0
                },
                
                init() {
                    // Initialize Flatpickr
                    const fpConfig = {
                        enableTime: true,
                        dateFormat: "Y-m-d H:i",
                        minDate: "today",
                        altInput: true,
                        altFormat: "F j, Y h:i K",
                        disableMobile: true, // Use beautiful flatpickr even on mobile
                        theme: "light"
                    };

                    const checkInPicker = flatpickr(this.$refs.checkInPicker, {
                        ...fpConfig,
                        onChange: (selectedDates, dateStr) => {
                            this.form.check_in = dateStr;
                            checkOutPicker.set('minDate', dateStr);
                        }
                    });

                    const checkOutPicker = flatpickr(this.$refs.checkOutPicker, {
                        ...fpConfig,
                        onChange: (selectedDates, dateStr) => {
                            this.form.check_out = dateStr;
                        }
                    });
                },
                
                async checkAvailability() {
                    if(!this.form.check_in || !this.form.check_out) {
                        this.showError('Please select both check-in and check-out dates.');
                        return;
                    }

                    this.loading = true;
                    
                    try {
                        const response = await fetch("{{ route('customer.booking.calculate', $facility) }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(this.form)
                        });
                        
                        const data = await response.json();
                        
                        if (!response.ok || !data.success) {
                            this.showError(data.message || 'An error occurred during calculation.');
                        } else {
                            this.price = data.data;
                            this.step = 2;
                        }
                    } catch (err) {
                        this.showError('Failed to connect to the server. Please try again.');
                    } finally {
                        this.loading = false;
                    }
                },

                showError(message) {
                    Toastify({
                        text: message,
                        duration: 5000,
                        close: true,
                        gravity: "top", 
                        position: "right",
                        style: {
                            background: "#e11d48", // Danger color
                            color: "#fff",
                            borderRadius: "12px",
                            boxShadow: "0 10px 15px -3px rgba(0, 0, 0, 0.1)"
                        }
                    }).showToast();
                },
                
                formatDate(dateString) {
                    if (!dateString) return '';
                    // Convert basic Y-m-d H:i string to Date object
                    // Note: flatpickr uses local time, Safari might struggle with Y-m-d H:i, so replace - with /
                    const safeDateString = dateString.replace(/-/g, '/');
                    const date = new Date(safeDateString);
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
                }
            }));
        });
    </script>
    @endpush
</x-customer-layout>
