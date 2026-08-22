<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Manage Payments') }}
            </h2>
            <a href="{{ route('customer.reservations.show', $reservation) }}" class="text-sm text-primary-600 hover:text-primary-800">
                &larr; Back to Reservation
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <x-alert type="success" :message="session('success')" />
            @endif

            @if (session('error'))
                <x-alert type="error" :message="session('error')" />
            @endif

            @if ($errors->any())
                <div class="bg-red-50 p-4 rounded-md">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <!-- Heroicon name: solid/x-circle -->
                            <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">There were errors with your submission</h3>
                            <div class="mt-2 text-sm text-red-700">
                                <ul class="list-disc pl-5 space-y-1">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Reservation Overview -->
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="px-4 py-5 border-b border-gray-200 sm:px-6 flex justify-between items-center">
                    <div>
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            Reservation: {{ $reservation->reservation_code }}
                        </h3>
                        <p class="mt-1 max-w-2xl text-sm text-gray-500">
                            {{ $reservation->facility->name }}
                        </p>
                    </div>
                    <x-badge :status="$reservation->status->label()" />
                </div>
                <div class="px-4 py-5 sm:p-6 bg-gray-50 border-b border-gray-200">
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-3">
                        <div class="sm:col-span-1">
                            <dt class="text-sm font-medium text-gray-500">Total Value</dt>
                            <dd class="mt-1 text-lg font-medium text-gray-900">₱{{ number_format($reservation->total_price, 2) }}</dd>
                        </div>
                        <div class="sm:col-span-1">
                            <dt class="text-sm font-medium text-gray-500">Verified Paid</dt>
                            <dd class="mt-1 text-lg font-medium text-green-600">₱{{ number_format($reservation->getVerifiedTotalPaid(), 2) }}</dd>
                        </div>
                        <div class="sm:col-span-1 border-l pl-4 sm:border-l-0 sm:pl-0 sm:border-t-0 border-gray-200">
                            <dt class="text-sm font-bold text-gray-900">Remaining Balance</dt>
                            @if($reservation->getRemainingBalance() > 0)
                                <dd class="mt-1 text-xl font-bold text-red-600">₱{{ number_format($reservation->getRemainingBalance(), 2) }}</dd>
                            @else
                                <dd class="mt-1 text-xl font-bold text-green-600">Fully Paid</dd>
                            @endif
                        </div>
                    </dl>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Payment History Column -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white shadow sm:rounded-lg">
                        <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                Payment History
                            </h3>
                        </div>
                        
                        @if($payments->isEmpty())
                            <div class="p-6">
                                <x-empty-state icon="cash" title="No payments yet" description="You have not submitted any payments for this reservation." />
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method & Ref</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th scope="col" class="relative px-6 py-3">
                                                <span class="sr-only">Actions</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($payments as $payment)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $payment->created_at->format('M d, Y h:i A') }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    ₱{{ number_format($payment->amount, 2) }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <div class="font-medium text-gray-900">{{ $payment->method->label() }}</div>
                                                    @if($payment->reference_number)
                                                        <div class="text-xs">Ref: {{ $payment->reference_number }}</div>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <x-badge :status="$payment->status->label()" />
                                                    @if($payment->status === \App\Enums\PaymentStatus::Rejected && $payment->rejection_reason)
                                                        <div class="mt-1 text-xs text-red-600 max-w-xs truncate" title="{{ $payment->rejection_reason }}">
                                                            {{ $payment->rejection_reason }}
                                                        </div>
                                                    @endif
                                                    @if($payment->status === \App\Enums\PaymentStatus::Pending)
                                                        <div class="mt-1 text-xs text-gray-500">
                                                            Waiting for verification.
                                                        </div>
                                                    @endif
                                                    @if($payment->status === \App\Enums\PaymentStatus::Verified && $payment->verified_at)
                                                        <div class="mt-1 text-xs text-green-600">
                                                            Verified on {{ $payment->verified_at->format('M d, Y') }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <a href="{{ route('customer.payments.download', $payment) }}" target="_blank" class="text-primary-600 hover:text-primary-900 inline-flex items-center">
                                                        <svg class="mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                        View Proof
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Submit Payment Column -->
                <div class="lg:col-span-1">
                    @if($reservation->getRemainingBalance() > 0)
                        <div class="bg-white shadow sm:rounded-lg">
                            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">
                                    Submit a Payment
                                </h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    Upload your proof of payment for verification.
                                </p>
                            </div>
                            <div class="px-4 py-5 sm:p-6" x-data="{ isSubmitting: false }">
                                <form action="{{ route('customer.payments.store', $reservation) }}" method="POST" enctype="multipart/form-data" @submit="isSubmitting = true">
                                    @csrf
                                    
                                    <div class="space-y-6">
                                        <!-- Amount -->
                                        <div>
                                            <label for="amount" class="block text-sm font-medium text-gray-700">Amount (₱)</label>
                                            <div class="mt-1 relative rounded-md shadow-sm">
                                                <input type="number" step="0.01" min="1" name="amount" id="amount" 
                                                       class="focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md" 
                                                       value="{{ old('amount', $reservation->getRemainingBalance()) }}" required>
                                            </div>
                                        </div>

                                        <!-- Method -->
                                        <div>
                                            <label for="payment_method" class="block text-sm font-medium text-gray-700">Payment Method</label>
                                            <div class="mt-1">
                                                <select id="payment_method" name="payment_method" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md">
                                                    <option value="" disabled selected>Select a method</option>
                                                    @foreach(\App\Enums\PaymentMethod::cases() as $method)
                                                        <option value="{{ $method->value }}" @selected(old('payment_method') === $method->value)>{{ $method->label() }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Reference Number -->
                                        <div>
                                            <label for="reference_number" class="block text-sm font-medium text-gray-700">
                                                Reference Number 
                                                <span class="text-gray-400 font-normal">(Optional, if applicable)</span>
                                            </label>
                                            <div class="mt-1">
                                                <input type="text" name="reference_number" id="reference_number" value="{{ old('reference_number') }}" class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                            </div>
                                            <p class="mt-2 text-xs text-gray-500">For GCash, Maya, or bank transfers.</p>
                                        </div>

                                        <!-- Proof of Payment -->
                                        <div>
                                            <label for="proof" class="block text-sm font-medium text-gray-700">Proof of Payment</label>
                                            <div class="mt-1">
                                                <input type="file" name="proof" id="proof" accept=".jpg,.jpeg,.png,.pdf" required class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md py-2 px-3 bg-white">
                                            </div>
                                            <p class="mt-2 text-xs text-gray-500" id="proof_description">
                                                Allowed formats: JPG, PNG, PDF up to 5MB.
                                            </p>
                                        </div>

                                        <!-- Submit -->
                                        <div class="pt-2">
                                            <button type="submit" 
                                                    :disabled="isSubmitting"
                                                    :class="isSubmitting ? 'opacity-50 cursor-wait' : 'hover:bg-primary-700'"
                                                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors">
                                                <span x-show="!isSubmitting">Submit Payment</span>
                                                <span x-show="isSubmitting" class="flex items-center">
                                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    Submitting...
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @else
                        <div class="bg-white shadow sm:rounded-lg">
                            <div class="px-4 py-5 sm:p-6 text-center">
                                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                                    <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Fully Paid</h3>
                                <p class="mt-2 text-sm text-gray-500">
                                    Thank you! Your reservation is fully paid. No further payments are required.
                                </p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
