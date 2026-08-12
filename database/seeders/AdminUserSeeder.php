<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@villamercedes.test'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('Password123!'),
                'email_verified_at' => now(),
            ]
        );

        if (! $admin->hasRole(Role::Administrator->value)) {
            $admin->assignRole(Role::Administrator->value);
        }
    }
}
