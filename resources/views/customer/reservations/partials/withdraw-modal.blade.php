@php
    // A withdrawal asks staff to review a request; it never releases the
    // booking. The status stays Pending until they confirm, so this modal is
    // only ever included for a reservation that is still waiting for approval.
    $hasWithdrawalRequest = $reservation->cancellation_requested_at !== null;
    $cutoffHours = (int) config('resort.policy.withdrawal_cutoff_hours', 48);
@endphp

<div x-data="{ show: false }">
    @if($hasWithdrawalRequest)
        <p class="text-sm text-text-muted">
            Withdrawal requested {{ $reservation->cancellation_requested_at->format('M d, Y g:i A') }}.
            The resort will confirm it. If you changed your mind, call
            <a href="{{ config('resort.phone_href') }}" class="font-medium text-primary hover:underline">{{ config('resort.phone') }}</a>.
        </p>
    @else
        <x-danger-button @click="show = true" type="button" class="w-full !bg-danger-light !text-danger hover:!opacity-90">
            Request withdrawal
        </x-danger-button>
    @endif

    @if(! $hasWithdrawalRequest)
    <div x-show="show" style="display: none;" class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="withdraw-modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/50 transition-opacity" @click="show = false" aria-hidden="true"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="relative inline-block align-bottom bg-surface border border-border rounded-2xl px-5 pt-5 pb-5 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-7">
                <form action="{{ route('customer.reservations.withdraw', $reservation) }}" method="POST">
                    @csrf
                    @method('PATCH')

                    <div>
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-danger-light">
                            <svg class="h-6 w-6 text-danger" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center">
                            <h3 class="font-heading text-2xl font-semibold text-text" id="withdraw-modal-title">
                                Request withdrawal
                            </h3>
                            <p class="mt-2 text-sm text-text-muted">
                                Ask the resort to withdraw your pending reservation for {{ $reservation->facility->name }}. Staff will confirm it — the booking stays as it is until they do.
                            </p>
                            <p class="mt-2 text-sm text-text-muted">
                                For new-policy bookings, cancellation and full-refund requests stay open until physical check-in, including no-show. The owner approves refunds; administrators record that approval and the funds returned. Historical bookings retain their accepted terms.
                            </p>
                        </div>

                        <div class="mt-4">
                            <x-input-label for="reason" value="Reason (optional)" />
                            <textarea id="reason" name="reason" rows="3" class="mt-1 shadow-sm focus:ring-danger focus:border-danger block w-full sm:text-sm border-border rounded-lg"></textarea>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col-reverse sm:flex-row gap-3">
                        <x-secondary-button @click="show = false" type="button" class="w-full sm:w-auto">
                            Go back
                        </x-secondary-button>
                        <x-danger-button type="submit" class="w-full sm:flex-1">
                            Submit request
                        </x-danger-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
