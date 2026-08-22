<?php

namespace Tests\Feature\Admin;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReservationManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;
    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Administrator->value);

        $this->customer = User::factory()->create();
        $this->customer->assignRole(Role::Customer->value);

        $this->facility = Facility::first();
    }

    // --- Access & Security ---

    public function test_unauthenticated_user_cannot_access_reservation_management(): void
    {
        $response = $this->get(route('admin.reservations.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_reservation_management(): void
    {
        $response = $this->actingAs($this->customer)->get(route('admin.reservations.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_access_reservation_listing(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reservations.index'));
        $response->assertStatus(200);
        $response->assertSee('Reservation Management');
    }

    // --- Listing & Filters ---

    public function test_reservation_listing_displays_reservations(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reservations.index'));
        $response->assertStatus(200);
        $response->assertSee($reservation->reservation_code);
        $response->assertSee($this->customer->name);
    }

    public function test_reservation_listing_filters_by_status(): void
    {
        Reservation::factory()->create(['status' => ReservationStatus::Pending->value]);
        Reservation::factory()->create(['status' => ReservationStatus::Approved->value]);

        $response = $this->actingAs($this->admin)->get(route('admin.reservations.index', ['status' => ReservationStatus::Pending->value]));
        $response->assertStatus(200);
        
        $reservations = $response->viewData('reservations');
        $this->assertTrue($reservations->every(fn($r) => $r->status === ReservationStatus::Pending));
    }

    public function test_reservation_listing_search_works(): void
    {
        $reservation = Reservation::factory()->create([
            'reservation_code' => 'VM-TEST-123',
            'user_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reservations.index', ['search' => 'VM-TEST-123']));
        $response->assertStatus(200);
        
        $reservations = $response->viewData('reservations');
        $this->assertCount(1, $reservations);
        $this->assertEquals($reservation->id, $reservations->first()->id);
    }

    // --- Details ---

    public function test_admin_can_view_reservation_details_safely(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'total_price' => 5000.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reservations.show', $reservation));
        $response->assertStatus(200);
        $response->assertSee($reservation->reservation_code);
        $response->assertSee($this->customer->name);
        $response->assertSee('5,000.00');
        
        // Security check: hash should not be rendered
        $response->assertDontSee($this->customer->password);
    }

    // --- Approval ---

    public function test_admin_can_approve_eligible_pending_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Pending->value,
        ]);

        $response = $this->actingAs($this->admin)->patch(route('admin.reservations.approve', $reservation));
        
        $response->assertRedirect();
        $response->assertSessionHas('success');
        
        $reservation->refresh();
        $this->assertEquals(ReservationStatus::Approved, $reservation->status);
        $this->assertNotNull($reservation->approved_at);
    }

    public function test_invalid_approval_fails_safely(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Cancelled->value,
        ]);

        $response = $this->actingAs($this->admin)->patch(route('admin.reservations.approve', $reservation));
        
        $response->assertRedirect();
        $response->assertSessionHas('error');
        
        $reservation->refresh();
        $this->assertEquals(ReservationStatus::Cancelled, $reservation->status);
    }

    // --- Rejection ---

    public function test_admin_can_reject_pending_reservation_with_reason(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Pending->value,
        ]);

        $response = $this->actingAs($this->admin)->patch(route('admin.reservations.reject', $reservation), [
            'reason' => 'Facility unavailable due to emergency maintenance.',
        ]);
        
        $response->assertRedirect();
        $response->assertSessionHas('success');
        
        $reservation->refresh();
        $this->assertEquals(ReservationStatus::Rejected, $reservation->status);
        $this->assertEquals('Facility unavailable due to emergency maintenance.', $reservation->rejection_reason);
        $this->assertNotNull($reservation->rejected_at);
    }

    // --- Cancellation ---

    public function test_eligible_reservation_can_be_cancelled(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Approved->value,
        ]);

        $response = $this->actingAs($this->admin)->patch(route('admin.reservations.cancel', $reservation), [
            'reason' => 'Customer requested cancellation.',
        ]);
        
        $response->assertRedirect();
        $response->assertSessionHas('success');
        
        $reservation->refresh();
        $this->assertEquals(ReservationStatus::Cancelled, $reservation->status);
        $this->assertEquals('Customer requested cancellation.', $reservation->cancellation_reason);
        $this->assertNotNull($reservation->cancelled_at);
    }

    public function test_invalid_cancellation_remains_blocked(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Completed->value,
        ]);

        $response = $this->actingAs($this->admin)->patch(route('admin.reservations.cancel', $reservation));
        
        $response->assertRedirect();
        $response->assertSessionHas('error');
        
        $reservation->refresh();
        $this->assertEquals(ReservationStatus::Completed, $reservation->status);
    }

    // --- Rescheduling ---

    public function test_availability_preview_works_for_admin(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Approved->value,
            'facility_id' => $this->facility->id,
            'check_in' => Carbon::today()->addDays(5),
            'check_out' => Carbon::today()->addDays(6),
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('admin.reservations.calculate-reschedule', $reservation), [
            'facility_id' => $this->facility->id,
            'check_in' => Carbon::today()->addDays(10)->format('Y-m-d'),
            'check_out' => Carbon::today()->addDays(12)->format('Y-m-d'),
            'guestCount' => 2,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'breakdown' => ['base_amount', 'additional_charges', 'total_amount'],
            'facility_name',
            'dates',
            'pax'
        ]);
    }

    public function test_final_reschedule_is_revalidated(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Approved->value,
            'facility_id' => $this->facility->id,
            'check_in' => Carbon::today()->addDays(5),
            'check_out' => Carbon::today()->addDays(6),
        ]);

        // Attempt to reschedule to the past (which should fail validation)
        $response = $this->actingAs($this->admin)->patch(route('admin.reservations.reschedule', $reservation), [
            'facility_id' => $this->facility->id,
            'check_in' => Carbon::today()->subDays(5)->format('Y-m-d'),
            'check_out' => Carbon::today()->subDays(3)->format('Y-m-d'),
            'guestCount' => 2,
        ]);

        $response->assertSessionHasErrors(['check_in']);
        
        $reservation->refresh();
        $this->assertEquals(Carbon::today()->addDays(5)->format('Y-m-d'), $reservation->check_in->format('Y-m-d'));
    }
}
