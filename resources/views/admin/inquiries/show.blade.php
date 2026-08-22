<x-admin-layout>
    <x-slot name="header">
        Inquiry #{{ $inquiry->id }}
    </x-slot>

    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm text-gray-500">
        <a href="{{ route('admin.inquiries.index') }}" class="hover:text-primary">Inquiries</a>
        <span class="mx-2">/</span>
        <span class="text-gray-900">Inquiry #{{ $inquiry->id }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Inquiry Details -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $inquiry->subject }}</h3>
                    <x-badge :status="$inquiry->status->value" />
                </div>
                <div class="p-6">
                    <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
                        <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 font-bold">
                            {{ substr($inquiry->user->name, 0, 1) }}
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ $inquiry->user->name }}</p>
                            <p class="text-xs text-gray-500">Submitted {{ $inquiry->created_at->format('M d, Y h:i A') }}</p>
                        </div>
                    </div>
                    
                    <div class="prose prose-sm max-w-none text-gray-700 whitespace-pre-wrap">
                        {{ $inquiry->message }}
                    </div>
                </div>
            </div>

            <!-- Response Section -->
            @if($inquiry->admin_response)
                <div class="bg-blue-50 shadow-sm rounded-lg border border-blue-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-blue-100 flex justify-between items-center bg-blue-100/50">
                        <h3 class="text-lg font-semibold text-blue-900">Administrator Response</h3>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center gap-4 mb-6 pb-4 border-b border-blue-200">
                            <div class="h-10 w-10 rounded-full bg-blue-200 flex items-center justify-center text-blue-700 font-bold">
                                {{ substr($inquiry->responder->name ?? 'A', 0, 1) }}
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-blue-900">{{ $inquiry->responder->name ?? 'Administrator' }}</p>
                                <p class="text-xs text-blue-600">Responded {{ \Carbon\Carbon::parse($inquiry->responded_at)->format('M d, Y h:i A') }}</p>
                            </div>
                        </div>
                        
                        <div class="prose prose-sm max-w-none text-blue-900 whitespace-pre-wrap">
                            {{ $inquiry->admin_response }}
                        </div>
                    </div>
                </div>
            @endif

            <!-- Reply Form (if open or pending) -->
            @if(in_array($inquiry->status, [\App\Enums\InquiryStatus::Open, \App\Enums\InquiryStatus::Pending]))
                <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">
                        {{ $inquiry->admin_response ? 'Update Response' : 'Write Response' }}
                    </h3>
                    <form method="POST" action="{{ route('admin.inquiries.respond', $inquiry) }}">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <label for="response" class="block text-sm font-medium text-gray-700">Message <span class="text-danger">*</span></label>
                                <textarea name="response" id="response" rows="6" required
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                                          placeholder="Type your response to the customer...">{{ old('response', $inquiry->admin_response) }}</textarea>
                                <p class="mt-1 text-xs text-gray-500">This response will be visible to the customer.</p>
                            </div>
                            
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">Set Status To</label>
                                <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                                    <option value="{{ \App\Enums\InquiryStatus::Pending->value }}" {{ $inquiry->status === \App\Enums\InquiryStatus::Pending ? 'selected' : '' }}>Pending (Needs customer reply)</option>
                                    <option value="{{ \App\Enums\InquiryStatus::Closed->value }}" {{ $inquiry->status === \App\Enums\InquiryStatus::Closed ? 'selected' : '' }}>Closed (Resolved)</option>
                                    <option value="{{ \App\Enums\InquiryStatus::Open->value }}" {{ $inquiry->status === \App\Enums\InquiryStatus::Open ? 'selected' : '' }}>Open</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-md text-sm font-semibold hover:bg-primary-dark">
                                {{ $inquiry->admin_response ? 'Update Response' : 'Send Response' }}
                            </button>
                        </div>
                    </form>
                </div>
            @endif

        </div>

        <!-- Sidebar -->
        <div class="space-y-6">

            <!-- Customer Card -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Customer Details</h3>
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Name</dt>
                        <dd class="mt-1 text-sm font-semibold text-gray-900">
                            <a href="{{ route('admin.customers.show', $inquiry->user) }}" class="text-primary hover:underline">{{ $inquiry->user->name }}</a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Email Address</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <a href="mailto:{{ $inquiry->user->email }}" class="text-primary hover:underline">{{ $inquiry->user->email }}</a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Phone</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $inquiry->user->phone ?: 'Not provided' }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Status Actions -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Quick Actions</h3>
                <form method="POST" action="{{ route('admin.inquiries.status', $inquiry) }}" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    
                    <div>
                        <label for="quick_status" class="block text-sm font-medium text-gray-700">Update Status</label>
                        <select name="status" id="quick_status" onchange="this.form.submit()" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                            @foreach(\App\Enums\InquiryStatus::cases() as $status)
                                <option value="{{ $status->value }}" {{ $inquiry->status === $status ? 'selected' : '' }}>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <noscript>
                        <button type="submit" class="w-full px-4 py-2 bg-gray-100 border border-gray-300 text-gray-700 rounded-md text-sm font-semibold hover:bg-gray-200">Update</button>
                    </noscript>
                </form>
            </div>

        </div>
    </div>
</x-admin-layout>
