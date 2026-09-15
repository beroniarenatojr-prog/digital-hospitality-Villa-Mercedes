<?php

namespace Tests\Feature\Customer;

use App\Enums\FacilityStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\Reservation\ReservationStatusChangedNotification;
use App\Notifications\Reservation\ReservationWithdrawalRequestedNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReservationWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $admin;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->customer = User::factory()->create();
        $this->customer->assignRole(Role::Customer->value);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Administrator->value);

        $this->facility = Facility::factory()->create([
            'is_active' => true,
            'status' => FacilityStatus::Available->value,
        ]);
    }

    public function test_pending_guest_can_request_withdrawal_but_cannot_auto_cancel(): void
    {
        Notification::fake();

        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Pending->value,
            'check_in' => now()->addDays(10),
            'check_out' => now()->addDays(12),
        ]);

        $this->actingAs($this->customer)
            ->patch(route('customer.reservations.cancel', $reservation), ['reason' => 'Plans changed'])
            ->assertRedirect(route('customer.reservations.show', $reservation))
            ->assertSessionHas('success');

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Pending, $reservation->status);
        $this->assertNotNull($reservation->cancellation_requested_at);
        $this->assertSame('Plans changed', $reservation->cancellation_request_reason);

        Notification::assertSentTo($this->admin, ReservationWithdrawalRequestedNotification::class);
    }

    public function test_duplicate_withdrawal_request_is_rejected(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Pending->value,
            'check_in' => now()->addDays(10),
            'check_out' => now()->addDays(12),
            'cancellation_requested_at' => now(),
            'cancellation_request_reason' => 'First request',
        ]);

        $this->actingAs($this->customer)
            ->patch(route('customer.reservations.cancel', $reservation), ['reason' => 'Again'])
            ->assertRedirect(route('customer.reservations.show', $reservation))
            ->assertSessionHas('error');

        $this->assertSame('First request', $reservation->fresh()->cancellation_request_reason);
    }

    public function test_there_is_no_unrequest_route(): void
    {
        $this->assertFalse(
            collect(Route::getRoutes())->contains(
                fn ($route) => str_contains($route->uri(), 'unrequest') || str_contains($route->getName() ?? '', 'unrequest')
            )
        );
    }

    public function test_staff_confirm_cancels_and_notifies_the_guest(): void
    {
        Notification::fake();

        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Pending->value,
            'check_in' => now()->addDays(10),
            'check_out' => now()->addDays(12),
            'cancellation_requested_at' => now(),
            'cancellation_request_reason' => 'Plans changed',
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.reservations.confirm-withdrawal', $reservation))
            ->assertRedirect()
            ->assertSessionHas('success');

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertSame('Plans changed', $reservation->cancellation_reason);

        Notification::assertSentTo($this->customer, ReservationStatusChangedNotification::class);
    }

    public function test_cutoff_blocks_pending_withdrawal(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Pending->value,
            'check_in' => now()->addHours(12),
            'check_out' => now()->addDays(2),
        ]);

        $this->actingAs($this->customer)
            ->patch(route('customer.reservations.cancel', $reservation), ['reason' => 'Too late'])
            ->assertRedirect(route('customer.reservations.show', $reservation))
            ->assertSessionHas('error');

        $this->assertNull($reservation->fresh()->cancellation_requested_at);
        $this->assertSame(ReservationStatus::Pending, $reservation->fresh()->status);
    }
}
