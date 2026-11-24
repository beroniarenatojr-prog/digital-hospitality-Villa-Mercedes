<?php

/*
 * PARKED, NOT LIVE.
 *
 * The stock inventory back office screen was taken off the admin on 2026-09-21
 * while the feature waits: its four `admin.inventory.*` routes and its AdminNav
 * entry were removed, so nothing resolves to this controller any more.
 *
 * It sits here rather than beside the other admin controllers so the app has no
 * autoloadable, unroutable controller in it. See README.md in this folder for
 * how to put it back. The content below is exactly what the working tree held
 * (the file had never been committed, so git could not keep it).
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminInventoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));

        $items = InventoryItem::query()
            ->withCount('movements')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            }))
            ->orderByRaw('CASE WHEN quantity_on_hand <= minimum_quantity THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $lowStockCount = InventoryItem::query()
            ->where('is_active', true)
            ->whereColumn('quantity_on_hand', '<=', 'minimum_quantity')
            ->count();

        $recentMovements = \App\Models\InventoryMovement::query()
            ->with(['item', 'creator'])
            ->latest()
            ->limit(12)
            ->get();

        return view('admin.inventory.index', compact('items', 'lowStockCount', 'recentMovements', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:80', 'unique:inventory_items,sku'],
            'unit' => ['required', 'string', 'max:40'],
            'minimum_quantity' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'initial_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ]);

        DB::transaction(function () use ($data, $request): void {
            $initial = (float) ($data['initial_quantity'] ?? 0);
            $item = InventoryItem::query()->create([
                'name' => $data['name'],
                'sku' => filled($data['sku'] ?? null) ? $data['sku'] : null,
                'unit' => $data['unit'],
                'quantity_on_hand' => $initial,
                'minimum_quantity' => $data['minimum_quantity'],
                'is_active' => true,
            ]);

            if ($initial > 0) {
                $item->movements()->create([
                    'created_by' => $request->user()->id,
                    'movement_type' => 'adjustment',
                    'quantity_change' => $initial,
                    'quantity_after' => $initial,
                    'note' => 'Opening balance',
                ]);
            }
        });

        return to_route('admin.inventory.index')->with('success', 'Inventory item added.');
    }

    public function movement(Request $request, InventoryItem $inventory): RedirectResponse
    {
        $data = $request->validate([
            'movement_type' => ['required', 'in:receive,use,adjustment'],
            'quantity' => ['required', 'numeric', 'not_in:0', 'between:-999999999,999999999'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($data, $request, $inventory): void {
            $item = InventoryItem::query()->lockForUpdate()->findOrFail($inventory->id);
            $quantity = (float) $data['quantity'];
            $change = match ($data['movement_type']) {
                'receive' => abs($quantity),
                'use' => -abs($quantity),
                default => $quantity,
            };
            $after = (float) $item->quantity_on_hand + $change;

            if ($after < 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'quantity' => 'This movement would take stock below zero.',
                ]);
            }

            $item->update(['quantity_on_hand' => $after]);
            $item->movements()->create([
                'created_by' => $request->user()->id,
                'movement_type' => $data['movement_type'],
                'quantity_change' => $change,
                'quantity_after' => $after,
                'note' => $data['note'] ?? null,
            ]);
        });

        return to_route('admin.inventory.index')->with('success', 'Stock movement recorded.');
    }

    public function update(Request $request, InventoryItem $inventory): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:80', 'unique:inventory_items,sku,'.$inventory->id],
            'unit' => ['required', 'string', 'max:40'],
            'minimum_quantity' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $inventory->update([
            ...$data,
            'sku' => filled($data['sku'] ?? null) ? $data['sku'] : null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return to_route('admin.inventory.index')->with('success', 'Inventory item updated.');
    }
}
