<x-admin-layout>
    <x-slot name="header">
        Notifications
    </x-slot>

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-gray-600">Reservation and payment alerts for the front desk.</p>
        @if($hasUnreadNotifications)
            <form action="{{ route('admin.notifications.mark-all-as-read') }}" method="POST">
                @csrf
                <button type="submit" class="inline-flex min-h-[40px] items-center rounded-lg bg-primary px-4 text-sm font-semibold text-white hover:bg-primary-dark">
                    Mark all as read
                </button>
            </form>
        @endif
    </div>

    @if($notifications->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
            <h2 class="text-base font-semibold text-gray-900">You're all caught up.</h2>
            <p class="mt-1 text-sm text-gray-500">New reservations, payments, and withdrawal requests will appear here.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
            @foreach($notifications as $notification)
                <a href="{{ route('admin.notifications.open', $notification->id) }}" class="flex items-start justify-between gap-4 p-5 transition hover:bg-gray-50 {{ $notification->read_at ? '' : 'bg-primary/5' }}">
                    <div class="min-w-0">
                        <p class="text-sm {{ $notification->read_at ? 'font-medium text-gray-800' : 'font-semibold text-gray-900' }}">
                            {{ $notification->data['message'] ?? 'You have a new notification.' }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>
                    @if(is_null($notification->read_at))
                        <span class="mt-1 inline-flex h-2.5 w-2.5 shrink-0 rounded-full bg-danger" aria-hidden="true"></span>
                        <span class="sr-only">Unread</span>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $notifications->links() }}
        </div>
    @endif
</x-admin-layout>
