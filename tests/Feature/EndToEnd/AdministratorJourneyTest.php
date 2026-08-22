<?php

namespace Tests\Feature\EndToEnd;

use App\Enums\ReservationStatus;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministratorJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_complete_administrator_journey()
    {
        // Setup Customer and Reservation
        $customer = User::factory()->create();
        $customer->assignRole('Customer');
        $facility = Facility::where('is_active', true)->first();

        $reservation = Reservation::factory()->create([
            'user_id' => $customer->id,
            'facility_id' => $facility->id,
            'status' => ReservationStatus::Pending,
        ]);

        // 1. Admin Login
        $admin = User::whereHas('roles', fn($q) => $q->where('name', 'Administrator'))->first();
        $this->assertNotNull($admin);

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'Password123!', // AdminUserSeeder password
        ]);
        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);

        // 2. Admin Dashboard
        $response = $this->get(route('admin.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Dashboard Overview');

        // 3. Review Pending Reservation
        $response = $this->get(route('admin.reservations.index'));
        $response->assertStatus(200);
        $response->assertSee($reservation->reservation_code);

        $response = $this->get(route('admin.reservations.show', $reservation));
        $response->assertStatus(200);

        // 4. Approve Reservation
        $response = $this->patch(route('admin.reservations.approve', $reservation));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(ReservationStatus::Approved, $reservation->fresh()->status);
        $this->assertNotNull($reservation->fresh()->approved_at);

        // Ensure customer was notified
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $customer->id,
            'type' => 'App\Notifications\Reservation\ReservationStatusChangedNotification'
        ]);

        // 5. Test Facility Management
        $response = $this->get(route('admin.facilities.index'));
        $response->assertStatus(200);

        $facilityType = \App\Models\FacilityType::first();
        
        $response = $this->post(route('admin.facilities.store'), [
            'facility_type_id' => $facilityType->id,
            'name' => 'New Test Hall E2E',
            'description' => 'A beautifully designed hall for E2E testing',
            'base_capacity' => 150,
            'max_capacity' => 200,
            'is_active' => true,
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('facilities', [
            'slug' => 'new-test-hall-e2e'
        ]);

        // 6. Manage Rates
        $newFacility = Facility::where('slug', 'new-test-hall-e2e')->first();
        
        $response = $this->get(route('admin.rates.index'));
        $response->assertStatus(200);

        $response = $this->post(route('admin.rates.store', $newFacility), [
            'type' => 'base',
            'name' => 'Base Rate E2E',
            'amount' => 10000,
            'unit' => 'per night',
            'is_active' => true,
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('rates', [
            'facility_id' => $newFacility->id,
            'name' => 'Base Rate E2E',
            'amount' => 10000,
        ]);

        // 7. View Reports
        $response = $this->get(route('admin.reports.index'));
        $response->assertStatus(200);
        $response->assertSee('Reporting');
        
        // Let's make sure the report has the new reservation
        $response->assertSee($facility->name); 
    }
}
