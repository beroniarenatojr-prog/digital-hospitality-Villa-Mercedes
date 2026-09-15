<x-customer-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-text mb-2">Notifications</h1>
                <p class="text-text-muted">Stay updated on your reservations and account activity.</p>
            </div>
            
            @if($hasUnreadNotifications)
                <form action="{{ route('customer.notifications.mark-all-as-read') }}" method="POST">
                    @csrf
                    <x-primary-button type="submit" class="whitespace-nowrap">
                        Mark all as read
                    </x-primary-button>
                </form>
            @endif
        </div>

        @if($notifications->isEmpty())
            <x-empty-state 
                title="You're all caught up." 
                description="You don't have any notifications at the moment." 
            />
        @else
            <x-card class="divide-y divide-border overflow-hidden">
                @foreach($notifications as $notification)
                    <div class="p-6 transition-colors {{ $notification->read_at ? 'bg-surface' : 'bg-primary/5' }}">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-4">
                                <div class="mt-1 flex-shrink-0">
                                    <div class="relative w-10 h-10 rounded-full flex items-center justify-center {{ $notification->read_at ? 'bg-muted text-text-muted' : 'bg-primary/20 text-primary' }}">
                                        @if($notification->read_at)
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                            </svg>
                                        @else
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                            </svg>
                                            <span class="absolute top-0 right-0 block h-2.5 w-2.5 rounded-full bg-danger ring-2 ring-surface"></span>
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <a href="{{ route('customer.notifications.open', $notification->id) }}" class="block">
                                        <p class="text-sm font-medium text-text {{ $notification->read_at ? '' : 'font-semibold' }}">
                                            {{ $notification->data['message'] ?? 'You have a new notification.' }}
                                        </p>
                                        <p class="text-sm text-text-muted mt-1">
                                            {{ $notification->created_at->diffForHumans() }}
                                        </p>
                                    </a>
                                    @if(isset($notification->data['reservation_id']))
                                        <div class="mt-2">
                                            <a href="{{ route('customer.notifications.open', $notification->id) }}" class="text-xs font-medium text-primary hover:text-primary-dark">
                                                View Reservation &rarr;
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            
                            @if(is_null($notification->read_at))
                                <form action="{{ route('customer.notifications.mark-as-read', $notification->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="text-xs font-medium text-primary hover:text-primary-dark bg-transparent border-0 p-0" title="Mark as read">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </x-card>

            <div class="mt-6">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</x-customer-layout>
