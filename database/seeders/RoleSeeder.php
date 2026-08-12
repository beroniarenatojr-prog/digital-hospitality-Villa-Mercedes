<?php

namespace Database\Seeders;

use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (Role::values() as $roleName) {
            SpatieRole::query()->firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web']
            );
        }
    }
}
