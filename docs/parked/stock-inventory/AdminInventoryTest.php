<?php

/*
 * PARKED, NOT LIVE.
 *
 * These tests drove the four `admin.inventory.*` HTTP routes, which were removed
 * on 2026-09-21 along with the screen. PHPUnit does not collect this file (it is
 * outside tests/ and its class is not autoloadable), so it is kept purely as the
 * record of what the removed endpoints did — including the ledger rules that
 * lived in the controller: an opening balance is written as an adjustment, and a
 * use movement can never take stock below zero.
 *
 * Restore it to tests/Feature/Admin/AdminInventoryTest.php together with the
 * routes listed in README.md and it runs again unchanged.
 */

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInventoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Administrator->value);
    }

    public function test_staff_can_add_an_item_and_record_movements(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.inventory.store'), [
                'name' => 'Pool towels',
                'sku' => 'TOWEL-01',
                'unit' => 'pieces',
                'initial_quantity' => 12,
                'minimum_quantity' => 4,
            ])
            ->assertRedirect(route('admin.inventory.index'));

        $item = InventoryItem::query()->where('sku', 'TOWEL-01')->firstOrFail();
        $this->assertSame('12.000', $item->quantity_on_hand);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $item->id,
            'movement_type' => 'adjustment',
            'quantity_change' => 12,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.inventory.movements.store', $item), [
                'movement_type' => 'use',
                'quantity' => 3,
                'note' => 'Front desk issue',
            ])
            ->assertRedirect(route('admin.inventory.index'));

        $this->assertSame('9.000', $item->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $item->id,
            'movement_type' => 'use',
            'quantity_change' => -3,
            'quantity_after' => 9,
        ]);
    }

    public function test_a_use_movement_cannot_make_stock_negative(): void
    {
        $item = InventoryItem::query()->create([
            'name' => 'Cleaning solution',
            'unit' => 'litres',
            'quantity_on_hand' => 2,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->from(route('admin.inventory.index'))
            ->post(route('admin.inventory.movements.store', $item), [
                'movement_type' => 'use',
                'quantity' => 3,
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertSame('2.000', $item->fresh()->quantity_on_hand);
        $this->assertSame(0, InventoryMovement::query()->where('inventory_item_id', $item->id)->count());
    }
}
