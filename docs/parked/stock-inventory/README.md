# Parked: stock inventory (back office)

The stock inventory screen was taken **off the admin** on 2026-09-21, while the
feature waits. It is not deleted: everything needed to bring it back is either
still in the app or parked in this folder.

## What was removed

| Where | What |
|---|---|
| `routes/web.php` | the four `admin.inventory.*` routes |
| `app/Support/Admin/AdminNav.php` | the **Stock inventory** entry in the *Resort* group |
| `tests/Feature/Admin/AdminSidebarNavigationTest.php` | `'Stock inventory'` in the expected sidebar order |
| the app itself | `AdminInventoryController`, the `admin.inventory.index` view, `AdminInventoryTest` |

Because the nav entry and the routes both went, no staff member can reach the
screen and `route('admin.inventory.index')` no longer resolves. `/admin/inventory`
answers 404.

## What was deliberately kept

- `app/Models/InventoryItem.php`, `app/Models/InventoryMovement.php`
- `database/migrations/2026_09_19_010000_create_inventory_ledger_tables.php`
- the `inventory_items` / `inventory_movements` tables and **every row in them** —
  nothing drops or truncates the ledger
- the `inventory` icon in `resources/views/components/admin/nav-icon.blade.php`

## Why the three files are here and not in git

They had never been committed — they were untracked working-tree files — so
deleting them would have lost them for good. `AdminInventoryController.php` and
`AdminInventoryTest.php` are exactly what the working tree held. `index.blade.php`
was **rebuilt from the compiled Blade** left behind in `storage/framework/views/`;
markup, classes, copy and directives match that compiled output, and only the
Blade syntax was re-derived.

## To bring it back

1. Move the files home:

   ```bash
   mkdir -p resources/views/admin/inventory
   mv docs/parked/stock-inventory/index.blade.php resources/views/admin/inventory/index.blade.php
   mv docs/parked/stock-inventory/AdminInventoryController.php app/Http/Controllers/Admin/AdminInventoryController.php
   mv docs/parked/stock-inventory/AdminInventoryTest.php tests/Feature/Admin/AdminInventoryTest.php
   ```

2. Re-add the import and the routes in `routes/web.php`, inside the admin group,
   beside the other front-desk sections:

   ```php
   use App\Http\Controllers\Admin\AdminInventoryController;

   // Physical supplies and equipment stock. Reservation availability remains
   // on the facilities graph; this ledger is intentionally separate.
   Route::get('inventory', [AdminInventoryController::class, 'index'])->name('inventory.index');
   Route::post('inventory', [AdminInventoryController::class, 'store'])->name('inventory.store');
   Route::patch('inventory/{inventory}', [AdminInventoryController::class, 'update'])->name('inventory.update');
   Route::post('inventory/{inventory}/movements', [AdminInventoryController::class, 'movement'])->name('inventory.movements.store');
   ```

3. Re-add the sidebar entry to `App\Support\Admin\AdminNav::groups()`, in the
   *Resort* group between **Rates** and **Resort Services** (the order is asserted
   by a test):

   ```php
   ['label' => 'Stock inventory', 'icon' => 'inventory', 'route' => 'admin.inventory.index', 'pattern' => 'admin.inventory.*'],
   ```

4. Put `'Stock inventory'` back into the expected order in
   `AdminSidebarNavigationTest::test_the_sidebar_renders_the_map`.

5. `php artisan test --filter=AdminInventory` and
   `php artisan test --filter=AdminSidebarNavigationTest`.

## Note on page subtitles

When the screen returns, its page header has no subtitle: the muted description
line under each admin page title was removed on 2026-09-21. The `description`
prop still exists on `x-admin.page-header`, so adding one back is a single
attribute.
