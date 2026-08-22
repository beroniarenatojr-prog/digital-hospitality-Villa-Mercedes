<x-customer-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-text mb-2">My Inquiries</h1>
                <p class="text-text-muted">View your previous inquiries or submit a new one.</p>
            </div>
            
            <div x-data="{ showModal: false }">
                <x-primary-button @click="showModal = true" class="flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    New Inquiry
                </x-primary-button>
                
                <!-- New Inquiry Modal -->
                <div x-show="showModal" 
                     class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto overflow-x-hidden bg-black bg-opacity-50"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     style="display: none;"
                     @keydown.escape.window="showModal = false"
                >
                    <div class="relative w-full max-w-lg p-4 mx-auto" @click.away="showModal = false">
                        <div class="bg-surface rounded-xl shadow-lg border border-border">
                            <div class="flex items-center justify-between p-4 md:p-5 border-b border-border rounded-t">
                                <h3 class="text-xl font-semibold text-text">
                                    Submit an Inquiry
                                </h3>
                                <button type="button" @click="showModal = false" class="text-text-muted bg-transparent hover:bg-gray-100 hover:text-text rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center">
                                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                                    </svg>
                                    <span class="sr-only">Close modal</span>
                                </button>
                            </div>
                            <form action="{{ route('customer.inquiries.store') }}" method="POST" class="p-4 md:p-5" x-data="{ isSubmitting: false }" @submit="isSubmitting = true">
                                @csrf
                                <div class="grid gap-4 mb-4 grid-cols-1">
                                    <div>
                                        <label for="subject" class="block mb-2 text-sm font-medium text-text">Subject <span class="text-error">*</span></label>
                                        <input type="text" name="subject" id="subject" class="bg-surface border border-border text-text text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5" placeholder="What is your inquiry about?" required>
                                        @error('subject')
                                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="message" class="block mb-2 text-sm font-medium text-text">Message <span class="text-error">*</span></label>
                                        <textarea id="message" name="message" rows="4" class="block p-2.5 w-full text-sm text-text bg-surface rounded-lg border border-border focus:ring-primary focus:border-primary" placeholder="Write your message here..." required></textarea>
                                        @error('message')
                                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                                <div class="flex justify-end gap-3 mt-6">
                                    <button type="button" @click="showModal = false" class="py-2.5 px-5 text-sm font-medium text-text bg-surface border border-border rounded-lg hover:bg-gray-50 focus:ring-4 focus:outline-none focus:ring-gray-200">
                                        Cancel
                                    </button>
                                    <x-primary-button type="submit" x-bind:disabled="isSubmitting" class="flex items-center gap-2">
                                        <span x-show="!isSubmitting">Submit Inquiry</span>
                                        <span x-show="isSubmitting">Submitting...</span>
                                    </x-primary-button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(session('success'))
            <x-alert type="success" class="mb-6">
                {{ session('success') }}
            </x-alert>
        @endif
        
        @if($errors->any())
            <x-alert type="error" class="mb-6">
                Please fix the errors in your submission.
            </x-alert>
        @endif

        @if($inquiries->isEmpty())
            <x-empty-state 
                title="You haven't submitted any inquiries yet." 
                description="If you have any questions or concerns, click the 'New Inquiry' button above." 
            />
        @else
            <x-card class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-surface-alt border-b border-border">
                                <th class="p-4 font-semibold text-sm text-text-muted">Subject</th>
                                <th class="p-4 font-semibold text-sm text-text-muted">Status</th>
                                <th class="p-4 font-semibold text-sm text-text-muted">Date Submitted</th>
                                <th class="p-4 font-semibold text-sm text-text-muted">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach($inquiries as $inquiry)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="p-4">
                                        <div class="font-medium text-text">{{ $inquiry->subject }}</div>
                                    </td>
                                    <td class="p-4">
                                        <x-badge status="{{ $inquiry->status->label() }}" />
                                    </td>
                                    <td class="p-4 text-text-muted text-sm">
                                        {{ $inquiry->created_at->format('M d, Y h:i A') }}
                                    </td>
                                    <td class="p-4">
                                        <a href="{{ route('customer.inquiries.show', $inquiry) }}" class="text-primary hover:text-primary-dark font-medium text-sm flex items-center gap-1">
                                            View Details
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endif
    </div>
</x-customer-layout>
