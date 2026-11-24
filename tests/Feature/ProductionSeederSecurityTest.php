<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class ProductionSeederSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        config(['app.env' => 'production', 'preview.enabled' => false]);
    }

    public function test_production_admin_seeding_refuses_missing_credentials(): void
    {
        config(['auth.initial_admin.email' => null, 'auth.initial_admin.password' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('INITIAL_ADMIN_EMAIL');

        $this->seed(AdminUserSeeder::class);
    }

    public function test_production_admin_seeding_uses_explicit_non_demo_credentials(): void
    {
        config([
            'auth.initial_admin.email' => 'owner@example.com',
            'auth.initial_admin.password' => 'Unique-Production-Secret-2026',
        ]);

        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('Unique-Production-Secret-2026', $admin->password));
        $this->assertDatabaseMissing('users', ['email' => 'admin@villamercedes.test']);
        $this->assertDatabaseMissing('users', ['email' => 'admin2@villamercedes.test']);
    }

    public function test_preview_admin_seeding_syncs_the_configured_demo_password(): void
    {
        config([
            'app.env' => 'admin',
            'preview.enabled' => true,
            'preview.admin.email' => 'admin@villamercedes.test',
            'preview.admin.password' => 'Password123!',
        ]);

        $admin = User::factory()->create([
            'email' => 'admin@villamercedes.test',
            'password' => Hash::make('old-preview-password'),
        ]);
        $admin->assignRole('Administrator');

        $this->seed(AdminUserSeeder::class);

        $admin->refresh();
        $this->assertTrue(Hash::check('Password123!', $admin->password));
    }
}
