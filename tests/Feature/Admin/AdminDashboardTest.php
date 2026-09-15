<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
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
        $response->assertRedirect(route('admin.login'));
    }

    public function test_customer_cannot_access_admin_dashboard(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);

        $response = $this->actingAs($customer)->get(route('admin.dashboard'));
        $response->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_administrator_can_access_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.dashboard');

        // Check for layout elements
        $response->assertSee('ADMIN'); // Sidebar branding
        $response->assertSee('Today’s operations');
        $response->assertSee('Open reservations');

        // Check for the live operational surface
        $response->assertSee('Needs attention');
        $response->assertSee('Reservations to approve');
        $response->assertSee('Payments to verify');
        $response->assertSee('Arrivals today');
        $response->assertSee('Next 7 days');

        // Check for data-driven insights and recent activity
        $response->assertSee('Recent Reservations');
        $response->assertSee('Recent Payments');
        $response->assertSee('Occupancy tonight');
        $response->assertSee('Verified revenue');

        // Decorative and non-actionable dashboard treatments stay removed.
        $response->assertDontSee('Dashboard Overview');
        $response->assertDontSee('Welcome back');
        $response->assertDontSee('Total Reservations (This Month)');
        $response->assertDontSee('Verified Payments (This Month)');
        $response->assertDontSee('Total Customers');
        $response->assertDontSee('Active Facilities');
        $response->assertDontSee('bg-gradient-to-r', false);
    }

    public function test_flat_dashboard_component_variants_preserve_existing_defaults(): void
    {
        $flatTable = Blade::render('<x-admin.table variant="flat" />');
        $defaultTable = Blade::render('<x-admin.table />');
        $compactBadge = Blade::render('<x-badge status="pending" variant="compact" />');
        $defaultBadge = Blade::render('<x-badge status="pending" />');

        $this->assertStringContainsString('overflow-hidden rounded-md border border-gray-200', $flatTable);
        $this->assertStringNotContainsString('shadow', $flatTable);
        $this->assertStringNotContainsString('lg:-mx-8', $flatTable);
        $this->assertStringContainsString('lg:-mx-8', $defaultTable);
        $this->assertStringContainsString('shadow', $defaultTable);
        $this->assertStringContainsString('rounded-sm border border-current', $compactBadge);
        $this->assertStringContainsString('rounded-full', $defaultBadge);
    }

    public function test_admin_shell_keeps_sidebar_in_document_flow(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);

        $html = $this->actingAs($admin)->get(route('admin.dashboard'))->getContent();

        $this->assertStringContainsString('class="admin-shell"', $html);
        $this->assertStringContainsString('class="admin-sidebar"', $html);
        $this->assertStringContainsString('class="admin-main"', $html);
        $this->assertDoesNotMatchRegularExpression('/<aside[^>]*\bfixed\b/', $html);
        $this->assertDoesNotMatchRegularExpression('/<aside[^>]*\babsolute\b/', $html);
    }
}
