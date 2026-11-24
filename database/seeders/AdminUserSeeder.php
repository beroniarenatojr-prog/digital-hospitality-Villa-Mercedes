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
        $preview = (bool) config('preview.enabled');
        $production = config('app.env') === 'production';
        $email = $preview
            ? (string) config('preview.admin.email')
            : ($production ? (string) config('auth.initial_admin.email') : 'admin@villamercedes.test');
        $password = $preview
            ? (string) config('preview.admin.password')
            : ($production ? (string) config('auth.initial_admin.password') : 'Password123!');

        if ($preview && $password === '') {
            throw new \RuntimeException('PREVIEW_ADMIN_PASSWORD is required when PREVIEW_MODE=true.');
        }

        if ($production && ! $preview) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('INITIAL_ADMIN_EMAIL must be a valid email address when APP_ENV=production.');
            }

            if (strlen($password) < 12 || $password === 'Password123!') {
                throw new \RuntimeException('INITIAL_ADMIN_PASSWORD must be at least 12 characters and must not use the local demo password when APP_ENV=production.');
            }
        }

        $this->ensureAdmin($email, 'System Administrator', $password, $preview);

        if (! $production) {
            $this->ensureAdmin(
                'admin2@villamercedes.test',
                'staff#2',
                $password,
                $preview,
                true,
            );
        }
    }

    private function ensureAdmin(string $email, string $name, string $password, bool $syncPassword, bool $syncName = false): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        $updates = [];

        if ($syncPassword) {
            $updates['password'] = Hash::make($password);
            $updates['email_verified_at'] = now();
        }

        if ($syncName && $admin->name !== $name) {
            $updates['name'] = $name;
        }

        if ($updates !== []) {
            $admin->forceFill($updates)->save();
        }

        if (! $admin->hasRole(Role::Administrator->value)) {
            $admin->assignRole(Role::Administrator->value);
        }
    }
}
