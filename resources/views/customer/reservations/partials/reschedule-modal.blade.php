<div x-data="{
    show: false,
    checkIn: '{{ $reservation->check_in->format('Y-m-d\TH:i') }}',
    checkOut: '{{ $reservation->check_out->format('Y-m-d\TH:i') }}',
    guestCount: {{ $reservation->guest_count }},
    earlyCheckInHours: {{ $reservation->early_check_in_hours }},
    lateCheckOutHours: {{ $reservation->late_check_out_hours }},
    previewLoading: false,
    previewError: null,
    previewData: null,

    open() {
        this.show = true;
        this.$nextTick(() => this.$refs.checkIn.focus());
    },
    close() {
        this.show = false;
        this.$refs.trigger.focus();
    },
    trapFocus(event) {
        const controls = [...this.$refs.dialog.querySelectorAll('input:not([type=hidden]), button, select, textarea, a[href]')]
            .filter(control => !control.disabled && control.getClientRects().length);
        const first = controls[0];
        const last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last?.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first?.focus();
        }
    },

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
                    early_check_in_hours: this.earlyCheckInHours,
                    late_check_out_hours: this.lateCheckOutHours,
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
}" x-init="calculatePreview(); $watch('show', value => document.body.classList.toggle('overflow-y-hidden', value))" @keydown.escape.window="if (show) close()">
    <x-secondary-button x-ref="trigger" @click="open()" type="button" class="w-full">
        Reschedule
    </x-secondary-button>

    <div x-ref="dialog" x-show="show" @keydown.tab="trapFocus($event)" style="display: none;" class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="reschedule-modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/50 transition-opacity" @click="close()" aria-hidden="true"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="relative inline-block align-bottom bg-surface border border-border rounded-2xl px-5 pt-5 pb-5 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full sm:p-7">
                <form action="{{ route('customer.reservations.reschedule', $reservation) }}" method="POST">
                    @csrf
                    @method('PATCH')

                    <input type="hidden" name="facility_id" value="{{ $reservation->facility_id }}">

                    <div>
                        <h3 class="font-heading text-2xl font-semibold text-text" id="reschedule-modal-title">
                            Reschedule reservation
                        </h3>
                        <p class="mt-2 text-sm text-text-muted">
                            Update your dates or guest count. Changes are subject to availability and may change the total.
                            Online reschedule stays open until {{ (int) config('resort.policy.reschedule_cutoff_hours', 48) }} hours before check-in.
                        </p>

                        <div class="mt-6 grid grid-cols-1 gap-y-5 gap-x-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="check_in" value="New check-in" />
                                <x-text-input type="datetime-local" x-model="checkIn" @change="calculatePreview" name="check_in" id="check_in" x-ref="checkIn" required class="mt-1 block w-full" />
                            </div>

                            <div>
                                <x-input-label for="check_out" value="New check-out" />
                                <x-text-input type="datetime-local" x-model="checkOut" @change="calculatePreview" name="check_out" id="check_out" required class="mt-1 block w-full" />
                            </div>

                            <div class="sm:col-span-2">
                                <x-input-label for="guestCount" value="New guest count" />
                                <x-text-input type="number" x-model="guestCount" @input.debounce.500ms="calculatePreview" name="guestCount" id="guestCount" min="1" required class="mt-1 block w-full" />
                            </div>

                            <div>
                                <x-input-label for="early_check_in_hours" value="Early check-in (hours)" />
                                <x-text-input type="number" x-model.number="earlyCheckInHours" @input.debounce.500ms="calculatePreview" name="early_check_in_hours" id="early_check_in_hours" min="0" max="12" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-input-label for="late_check_out_hours" value="Late check-out (hours)" />
                                <x-text-input type="number" x-model.number="lateCheckOutHours" @input.debounce.500ms="calculatePreview" name="late_check_out_hours" id="late_check_out_hours" min="0" max="12" class="mt-1 block w-full" />
                            </div>
                        </div>

                        <div class="mt-8 border-t border-border pt-6">
                            <h4 class="font-semibold text-text mb-4">Preview changes</h4>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="bg-background p-4 rounded-xl border border-border">
                                    <h5 class="text-xs font-semibold uppercase tracking-wider text-text-muted mb-2">Current</h5>
                                    <dl class="space-y-1 text-sm">
                                        <div class="flex justify-between">
                                            <dt class="text-text-muted">Check-in</dt>
                                            <dd class="text-text">{{ $reservation->check_in->format('M d, Y h:i A') }}</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-text-muted">Check-out</dt>
                                            <dd class="text-text">{{ $reservation->check_out->format('M d, Y h:i A') }}</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-text-muted">Guests</dt>
                                            <dd class="text-text">{{ $reservation->guest_count }}</dd>
                                        </div>
                                        <div class="flex justify-between pt-2 mt-2 border-t border-border">
                                            <dt class="font-medium text-text">Total</dt>
                                            <dd class="font-bold text-text">₱{{ number_format($reservation->total_price, 2) }}</dd>
                                        </div>
                                    </dl>
                                </div>

                                <div class="bg-primary-light p-4 rounded-xl border border-primary/20 relative">
                                    <h5 class="text-xs font-semibold uppercase tracking-wider text-primary mb-2">New</h5>

                                    <div x-show="previewLoading" class="absolute inset-0 bg-primary-light/80 flex items-center justify-center rounded-xl">
                                        <svg class="animate-spin h-5 w-5 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>

                                    <dl class="space-y-1 text-sm">
                                        <div class="flex justify-between">
                                            <dt class="text-text-muted">Check-in</dt>
                                            <dd class="text-text" x-text="checkIn ? new Date(checkIn).toLocaleString() : '...'"></dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-text-muted">Check-out</dt>
                                            <dd class="text-text" x-text="checkOut ? new Date(checkOut).toLocaleString() : '...'"></dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-text-muted">Guests</dt>
                                            <dd class="text-text" x-text="guestCount"></dd>
                                        </div>

                                        <template x-if="previewData">
                                            <div class="flex justify-between pt-2 mt-2 border-t border-primary/20">
                                                <dt class="font-medium text-text">Total</dt>
                                                <dd class="font-bold text-primary">
                                                    ₱<span x-text="(Number(previewData.total_amount) / 100).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                                </dd>
                                            </div>
                                        </template>

                                        <template x-if="previewError">
                                            <div class="mt-2 text-danger text-xs font-medium" x-text="previewError"></div>
                                        </template>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex flex-col-reverse sm:flex-row gap-3">
                        <x-secondary-button @click="close()" type="button" class="w-full sm:w-auto">
                            Cancel
                        </x-secondary-button>
                        <x-primary-button
                            type="submit"
                            class="w-full sm:flex-1"
                            x-bind:disabled="previewLoading || previewError !== null || !previewData"
                        >
                            Confirm reschedule
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
