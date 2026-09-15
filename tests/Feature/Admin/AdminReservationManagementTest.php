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
        $response->assertRedirect(route('admin.login'));
    }

    public function test_customer_cannot_access_reservation_management(): void
    {
        $response = $this->actingAs($this->customer)->get(route('admin.reservations.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_access_reservation_listing(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reservations.index'));
        $response->assertStatus(200);
        $response->assertSee('Reservation Management');
        $response->assertSee('Search reservations');
        $response->assertSee('Arriving today');
        $this->assertCount(0, $response->viewData('facilities'));
        $this->assertTrue($response->viewData('filterFacilities')->contains('name', 'Private Pool + Function Hall (Overnight Stay)'));

        $walkIn = $this->actingAs($this->admin)->get(route('admin.reservations.index', ['walk_in' => 1]));
        $walkIn->assertStatus(200);
        $walkIn->assertSee('admin-dialog-frame', false);
        $walkInNames = $walkIn->viewData('facilities')->pluck('name');
        $this->assertFalse($walkInNames->contains('Private Function Hall'));
        $this->assertTrue($walkInNames->contains('Private Pool + Function Hall (Overnight Stay)'));
        $this->assertTrue($walkInNames->contains('Sapphire Hall + Pool + Jacuzzi (Day Use)'));
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
        $this->assertTrue($reservations->every(fn ($r) => $r->status === ReservationStatus::Pending));
    }

    public function test_reservation_listing_search_keeps_multiple_matches_on_the_list(): void
    {
        Reservation::factory()->create([
            'reservation_code' => 'VM-TEST-123',
            'user_id' => $this->customer->id,
        ]);
        Reservation::factory()->create([
            'reservation_code' => 'VM-TEST-456',
            'user_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reservations.index', ['search' => 'VM-TEST']));
        $response->assertStatus(200);

        $reservations = $response->viewData('reservations');
        $this->assertCount(2, $reservations);
        $response->assertSee('2 matching reservations');
    }

    public function test_unique_search_keeps_the_reservation_on_the_list(): void
    {
        $reservation = Reservation::factory()->create([
            'reservation_code' => 'VM-20260906-AB12CD',
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.reservations.index', ['search' => 'VM-20260906-AB12CD']))
            ->assertOk()
            ->assertSee($reservation->reservation_code)
            ->assertSee('1 matching reservation');
    }

    public function test_search_accepts_a_code_without_dashes_or_the_suffix_only(): void
    {
        $reservation = Reservation::factory()->create([
            'reservation_code' => 'VM-20260906-AB12CD',
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.reservations.index', ['search' => 'vm 20260906 ab12cd']))
            ->assertOk()
            ->assertSee($reservation->reservation_code);

        $this->actingAs($this->admin)
            ->get(route('admin.reservations.index', ['search' => 'ab12cd']))
            ->assertOk()
            ->assertSee($reservation->reservation_code);
    }

    public function test_search_finds_walk_in_guest_name_and_phone(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->admin->id,
            'facility_id' => $this->facility->id,
            'reservation_source' => ReservationSource::WalkIn->value,
            'guest_name' => 'Juan Dela Cruz',
            'guest_phone' => '0917-555-0100',
            'guest_email' => 'juan.walkin@example.com',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.reservations.index', ['search' => 'Juan Dela Cruz']))
            ->assertOk()
            ->assertSee($reservation->reservation_code)
            ->assertSee('Juan Dela Cruz');

        $this->actingAs($this->admin)
            ->get(route('admin.reservations.index', ['search' => '09175550100']))
            ->assertOk()
            ->assertSee($reservation->reservation_code);
    }

    public function test_search_finds_registered_guest_phone(): void
    {
        $this->customer->forceFill(['phone' => '0918-555-0200'])->save();

        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.reservations.index', ['search' => '09185550200']))
            ->assertOk()
            ->assertSee($reservation->reservation_code);
    }

    public function test_arriving_today_lists_expected_guests_and_skips_the_rest(): void
    {
        $today = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => Carbon::today()->setTime(14, 0),
            'check_out' => Carbon::today()->addDay()->setTime(12, 0),
            'checked_in_at' => null,
        ]);

        $pendingToday = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Pending->value,
            'check_in' => Carbon::today()->setTime(10, 0),
            'check_out' => Carbon::today()->addDay()->setTime(12, 0),
            'checked_in_at' => null,
        ]);

        $alreadyIn = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => Carbon::today()->setTime(9, 0),
            'check_out' => Carbon::today()->addDay()->setTime(12, 0),
            'checked_in_at' => now()->subHour(),
        ]);

        $tomorrow = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => Carbon::tomorrow()->setTime(14, 0),
            'check_out' => Carbon::tomorrow()->addDay()->setTime(12, 0),
            'checked_in_at' => null,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reservations.index'));
        $response->assertStatus(200);

        $arrivals = $response->viewData('arrivalsToday');
        $this->assertTrue($arrivals->contains('id', $today->id));
        $this->assertTrue($arrivals->contains('id', $pendingToday->id));
        $this->assertFalse($arrivals->contains('id', $alreadyIn->id));
        $this->assertFalse($arrivals->contains('id', $tomorrow->id));

        $response->assertSee($today->reservation_code);
        $response->assertSee($this->customer->name);
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

    public function test_admin_can_record_a_walk_in_with_the_guest_information_sheet(): void
    {
        $facility = Facility::query()->where('slug', 'superior-barkada-room-10')->firstOrFail();

        $response = $this->actingAs($this->admin)->post(route('admin.reservations.store'), [
            'facility_id' => $facility->id,
            'check_in' => now()->addDays(40)->format('Y-m-d\TH:i'),
            'check_out' => now()->addDays(41)->format('Y-m-d\TH:i'),
            'guestCount' => 3,
            'early_check_in_hours' => 0,
            'late_check_out_hours' => 0,
        ] + $this->guestInformationPayload([
            'guest_name' => 'Juan Dela Cruz',
            'guest_email' => 'juan.walkin@example.com',
            'guest_phone' => '09175550100',
            'other_guest_names' => ['Ana Dela Cruz', 'Lito Dela Cruz', '', '', ''],
        ]));

        $reservation = Reservation::query()->where('guest_name', 'Juan Dela Cruz')->first();
        $this->assertNotNull($reservation);
        $response->assertRedirect(route('admin.reservations.show', $reservation));

        $this->assertEquals(ReservationSource::WalkIn, $reservation->reservation_source);
        $this->assertEquals(ReservationStatus::Approved, $reservation->status);
        $this->assertEquals($this->admin->id, $reservation->user_id);
        $this->assertEquals('Ilagan, Isabela', $reservation->guest_place_of_origin);
        $this->assertEquals('Filipino', $reservation->guest_nationality);
        $this->assertEquals(\App\Enums\GuestIdType::PhilSys, $reservation->guest_valid_id);
        $this->assertEquals(['Ana Dela Cruz', 'Lito Dela Cruz'], $reservation->otherGuestNames());
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
            'pax',
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
