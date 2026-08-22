<x-admin-layout>
    <x-slot name="header">
        Inquiry Management
    </x-slot>

    <!-- Filters -->
    <div class="mb-6 bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <form method="GET" action="{{ route('admin.inquiries.index') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <!-- Search -->
            <div>
                <label for="search" class="block text-xs font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Subject, Message, Name"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
            </div>

            <!-- Status -->
            <div>
                <label for="status" class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                <select name="status" id="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
                    <option value="">All Statuses</option>
                    @foreach(\App\Enums\InquiryStatus::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Date From -->
            <div>
                <label for="date_from" class="block text-xs font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
            </div>

            <!-- Date Until -->
            <div>
                <label for="date_until" class="block text-xs font-medium text-gray-700 mb-1">Date Until</label>
                <input type="date" name="date_until" id="date_until" value="{{ request('date_until') }}"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm">
            </div>

            <!-- Actions -->
            <div class="flex items-end gap-2 lg:col-span-4">
                <button type="submit" class="inline-flex justify-center items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-dark transition">
                    Filter
                </button>
                @if(request()->anyFilled(['search', 'status', 'date_from', 'date_until']))
                    <a href="{{ route('admin.inquiries.index') }}" class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                        Clear
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Desktop Table -->
    <div class="hidden md:block">
        <x-admin.table>
            <x-admin.table-head>
                <x-admin.table-cell :isHeader="true">ID</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Customer</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Subject</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Submitted</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Status</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Actions</x-admin.table-cell>
            </x-admin.table-head>
            <x-admin.table-body>
                @forelse($inquiries as $inquiry)
                    <x-admin.table-row class="{{ $inquiry->status === \App\Enums\InquiryStatus::Pending ? 'bg-amber-50/50' : ($inquiry->status === \App\Enums\InquiryStatus::Open ? 'bg-blue-50/50' : '') }}">
                        <x-admin.table-cell>
                            <span class="font-mono text-sm text-gray-500">#{{ $inquiry->id }}</span>
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            <span class="font-medium text-gray-900">{{ $inquiry->user->name }}</span>
                            <span class="block text-xs text-gray-500">{{ $inquiry->user->email }}</span>
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            <span class="font-medium text-gray-900 line-clamp-1">{{ $inquiry->subject }}</span>
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            <span class="text-sm text-gray-700">{{ $inquiry->created_at->format('M d, Y') }}</span>
                            @if($inquiry->responded_at)
                                <span class="block text-xs text-gray-400">Responded: {{ \Carbon\Carbon::parse($inquiry->responded_at)->format('M d') }}</span>
                            @endif
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            <x-badge :status="$inquiry->status->value" />
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            <a href="{{ route('admin.inquiries.show', $inquiry) }}" class="text-primary hover:text-primary-dark text-sm font-medium">
                                {{ in_array($inquiry->status, [\App\Enums\InquiryStatus::Open, \App\Enums\InquiryStatus::Pending]) ? 'Respond' : 'View' }}
                            </a>
                        </x-admin.table-cell>
                    </x-admin.table-row>
                @empty
                    <x-admin.table-row>
                        <x-admin.table-cell colspan="6" class="text-center py-8 text-gray-500">
                            No inquiries found matching your criteria.
                        </x-admin.table-cell>
                    </x-admin.table-row>
                @endforelse
            </x-admin.table-body>
        </x-admin.table>
    </div>

    <!-- Mobile Cards -->
    <div class="md:hidden space-y-4">
        @forelse($inquiries as $inquiry)
            <div class="bg-white rounded-lg shadow-sm border {{ $inquiry->status === \App\Enums\InquiryStatus::Pending ? 'border-amber-300' : ($inquiry->status === \App\Enums\InquiryStatus::Open ? 'border-blue-300' : 'border-gray-200') }} p-4">
                <div class="flex items-start justify-between mb-2">
                    <div>
                        <span class="font-mono text-xs text-gray-500">#{{ $inquiry->id }}</span>
                        <h4 class="font-semibold text-gray-900">{{ $inquiry->subject }}</h4>
                    </div>
                    <x-badge :status="$inquiry->status->value" />
                </div>
                <div class="text-sm text-gray-600 space-y-1 mt-3">
                    <p><span class="font-medium text-gray-900">{{ $inquiry->user->name }}</span></p>
                    <p class="text-xs">{{ $inquiry->created_at->format('M d, Y h:i A') }}</p>
                </div>
                <div class="mt-4 pt-3 border-t border-gray-100">
                    <a href="{{ route('admin.inquiries.show', $inquiry) }}" class="w-full text-center block px-4 py-2 border border-primary rounded-md text-sm font-medium text-primary hover:bg-primary hover:text-white transition">
                        {{ in_array($inquiry->status, [\App\Enums\InquiryStatus::Open, \App\Enums\InquiryStatus::Pending]) ? 'Respond to Inquiry' : 'View Details' }}
                    </a>
                </div>
            </div>
        @empty
            <p class="text-center text-gray-500 py-8">No inquiries found.</p>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $inquiries->withQueryString()->links() }}
    </div>
</x-admin-layout>
