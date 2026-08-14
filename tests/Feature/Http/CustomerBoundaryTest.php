<?php

namespace Tests\Feature\Http;

use App\Enums\Role;
use App\Models\Inquiry;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(); // Get facilities and roles
    }

    public function test_unauthenticated_user_cannot_access_customer_routes()
    {
        $response = $this->get(route('customer.reservations.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_customer_can_access_own_reservation()
    {
        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);

        $reservation = Reservation::factory()->create([
            'user_id' => $customer->id,
        ]);

        $response = $this->actingAs($customer)->get(route('customer.reservations.show', $reservation));
        $response->assertStatus(200);
    }

    public function test_customer_cannot_access_another_customers_reservation()
    {
        $customer1 = User::factory()->create();
        $customer1->assignRole(Role::Customer->value);

        $customer2 = User::factory()->create();
        $customer2->assignRole(Role::Customer->value);

        $reservation = Reservation::factory()->create([
            'user_id' => $customer1->id,
        ]);

        // customer2 tries to access customer1's reservation
        $response = $this->actingAs($customer2)->get(route('customer.reservations.show', $reservation));
        $response->assertStatus(403);
    }

    public function test_customer_cannot_access_admin_routes()
    {
        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);

        $response = $this->actingAs($customer)->get(route('admin.dashboard'));
        $response->assertStatus(403);
    }

    public function test_customer_cannot_access_another_customers_inquiry()
    {
        $customer1 = User::factory()->create();
        $customer1->assignRole(Role::Customer->value);

        $customer2 = User::factory()->create();
        $customer2->assignRole(Role::Customer->value);

        $inquiry = Inquiry::factory()->create([
            'user_id' => $customer1->id,
        ]);

        $response = $this->actingAs($customer2)->get(route('customer.inquiries.show', $inquiry));
        $response->assertStatus(403);
    }
}
