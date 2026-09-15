<?php

namespace Tests\Feature\Admin;

use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontDeskCheckInTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Administrator->value);

        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);

        $this->reservation = Reservation::factory()->create([
            'user_id' => $customer->id,
            'facility_id' => Facility::factory()->create(['is_active' => true])->id,
            'status' => ReservationStatus::Approved->value,
            'total_price' => 5000.00,
        ]);
    }

    public function test_admin_can_check_a_guest_in(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('admin.reservations.check-in', $this->reservation))
            ->assertSessionHas('success');

        $reservation = $this->reservation->fresh();

        $this->assertNotNull($reservation->checked_in_at);
        $this->assertTrue($reservation->isInHouse());
        $this->assertSame(ReservationStatus::Approved, $reservation->status);
    }

    public function test_pending_reservations_cannot_be_checked_in(): void
    {
        $this->reservation->update(['status' => ReservationStatus::Pending->value]);

        $this->actingAs($this->admin)
            ->patch(route('admin.reservations.check-in', $this->reservation))
            ->assertSessionHas('error');

        $this->assertNull($this->reservation->fresh()->checked_in_at);
    }

    public function test_a_guest_cannot_be_checked_in_twice(): void
    {
        $this->reservation->update(['checked_in_at' => now()->subHour()]);

        $this->actingAs($this->admin)
            ->patch(route('admin.reservations.check-in', $this->reservation))
            ->assertSessionHas('error');
    }

    public function test_check_out_completes_the_reservation_and_releases_the_space(): void
    {
        $this->reservation->update(['checked_in_at' => now()->subDay()]);

        $this->actingAs($this->admin)
            ->patch(route('admin.reservations.check-out', $this->reservation))
            ->assertSessionHas('success');

        $reservation = $this->reservation->fresh();

        $this->assertNotNull($reservation->checked_out_at);
        $this->assertFalse($reservation->isInHouse());
        $this->assertSame(ReservationStatus::Completed, $reservation->status);
    }

    public function test_check_out_requires_a_prior_check_in(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('admin.reservations.check-out', $this->reservation))
            ->assertSessionHas('error');

        $this->assertNull($this->reservation->fresh()->checked_out_at);
    }

    public function test_customers_cannot_use_front_desk_actions(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);

        $this->actingAs($customer)
            ->patch(route('admin.reservations.check-in', $this->reservation))
            ->assertRedirect(route('admin.login'));
    }
}
