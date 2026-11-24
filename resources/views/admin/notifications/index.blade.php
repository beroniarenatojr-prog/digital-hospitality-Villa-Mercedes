<x-admin-layout>
    <x-slot name="header">Notifications</x-slot>

    <x-admin.page-header
        title="Notifications"
    >
        @if ($hasUnreadNotifications)
            <x-slot:actions>
                <form action="{{ route('admin.notifications.mark-all-as-read') }}" method="POST">
                    @csrf
                    <x-admin.button type="submit" variant="secondary">Mark all as read</x-admin.button>
                </form>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    @if ($notifications->isEmpty())
        <div class="rounded-xl border border-border bg-surface">
            <x-admin.empty
                icon="notifications"
                title="Nothing new"
                description="New reservations, payment proofs, and withdrawal requests appear here as they arrive, so an empty list means the desk is caught up."
            />
        </div>
    @else
        <x-admin.panel :padding="false">
            <ul class="divide-y divide-border">
                @foreach ($notifications as $notification)
                    <li>
                        <a
                            href="{{ route('admin.notifications.open', $notification->id) }}"
                            class="flex items-start gap-3 px-5 py-4 transition-colors duration-150 hover:bg-primary-light/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary {{ $notification->read_at ? '' : 'bg-primary-light/30' }}"
                        >
                            <span class="mt-1.5 shrink-0" aria-hidden="true">
                                @if (is_null($notification->read_at))
                                    <span class="block h-2.5 w-2.5 rounded-full bg-danger"></span>
                                @else
                                    <span class="block h-2.5 w-2.5 rounded-full border border-border"></span>
                                @endif
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="block text-sm {{ $notification->read_at ? 'font-medium text-text' : 'font-semibold text-text' }}">
                                    {{ $notification->data['message'] ?? 'You have a new notification.' }}
                                </span>
                                <span class="mt-1 block text-xs text-text-muted">
                                    {{ $notification->created_at->diffForHumans() }}
                                    @unless ($notification->read_at)
                                        · <span class="font-semibold text-danger">Unread</span>
                                    @endunless
                                </span>
                            </span>

                            <span class="mt-0.5 shrink-0 text-xs font-semibold text-primary">Open</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-admin.panel>

        @if ($notifications->hasPages())
            <div class="mt-6">
                {{ $notifications->withQueryString()->links() }}
            </div>
        @endif
    @endif
</x-admin-layout>
