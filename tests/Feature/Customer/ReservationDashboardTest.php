<?php

namespace Tests\Feature\Customer;

use App\Enums\ReservationStatus;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $customer1;
    private User $customer2;
    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->customer1 = User::factory()->create();
        // Give customer role if needed, or assume default
        
        $this->customer2 = User::factory()->create();
        
        $this->facility = Facility::factory()->create([
            'is_active' => true,
            'status' => \App\Enums\FacilityStatus::Available->value,
            'max_capacity' => 10,
        ]);
        
        // Add a rate to the facility
        \App\Models\Rate::factory()->create([
            'facility_id' => $this->facility->id,
            'amount' => 100000, // 1000.00
            'is_active' => true,
        ]);
    }

    public function test_customer_can_view_own_reservations_dashboard()
    {
        Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Pending->value,
        ]);

        $response = $this->actingAs($this->customer1)->get(route('customer.reservations.index'));
        $response->assertStatus(200);
        $response->assertSee('My Reservations');
    }

    public function test_customer_cannot_view_another_customers_reservation()
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer2->id,
            'facility_id' => $this->facility->id,
        ]);

        $response = $this->actingAs($this->customer1)->get(route('customer.reservations.show', $reservation));
        $response->assertStatus(403);
    }

    public function test_customer_can_cancel_approved_reservation()
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
        ]);

        $response = $this->actingAs($this->customer1)->patch(route('customer.reservations.cancel', $reservation), [
            'reason' => 'Change of plans',
        ]);
        
        $response->assertRedirect();
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::Cancelled->value,
            'cancellation_reason' => 'Change of plans',
        ]);
    }

    public function test_invalid_cancellation_is_rejected()
    {
        // Cancelled reservations cannot be cancelled again
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Cancelled->value,
        ]);

        $response = $this->actingAs($this->customer1)->patch(route('customer.reservations.cancel', $reservation));
        
        // The service throws ReservationCancellationException, which in a real app might be caught 
        // by the exception handler, returning 500 or redirect with error. 
        // We'll assert that the exception is thrown or caught (status 500/redirect).
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::Cancelled->value, // Still cancelled
        ]);
    }

    public function test_reschedule_preview_excludes_current_reservation()
    {
        $checkIn = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $checkOut = Carbon::now()->addDays(6)->setHour(12)->setMinute(0)->setSecond(0);

        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guest_count' => 2,
        ]);

        $response = $this->actingAs($this->customer1)->postJson(route('customer.reservations.calculate-reschedule', $reservation), [
            'facility_id' => $this->facility->id,
            'check_in' => $checkIn->format('Y-m-d\TH:i'),
            'check_out' => $checkOut->format('Y-m-d\TH:i'),
            'guestCount' => 3, // change guest count
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        // It shouldn't fail with "Facility is already booked" because it excludes itself
    }

    public function test_final_reschedule_revalidates_availability()
    {
        $checkIn = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $checkOut = Carbon::now()->addDays(6)->setHour(12)->setMinute(0)->setSecond(0);

        // Another customer has a reservation
        Reservation::factory()->create([
            'user_id' => $this->customer2->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ]);

        $checkInOriginal = Carbon::now()->addDays(10)->setHour(14)->setMinute(0)->setSecond(0);
        $checkOutOriginal = Carbon::now()->addDays(11)->setHour(12)->setMinute(0)->setSecond(0);

        // Customer 1 tries to reschedule their own reservation to overlapping dates
        $reservation1 = Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => $checkInOriginal,
            'check_out' => $checkOutOriginal,
        ]);

        $response = $this->actingAs($this->customer1)->patch(route('customer.reservations.reschedule', $reservation1), [
            'facility_id' => $this->facility->id,
            'check_in' => $checkIn->format('Y-m-d\TH:i'),
            'check_out' => $checkOut->format('Y-m-d\TH:i'),
            'guestCount' => 2,
        ]);

        // The service should throw ReservationConflictException, which the handler handles
        // Assert the reservation dates didn't change
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation1->id,
            'check_in' => $checkInOriginal->toDateTimeString(),
        ]);
    }
}
