<x-customer-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="mb-8">
            <a href="{{ route('customer.inquiries.index') }}" class="text-primary hover:text-primary-dark font-medium text-sm flex items-center gap-1 mb-4">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Inquiries
            </a>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <h1 class="text-3xl font-bold text-text">{{ $inquiry->subject }}</h1>
                <x-badge status="{{ $inquiry->status->label() }}" class="self-start sm:self-auto" />
            </div>
            <p class="text-text-muted mt-2">Submitted on {{ $inquiry->created_at->format('F d, Y \a\t h:i A') }}</p>
        </div>

        @if(session('success'))
            <x-alert type="success" class="mb-6">
                {{ session('success') }}
            </x-alert>
        @endif

        <div class="space-y-6">
            <!-- Customer Message -->
            <x-card class="p-6">
                <div class="flex items-center gap-3 mb-4 border-b border-border pb-4">
                    <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold">
                        {{ substr(auth()->user()->name, 0, 1) }}
                    </div>
                    <div>
                        <div class="font-medium text-text">You</div>
                        <div class="text-xs text-text-muted">{{ $inquiry->created_at->diffForHumans() }}</div>
                    </div>
                </div>
                <div class="text-text whitespace-pre-wrap">{{ $inquiry->message }}</div>
            </x-card>

            <!-- Admin Response -->
            @if($inquiry->admin_response)
                <x-card class="p-6 border-l-4 border-l-primary bg-primary/5">
                    <div class="flex items-center gap-3 mb-4 border-b border-border pb-4">
                        <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-bold">
                            {{ substr($inquiry->responder?->name ?? 'Admin', 0, 1) }}
                        </div>
                        <div>
                            <div class="font-medium text-text">{{ $inquiry->responder?->name ?? 'Administrator' }}</div>
                            <div class="text-xs text-text-muted">
                                {{ $inquiry->responded_at ? $inquiry->responded_at->format('M d, Y h:i A') : '' }}
                                @if($inquiry->responded_at)
                                    ({{ $inquiry->responded_at->diffForHumans() }})
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="text-text whitespace-pre-wrap">{{ $inquiry->admin_response }}</div>
                </x-card>
            @elseif($inquiry->status !== \App\Enums\InquiryStatus::Closed)
                <div class="text-center py-8">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 text-gray-500 mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <p class="text-text-muted">We have received your inquiry and our team will respond shortly.</p>
                </div>
            @endif
        </div>
    </div>
</x-customer-layout>
