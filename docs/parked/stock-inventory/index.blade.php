{{--
    PARKED, NOT LIVE.

    This is the back office stock inventory screen, taken off the admin on
    2026-09-21 while the feature waits. Nothing renders it: the four
    `admin.inventory.*` routes and the AdminNav entry were removed, so the app
    has no path to this file from `resources/views/`. See README.md beside it
    for how to bring it back.

    It was rebuilt from the compiled Blade left in storage/framework/views/,
    because the original had never been committed to git and was therefore not
    recoverable from history. Markup, classes, copy and directives are faithful
    to that compiled output; only the Blade syntax was restored.
--}}
<x-admin-layout>
    <x-slot name="header">Stock inventory</x-slot>

    <x-admin.page-header
        title="Stock inventory"
        description="Track supplies and equipment separately from bookable spaces."
    >
        <x-slot:actions>
            <x-admin.button href="#add-inventory">Add item</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-border bg-surface p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Low stock</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-accent">{{ $lowStockCount }}</p>
            <p class="mt-1 text-xs text-text-muted">Items at or below their minimum.</p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 sm:col-span-2">
            <form method="GET" action="{{ route('admin.inventory.index') }}" role="search" class="flex flex-col gap-2 sm:flex-row sm:items-end">
                <div class="min-w-0 flex-1">
                    <label for="inventory-search" class="admin-label">Find an item</label>
                    <input id="inventory-search" name="search" value="{{ $search }}" type="search" class="admin-input mt-1" placeholder="Name or SKU" autocomplete="off">
                </div>
                <x-admin.button type="submit" variant="secondary" size="sm">Search</x-admin.button>
                @if ($search !== '')
                    <x-admin.button :href="route('admin.inventory.index')" variant="ghost" size="sm">Clear</x-admin.button>
                @endif
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <x-admin.panel title="Items" :badge="$items->total()" :padding="false">
            @if ($items->isEmpty())
                <x-admin.empty
                    icon="inventory"
                    title="No stock items yet"
                    description="Add towels, cleaning supplies, fuel, or equipment that staff count manually."
                />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border text-sm">
                        <thead class="bg-muted/60 text-left text-xs font-semibold uppercase tracking-wide text-text-muted">
                            <tr>
                                <th scope="col" class="px-4 py-3">Item</th>
                                <th scope="col" class="px-4 py-3 text-right">On hand</th>
                                <th scope="col" class="px-4 py-3">Status</th>
                                <th scope="col" class="px-4 py-3 text-right"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach ($items as $item)
                                <tr class="align-top {{ $item->isLowStock() ? 'bg-accent-light/35' : '' }}">
                                    <td class="px-4 py-4">
                                        <form method="POST" action="{{ route('admin.inventory.update', $item) }}" class="space-y-2">
                                            @csrf
                                            @method('PATCH')
                                            <label class="sr-only" for="item-name-{{ $item->id }}">Item name</label>
                                            <input id="item-name-{{ $item->id }}" name="name" value="{{ $item->name }}" class="admin-input max-w-xs font-semibold">
                                            <div class="flex flex-wrap gap-2">
                                                <label class="sr-only" for="item-unit-{{ $item->id }}">Unit</label>
                                                <input id="item-unit-{{ $item->id }}" name="unit" value="{{ $item->unit }}" class="admin-input w-28 text-xs" aria-label="Unit">
                                                <label class="sr-only" for="item-sku-{{ $item->id }}">SKU</label>
                                                <input id="item-sku-{{ $item->id }}" name="sku" value="{{ $item->sku }}" class="admin-input w-32 font-mono text-xs" placeholder="SKU" aria-label="SKU">
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <label for="item-min-{{ $item->id }}" class="text-xs text-text-muted">Minimum</label>
                                                <input id="item-min-{{ $item->id }}" name="minimum_quantity" value="{{ rtrim(rtrim((string) $item->minimum_quantity, '0'), '.') }}" type="number" min="0" step="0.001" class="admin-input w-28 text-xs" aria-label="Minimum quantity">
                                                <label class="inline-flex items-center gap-1 text-xs text-text-muted">
                                                    <input type="checkbox" name="is_active" value="1" @checked($item->is_active) class="rounded border-border text-primary focus:ring-primary">
                                                    Active
                                                </label>
                                            </div>
                                            <button type="submit" class="text-xs font-semibold text-primary hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">Save item details</button>
                                        </form>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-right">
                                        <span class="text-lg font-semibold tabular-nums text-text">{{ rtrim(rtrim((string) $item->quantity_on_hand, '0'), '.') }}</span>
                                        <span class="block text-xs text-text-muted">{{ $item->unit }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4">
                                        @if ($item->isLowStock())
                                            <span class="inline-flex rounded-full bg-accent-light px-2 py-1 text-xs font-semibold text-primary-dark">Low stock</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-secondary-light px-2 py-1 text-xs font-semibold text-primary-dark">Healthy</span>
                                        @endif
                                        <span class="mt-1 block text-xs text-text-muted">Minimum {{ rtrim(rtrim((string) $item->minimum_quantity, '0'), '.') }} {{ $item->unit }}</span>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <details class="relative inline-block text-left">
                                            <summary class="cursor-pointer list-none rounded-lg border border-border bg-surface px-3 py-2 text-xs font-semibold text-text hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">Record movement</summary>
                                            <form method="POST" action="{{ route('admin.inventory.movements.store', $item) }}" class="mt-2 w-64 rounded-xl border border-border bg-surface p-3 text-left shadow-lg">
                                                @csrf
                                                <label for="movement-type-{{ $item->id }}" class="admin-label">Movement</label>
                                                <select id="movement-type-{{ $item->id }}" name="movement_type" class="admin-input mt-1 text-xs">
                                                    <option value="receive">Receive stock</option>
                                                    <option value="use">Use stock</option>
                                                    <option value="adjustment">Adjustment (+ / −)</option>
                                                </select>
                                                <label for="movement-quantity-{{ $item->id }}" class="admin-label mt-3">Quantity</label>
                                                <input id="movement-quantity-{{ $item->id }}" name="quantity" type="number" step="0.001" min="-999999999" required class="admin-input mt-1 text-xs">
                                                <label for="movement-note-{{ $item->id }}" class="admin-label mt-3">Note <span class="font-normal text-text-muted">(optional)</span></label>
                                                <input id="movement-note-{{ $item->id }}" name="note" maxlength="500" class="admin-input mt-1 text-xs" placeholder="Delivery, damaged, count…">
                                                <button type="submit" class="mt-3 inline-flex min-h-[40px] w-full items-center justify-center rounded-lg bg-primary px-3 text-xs font-semibold text-white hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">Save movement</button>
                                            </form>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($items->hasPages())
                    <div class="border-t border-border px-4 py-4">{{ $items->links() }}</div>
                @endif
            @endif
        </x-admin.panel>

        <div class="space-y-6">
            <x-admin.panel id="add-inventory" title="Add item">
                <form method="POST" action="{{ route('admin.inventory.store') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label for="new-name" class="admin-label">Name</label>
                        <input id="new-name" name="name" required class="admin-input mt-1" placeholder="Pool towels">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="new-unit" class="admin-label">Unit</label>
                            <input id="new-unit" name="unit" required class="admin-input mt-1" placeholder="pieces">
                        </div>
                        <div>
                            <label for="new-sku" class="admin-label">SKU</label>
                            <input id="new-sku" name="sku" class="admin-input mt-1" placeholder="Optional">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="new-quantity" class="admin-label">Opening quantity</label>
                            <input id="new-quantity" name="initial_quantity" type="number" min="0" step="0.001" value="0" class="admin-input mt-1">
                        </div>
                        <div>
                            <label for="new-minimum" class="admin-label">Minimum</label>
                            <input id="new-minimum" name="minimum_quantity" type="number" min="0" step="0.001" value="0" required class="admin-input mt-1">
                        </div>
                    </div>
                    <x-admin.button type="submit" class="w-full">Add item</x-admin.button>
                </form>
            </x-admin.panel>

            <x-admin.panel title="Recent movements" :padding="false">
                @forelse ($recentMovements as $movement)
                    <div class="border-b border-border px-4 py-3 last:border-b-0">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="truncate text-sm font-semibold text-text">{{ $movement->item->name }}</p>
                            <span class="text-sm font-semibold tabular-nums {{ (float) $movement->quantity_change < 0 ? 'text-accent' : 'text-secondary' }}">
                                {{ (float) $movement->quantity_change > 0 ? '+' : '' }}{{ rtrim(rtrim((string) $movement->quantity_change, '0'), '.') }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-text-muted">
                            {{ ucfirst($movement->movement_type) }} · {{ $movement->created_at->format('M j, Y g:i A') }}
                            @if ($movement->creator) · {{ $movement->creator->name }} @endif
                        </p>
                        @if ($movement->note)
                            <p class="mt-1 text-xs text-text-muted">{{ $movement->note }}</p>
                        @endif
                    </div>
                @empty
                    <x-admin.empty
                        icon="inventory"
                        title="No movements yet"
                        description="Receiving, using, or adjusting an item will appear here."
                    />
                @endforelse
            </x-admin.panel>
        </div>
    </div>
</x-admin-layout>
