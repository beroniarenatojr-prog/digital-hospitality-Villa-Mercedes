<x-customer-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-12">
        <h1 class="text-3xl font-bold text-text mb-2">Book Your Reservation</h1>
        <p class="text-muted-foreground mb-8">Complete the details below to reserve {{ $facility->name }}.</p>

        <div x-data="bookingWizard()" class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
            
            {{-- Facility Summary --}}
            <div class="bg-muted/30 p-6 border-b border-border flex flex-col sm:flex-row gap-6 items-start sm:items-center">
                @if($facility->galleryImages->count() > 0)
                    <img src="{{ Storage::url($facility->galleryImages->first()->path) }}" alt="{{ $facility->name }}" class="w-24 h-24 rounded-lg object-cover border border-border shadow-sm">
                @else
                    <div class="w-24 h-24 rounded-lg bg-muted flex items-center justify-center border border-border">
                        <svg class="w-8 h-8 text-muted-foreground opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                @endif
                <div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-secondary text-text mb-1">{{ $facility->facilityType->name }}</span>
                    <h2 class="text-2xl font-bold text-text">{{ $facility->name }}</h2>
                    <p class="text-sm text-muted-foreground mt-1">Standard Capacity: {{ $facility->capacity }} pax | Maximum: {{ $facility->effectiveMaxCapacity() }} pax</p>
                </div>
            </div>

            {{-- Wizard Body --}}
            <div class="p-6 md:p-8">
                
                {{-- Step 1: Inputs --}}
                <div x-show="step === 1">
                    <h3 class="text-xl font-bold text-text mb-6 flex items-center gap-2">
                        <span class="bg-primary text-primary-foreground w-6 h-6 rounded-full inline-flex items-center justify-center text-sm">1</span>
                        Reservation Details
                    </h3>
                    
                    <form @submit.prevent="checkAvailability" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-input-label for="check_in" value="Check-in" />
                                <x-text-input id="check_in" x-model="form.check_in" type="datetime-local" class="mt-1 block w-full" required />
                            </div>
                            <div>
                                <x-input-label for="check_out" value="Check-out" />
                                <x-text-input id="check_out" x-model="form.check_out" type="datetime-local" class="mt-1 block w-full" required />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="guestCount" value="Guest Count" />
                            <x-text-input id="guestCount" x-model.number="form.guestCount" type="number" min="1" :max="$facility->effectiveMaxCapacity()" class="mt-1 block w-full md:w-1/2" required />
                            <p class="text-xs text-muted-foreground mt-1">Maximum capacity allowed is {{ $facility->effectiveMaxCapacity() }}. Additional charges may apply for exceeding standard capacity ({{ $facility->capacity }}).</p>
                        </div>

                        {{-- Error Message Alert --}}
                        <div x-show="error" x-transition class="p-4 bg-danger-light border border-danger/20 rounded-lg flex items-start gap-3 mt-4" style="display: none;">
                            <svg class="w-5 h-5 text-danger shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <p class="text-sm text-danger font-medium" x-text="error"></p>
                        </div>

                        <div class="flex justify-end pt-4">
                            <x-primary-button type="submit" x-bind:disabled="loading" class="w-full sm:w-auto">
                                <span x-show="!loading">Check Availability & Price</span>
                                <span x-show="loading">Checking...</span>
                            </x-primary-button>
                        </div>
                    </form>
                </div>

                {{-- Step 2: Review & Submit --}}
                <div x-show="step === 2" style="display: none;">
                    <h3 class="text-xl font-bold text-text mb-6 flex items-center gap-2">
                        <span class="bg-primary text-primary-foreground w-6 h-6 rounded-full inline-flex items-center justify-center text-sm">2</span>
                        Review & Confirm
                    </h3>
                    
                    <div class="bg-background border border-border rounded-xl p-6 mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <p class="text-sm text-muted-foreground">Check-in</p>
                                <p class="font-bold text-text" x-text="formatDate(form.check_in)"></p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Check-out</p>
                                <p class="font-bold text-text" x-text="formatDate(form.check_out)"></p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Guest Count</p>
                                <p class="font-bold text-text" x-text="form.guestCount + ' pax'"></p>
                            </div>
                        </div>
                        
                        <hr class="border-border mb-6">
                        
                        <h4 class="text-lg font-bold text-text mb-4">Price Breakdown</h4>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Base Amount</span>
                                <span class="font-medium text-text">₱<span x-text="formatCurrency(price.base_amount)"></span></span>
                            </div>
                            <div class="flex justify-between" x-show="price.extra_person_charge > 0">
                                <span class="text-muted-foreground">Extra Person Charge</span>
                                <span class="font-medium text-text">₱<span x-text="formatCurrency(price.extra_person_charge)"></span></span>
                            </div>
                            <div class="flex justify-between pt-4 border-t border-border mt-4">
                                <span class="font-bold text-text text-base">Estimated Total</span>
                                <span class="font-extrabold text-primary text-lg">₱<span x-text="formatCurrency(price.total_amount)"></span></span>
                            </div>
                        </div>
                    </div>

                    <form action="{{ route('customer.reservations.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="facility_id" value="{{ $facility->id }}">
                        <input type="hidden" name="check_in" x-model="form.check_in">
                        <input type="hidden" name="check_out" x-model="form.check_out">
                        <input type="hidden" name="guestCount" x-model="form.guestCount">
                        
                        <div class="flex flex-col sm:flex-row gap-3 justify-end">
                            <x-secondary-button type="button" @click="step = 1" class="w-full sm:w-auto justify-center">
                                Edit Details
                            </x-secondary-button>
                            <x-primary-button type="submit" class="w-full sm:w-auto justify-center">
                                Submit Reservation
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
                error: null,
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
                
                async checkAvailability() {
                    this.error = null;
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
                            this.error = data.message || 'An error occurred during calculation.';
                        } else {
                            this.price = data.data;
                            this.step = 2;
                        }
                    } catch (err) {
                        this.error = 'Failed to connect to the server. Please try again.';
                    } finally {
                        this.loading = false;
                    }
                },
                
                formatDate(dateString) {
                    if (!dateString) return '';
                    const date = new Date(dateString);
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

