<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure roles exist
        SpatieRole::firstOrCreate(['name' => Role::Administrator->value]);
        SpatieRole::firstOrCreate(['name' => Role::Customer->value]);
    }

    public function test_unauthenticated_user_cannot_access_admin_dashboard(): void
    {
        $response = $this->get(route('admin.dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_admin_dashboard(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);

        $response = $this->actingAs($customer)->get(route('admin.dashboard'));
        $response->assertStatus(403);
    }

    public function test_administrator_can_access_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        
        $response->assertStatus(200);
        $response->assertViewIs('admin.dashboard');
        
        // Check for layout elements
        $response->assertSee('VM ADMIN'); // Sidebar branding
        $response->assertSee('Dashboard Overview'); // Header
        
        // Check for KPI cards
        $response->assertSee('Total Reservations (This Month)');
        $response->assertSee('Verified Payments (This Month)');
        $response->assertSee('Total Customers');
        $response->assertSee('Active Facilities');
        
        // Check for recent activity grids
        $response->assertSee('Recent Reservations');
        $response->assertSee('Recent Payments');
    }
}
