@php
    $hasFilters = request()->anyFilled(['search', 'has_reservations', 'registered_from', 'registered_until']);
@endphp

@if ($customers->isEmpty())
    <div class="rounded-xl border border-border bg-surface">
        <x-admin.empty
            icon="customers"
            title="{{ $hasFilters ? 'No customers match these filters' : 'No guest accounts yet' }}"
            description="{{ $hasFilters ? 'Clear a filter or search by a different name, email, or phone number.' : 'Guests appear here when they create an account through the public booking flow.' }}"
        />
    </div>
@else
    <div class="hidden lg:block">
        <x-admin.table>
            <x-admin.table-head>
                <x-admin.table-cell :isHeader="true">Guest</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Email</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Phone</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true">Registered</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true" numeric>Bookings</x-admin.table-cell>
                <x-admin.table-cell :isHeader="true"><span class="sr-only">Actions</span></x-admin.table-cell>
            </x-admin.table-head>
            <x-admin.table-body>
                @foreach ($customers as $customer)
                    <x-admin.table-row>
                        <x-admin.table-cell>
                            <a href="{{ route('admin.customers.show', $customer->id) }}" class="rounded font-semibold text-text hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                {{ $customer->name }}
                            </a>
                        </x-admin.table-cell>

                        <x-admin.table-cell nowrap>
                            {{ $customer->email }}
                        </x-admin.table-cell>

                        <x-admin.table-cell nowrap>
                            @if ($customer->phone)
                                {{ $customer->phone }}
                            @else
                                <span class="text-text-muted">No number on file</span>
                            @endif
                        </x-admin.table-cell>

                        <x-admin.table-cell nowrap>
                            <x-admin.date-time :value="$customer->created_at" format="date" />
                        </x-admin.table-cell>

                        <x-admin.table-cell numeric>
                            <span class="font-medium">{{ $customer->reservations_count }}</span>
                            @if ($customer->reservations_max_check_in)
                                <span class="mt-0.5 block text-xs text-text-muted">
                                    Latest <x-admin.date-time :value="\Carbon\Carbon::parse($customer->reservations_max_check_in)" format="date" />
                                </span>
                            @endif
                        </x-admin.table-cell>

                        <x-admin.table-cell nowrap>
                            <a href="{{ route('admin.customers.show', $customer->id) }}" class="rounded font-semibold text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                Open guest
                            </a>
                        </x-admin.table-cell>
                    </x-admin.table-row>
                @endforeach
            </x-admin.table-body>
        </x-admin.table>
    </div>

    <ul class="space-y-3 lg:hidden">
        @foreach ($customers as $customer)
            <li class="rounded-xl border border-border bg-surface p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <a href="{{ route('admin.customers.show', $customer->id) }}" class="block truncate font-semibold text-text hover:text-primary">
                            {{ $customer->name }}
                        </a>
                        <p class="truncate text-sm text-text-muted">{{ $customer->email }}</p>
                        @if ($customer->phone)
                            <p class="truncate text-sm text-text-muted tabular-nums">{{ $customer->phone }}</p>
                        @endif
                    </div>
                    <span class="shrink-0 text-xs text-text-muted tabular-nums">
                        {{ $customer->reservations_count }} {{ \Illuminate\Support\Str::plural('booking', $customer->reservations_count) }}
                    </span>
                </div>

                <div class="mt-4 flex items-center justify-between gap-3 border-t border-border pt-3">
                    <span class="text-xs text-text-muted">
                        Registered <x-admin.date-time :value="$customer->created_at" format="date" />
                    </span>
                    <x-admin.button variant="secondary" size="sm" :href="route('admin.customers.show', $customer->id)">Open guest</x-admin.button>
                </div>
            </li>
        @endforeach
    </ul>

    @if ($customers->hasPages())
        <div class="mt-6">
            {{ $customers->withQueryString()->links() }}
        </div>
    @endif
@endif
