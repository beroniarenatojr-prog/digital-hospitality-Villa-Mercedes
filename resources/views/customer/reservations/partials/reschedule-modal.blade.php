<div x-data="{ 
    show: false,
    checkIn: '{{ $reservation->check_in->format('Y-m-d\TH:i') }}',
    checkOut: '{{ $reservation->check_out->format('Y-m-d\TH:i') }}',
    guestCount: {{ $reservation->guest_count }},
    previewLoading: false,
    previewError: null,
    previewData: null,
    
    async calculatePreview() {
        this.previewLoading = true;
        this.previewError = null;
        this.previewData = null;
        
        try {
            const response = await fetch('{{ route('customer.reservations.calculate-reschedule', $reservation) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    check_in: this.checkIn,
                    check_out: this.checkOut,
                    guestCount: this.guestCount,
                    facility_id: {{ $reservation->facility_id }}
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.previewData = data.data;
            } else {
                this.previewError = data.message || 'Error calculating preview.';
            }
        } catch (e) {
            this.previewError = 'An error occurred while calculating the preview.';
        } finally {
            this.previewLoading = false;
        }
    }
}" x-init="calculatePreview()">
    <button @click="show = true" type="button" class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
        Reschedule
    </button>

    <!-- Modal -->
    <div x-show="show" style="display: none;" class="fixed z-10 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="show = false" aria-hidden="true"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal panel -->
            <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="relative inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full sm:p-6">
                
                <form action="{{ route('customer.reservations.reschedule', $reservation) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    
                    <input type="hidden" name="facility_id" value="{{ $reservation->facility_id }}">

                    <div>
                        <div class="mt-3 text-center sm:mt-0 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Reschedule Reservation
                            </h3>
                            <div class="mt-2 text-sm text-gray-500">
                                Update your reservation dates or guest count. Changes are subject to availability and may result in a price difference.
                            </div>
                        </div>

                        <div class="mt-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
                            <!-- Check-in -->
                            <div>
                                <label for="check_in" class="block text-sm font-medium text-gray-700">New Check-in</label>
                                <div class="mt-1">
                                    <input type="datetime-local" x-model="checkIn" @change="calculatePreview" name="check_in" id="check_in" required class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                </div>
                            </div>

                            <!-- Check-out -->
                            <div>
                                <label for="check_out" class="block text-sm font-medium text-gray-700">New Check-out</label>
                                <div class="mt-1">
                                    <input type="datetime-local" x-model="checkOut" @change="calculatePreview" name="check_out" id="check_out" required class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                </div>
                            </div>

                            <!-- Guest Count -->
                            <div class="sm:col-span-2">
                                <label for="guestCount" class="block text-sm font-medium text-gray-700">New Guest Count</label>
                                <div class="mt-1">
                                    <input type="number" x-model="guestCount" @input.debounce.500ms="calculatePreview" name="guestCount" id="guestCount" min="1" required class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                </div>
                            </div>
                        </div>

                        <!-- Comparison / Preview Section -->
                        <div class="mt-8 border-t border-gray-200 pt-6">
                            <h4 class="text-md font-medium text-gray-900 mb-4">Preview Changes</h4>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <!-- Current -->
                                <div class="bg-gray-50 p-4 rounded-md border border-gray-200">
                                    <h5 class="text-sm font-medium text-gray-500 mb-2 uppercase tracking-wider">Current</h5>
                                    <dl class="space-y-1 text-sm">
                                        <div class="flex justify-between">
                                            <dt class="text-gray-500">Check-in:</dt>
                                            <dd class="text-gray-900">{{ $reservation->check_in->format('M d, Y h:i A') }}</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-gray-500">Check-out:</dt>
                                            <dd class="text-gray-900">{{ $reservation->check_out->format('M d, Y h:i A') }}</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-gray-500">Guests:</dt>
                                            <dd class="text-gray-900">{{ $reservation->guest_count }}</dd>
                                        </div>
                                        <div class="flex justify-between pt-2 mt-2 border-t border-gray-200">
                                            <dt class="font-medium text-gray-900">Total:</dt>
                                            <dd class="font-bold text-gray-900">₱{{ number_format($reservation->total_price, 2) }}</dd>
                                        </div>
                                    </dl>
                                </div>

                                <!-- New -->
                                <div class="bg-primary-50 p-4 rounded-md border border-primary-200 relative">
                                    <h5 class="text-sm font-medium text-primary-700 mb-2 uppercase tracking-wider">New</h5>
                                    
                                    <!-- Loading overlay -->
                                    <div x-show="previewLoading" class="absolute inset-0 bg-primary-50 bg-opacity-75 flex items-center justify-center rounded-md">
                                        <svg class="animate-spin h-5 w-5 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>
                                    
                                    <dl class="space-y-1 text-sm">
                                        <div class="flex justify-between">
                                            <dt class="text-primary-600">Check-in:</dt>
                                            <dd class="text-primary-900" x-text="checkIn ? new Date(checkIn).toLocaleString() : '...'"></dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-primary-600">Check-out:</dt>
                                            <dd class="text-primary-900" x-text="checkOut ? new Date(checkOut).toLocaleString() : '...'"></dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-primary-600">Guests:</dt>
                                            <dd class="text-primary-900" x-text="guestCount"></dd>
                                        </div>
                                        
                                        <template x-if="previewData">
                                            <div class="flex justify-between pt-2 mt-2 border-t border-primary-200">
                                                <dt class="font-medium text-primary-900">Total:</dt>
                                                <dd class="font-bold text-primary-900">
                                                    ₱<span x-text="parseFloat(previewData.total_amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                                </dd>
                                            </div>
                                        </template>

                                        <template x-if="previewError">
                                            <div class="mt-2 text-red-600 text-xs font-medium" x-text="previewError"></div>
                                        </template>
                                    </dl>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="mt-8 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                        <button type="submit" 
                                :disabled="previewLoading || previewError !== null || !previewData"
                                :class="(previewLoading || previewError !== null || !previewData) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-primary-700'"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:col-start-2 sm:text-sm transition-opacity">
                            Confirm Reschedule
                        </button>
                        <button @click="show = false" type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:col-start-1 sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
