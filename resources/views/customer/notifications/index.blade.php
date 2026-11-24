<x-customer-layout>
    <div class="py-10">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-6">

            <x-customer.page-header
                eyebrow="Your account"
                title="Notifications"
                description="Updates about your reservations, deposits and account. Opening one marks it as read."
            >
                @if($hasUnreadNotifications)
                    <x-slot name="actions">
                        <form action="{{ route('customer.notifications.mark-all-as-read') }}" method="POST">
                            @csrf
                            <x-secondary-button type="submit" class="whitespace-nowrap">
                                Mark all as read
                            </x-secondary-button>
                        </form>
                    </x-slot>
                @endif
            </x-customer.page-header>

            @if($notifications->isEmpty())
                <x-empty-state
                    icon="sparkles"
                    title="You're all caught up."
                    description="You don't have any notifications at the moment. Reservation and payment updates appear here as they happen."
                />
            @else
                <ul class="bg-surface border border-border rounded-2xl shadow-sm divide-y divide-border overflow-hidden">
                    @foreach($notifications as $notification)
                        @php $isUnread = is_null($notification->read_at); @endphp
                        <li @class(['px-5 py-5 sm:px-6 transition-colors', 'bg-primary-light/40' => $isUnread])>
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex min-w-0 items-start gap-4">
                                    <span class="mt-0.5 grid h-10 w-10 shrink-0 place-items-center rounded-full {{ $isUnread ? 'bg-primary/15 text-primary' : 'bg-muted text-text-muted' }}">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                        </svg>
                                    </span>
                                    <div class="min-w-0">
                                        <a href="{{ route('customer.notifications.open', $notification->id) }}" class="block rounded focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                                            <p class="text-sm {{ $isUnread ? 'font-semibold text-text' : 'text-text' }}">
                                                {{ $notification->data['message'] ?? 'You have a new notification.' }}
                                            </p>
                                            <p class="mt-1 text-sm text-text-muted">
                                                {{ $notification->created_at->diffForHumans() }}
                                            </p>
                                        </a>
                                        @if(isset($notification->data['reservation_id']))
                                            <p class="mt-2">
                                                <a href="{{ route('customer.notifications.open', $notification->id) }}" class="text-xs font-semibold text-primary hover:underline">
                                                    Open reservation &rarr;
                                                </a>
                                            </p>
                                        @endif
                                    </div>
                                </div>

                                @if($isUnread)
                                    <form action="{{ route('customer.notifications.mark-as-read', $notification->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-primary hover:bg-background focus:outline-none focus:ring-2 focus:ring-primary" title="Mark as read">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span class="sr-only">Mark as read</span>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>

                @if($notifications->hasPages())
                    <div class="pt-2">
                        {{ $notifications->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-customer-layout>
