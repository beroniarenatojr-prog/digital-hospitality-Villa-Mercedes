<?php

namespace Tests\Feature\Http;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_unauthenticated_user_cannot_access_admin_routes()
    {
        $response = $this->get(route('admin.dashboard'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_access_admin_routes()
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertStatus(200);

        $response = $this->actingAs($admin)->get(route('admin.facilities.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($admin)->get(route('admin.reservations.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($admin)->get(route('admin.reports.index'));
        $response->assertStatus(200);
    }
}
