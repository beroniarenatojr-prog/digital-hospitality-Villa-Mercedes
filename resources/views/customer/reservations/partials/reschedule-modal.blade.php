<div x-data="{
    show: false,
    checkIn: '{{ $reservation->check_in->format('Y-m-d\TH:i') }}',
    checkOut: '{{ $reservation->check_out->format('Y-m-d\TH:i') }}',
    guestCount: {{ $reservation->guest_count }},
    earlyCheckInHours: {{ $reservation->early_check_in_hours }},
    lateCheckOutHours: 0,
    previewAttempt: 0,
    previewTimer: null,
    previewLoading: false,
    previewError: null,
    previewData: null,

    open() {
        this.show = true;
        this.$nextTick(() => this.$refs.dialog.querySelector('input[type=date]')?.focus());
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
        const attempt = ++this.previewAttempt;
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
            if (attempt !== this.previewAttempt) return;

            if (data.success) {
                this.previewData = data.data;
            } else {
                this.previewError = Object.values(data.errors || {}).flat()[0] || data.message || 'Error calculating preview.';
            }
        } catch (e) {
            this.previewError = 'An error occurred while calculating the preview.';
        } finally {
            if (attempt === this.previewAttempt) this.previewLoading = false;
        }
    }
}" x-init="$watch('show', value => document.body.classList.toggle('overflow-y-hidden', value))" @keydown.escape.window="if (show) close()">
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
                            New-policy bookings may request rescheduling without a notice deadline until physical check-in; staff confirm availability and the accepted quote. Historical bookings retain their accepted terms.
                        </p>

                        <div class="mt-6" @schedule-changed="checkIn = $event.detail.check_in; checkOut = $event.detail.check_out; earlyCheckInHours = $event.detail.early_check_in_hours; calculatePreview()">
                            <x-reservation-schedule :facility="$reservation->facility" :initial="['check_in' => $reservation->check_in->format('Y-m-d H:i'), 'check_out' => $reservation->check_out->format('Y-m-d H:i'), 'early_check_in_hours' => $reservation->early_check_in_hours, 'late_check_out_hours' => $reservation->late_check_out_hours]" prefix="reschedule" error-expression="previewError" />
                        </div>
                        <input type="hidden" name="check_in" x-model="checkIn">
                        <input type="hidden" name="check_out" x-model="checkOut">
                        <input type="hidden" name="early_check_in_hours" x-model="earlyCheckInHours">
                        <div class="mt-5">
                            <x-input-label for="guestCount" value="New guest count" />
                            <x-text-input type="number" x-model.number="guestCount" @input="previewData = null; previewAttempt++; clearTimeout(previewTimer); previewTimer = setTimeout(() => calculatePreview(), 500)" name="guestCount" id="guestCount" min="1" required class="mt-1 block w-full" />
                        </div>

                        <div class="mt-5">
                            <x-input-label for="reschedule-notes" value="Notes and additional requests" />
                            <textarea id="reschedule-notes" name="special_requests" maxlength="1000" rows="3" class="mt-2 block w-full rounded-lg border-border bg-surface text-text focus:border-primary focus:ring-primary" placeholder="Late check out requests require staff approval and may incur an additional fee.">{{ old('special_requests', $reservation->special_requests) }}</textarea>
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
                                            <dd class="font-bold text-text"><x-amount :value="$reservation->total_price" /></dd>
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
                                        <div x-show="previewData && previewData.hall_extension_charge > 0" class="flex justify-between"><dt>Hall extension</dt><dd x-text="pesosFromCents(previewData?.hall_extension_charge || 0)"></dd></div>
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
                                                    <span x-text="pesosFromCents(previewData.total_amount)"></span>
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
                            {{-- vocabulary-guard: closes the reschedule dialog; it cancels nothing --}}
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
