<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CustomerUserSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::query()->firstOrCreate(
            ['email' => 'customer@villamercedes.test'],
            [
                'name' => 'Sample Customer',
                'password' => Hash::make('Password123!'),
                'email_verified_at' => now(),
                'phone' => '09123456789',
            ]
        );

        if (! $customer->hasRole(Role::Customer->value)) {
            $customer->assignRole(Role::Customer->value);
        }
    }
}
