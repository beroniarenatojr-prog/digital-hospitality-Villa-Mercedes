<?php

namespace Tests\Feature\Admin;

use App\Enums\FacilityType as FacilityTypeEnum;
use App\Enums\GuestIdType;
use App\Enums\PaymentStatus;
use App\Enums\RateType;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Payment;
use App\Models\Rate;
use App\Models\Reservation;
use App\Models\User;
use App\Services\PricingService;
use App\Support\LayoutBadges;
use App\Support\ResortTime;
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
        $response->assertSee('Reservations');
        $response->assertSee('Search reservations');
        $response->assertSee('Arriving today');
        $response->assertSee(route('admin.reservations.create'), false);
        $this->assertTrue($response->viewData('filterFacilities')->contains('name', 'Private Pool + Function Hall (Overnight Stay)'));

        // The walk-in sheet lives on its own page; the old query string and the
        // header shortcut both resolve to it.
        $this->actingAs($this->admin)
            ->get(route('admin.reservations.index', ['walk_in' => 1]))
            ->assertRedirect(route('admin.reservations.create'));

        $walkIn = $this->actingAs($this->admin)->get(route('admin.reservations.create'));
        $walkIn->assertStatus(200);
        $walkIn->assertSee('Create walk-in reservation');
        $walkInNames = $walkIn->viewData('facilities')->pluck('name');
        $this->assertFalse($walkInNames->contains('Private Function Hall'));
        $this->assertTrue($walkInNames->contains('Private Pool + Function Hall (Overnight Stay)'));
        $this->assertTrue($walkInNames->contains('Sapphire Hall + Pool + Jacuzzi (Day Use)'));
    }

    // --- Listing & Filters ---

    public function test_listing_groups_services_and_shows_balance_from_verified_payments_only(): void
    {
        $group = \App\Models\ReservationGroup::factory()->create(['total_amount' => 27500]);
        $first = Reservation::factory()->create(['reservation_group_id' => $group->id, 'facility_id' => $this->facility->id, 'total_price' => 25000]);
        $second = Reservation::factory()->create(['reservation_group_id' => $group->id, 'facility_id' => $this->facility->id, 'total_price' => 2500]);
        Payment::withoutEvents(function () use ($group, $first) {
            Payment::factory()->create(['reservation_id' => $first->id, 'reservation_group_id' => $group->id, 'amount' => 10000, 'status' => PaymentStatus::Verified]);
            Payment::factory()->create(['reservation_id' => null, 'reservation_group_id' => $group->id, 'amount' => 3750, 'status' => PaymentStatus::Verified]);
            Payment::factory()->create(['reservation_id' => null, 'reservation_group_id' => $group->id, 'amount' => 13750, 'status' => PaymentStatus::Pending]);
        });

        $response = $this->actingAs($this->admin)->get(route('admin.reservations.index', ['search' => $first->reservation_code]));
        $response->assertOk()->assertSee('Balance')->assertSee('27,500.00')->assertSee('13,750.00')
            ->assertSee($second->reservation_code)->assertSee(route('admin.reservation-groups.show', $group), false)
            ->assertDontSee('Manage group');
        $this->assertSame(1, $response->viewData('reservations')->total());
    }

    public function test_grouping_happens_before_pagination_and_keeps_standalone_bookings_separate(): void
    {
        $group = \App\Models\ReservationGroup::factory()->create();
        Reservation::factory()->count(22)->create(['reservation_group_id' => $group->id, 'user_id' => $this->customer->id, 'facility_id' => $this->facility->id]);
        Reservation::factory()->count(2)->create(['reservation_group_id' => null, 'user_id' => $this->customer->id, 'facility_id' => $this->facility->id]);
        $response = $this->actingAs($this->admin)->get(route('admin.reservations.index', ['search' => $this->customer->email]));
        $response->assertOk();
        $this->assertSame(3, $response->viewData('reservations')->total());
        $this->assertCount(3, $response->viewData('reservations'));
    }

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
        $response->assertSee('reservations match');
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
            ->assertSee('1 reservation matches');
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
        // Frozen just after Manila midnight, and the stays named on the resort
        // calendar rather than on `Carbon::today()`. The two differ here: the
        // resort day has already turned over while the process timezone (and
        // UTC) are still on the previous one, which is exactly when an arrival
        // list built from the wrong clock hides tonight's guests until morning.
        $this->travelTo(Carbon::parse('2026-09-17 00:30:00', ResortTime::timezone()));

        $today = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => '2026-09-17 14:00:00',
            'check_out' => '2026-09-18 12:00:00',
            'checked_in_at' => null,
        ]);

        $pendingToday = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Pending->value,
            'check_in' => '2026-09-17 10:00:00',
            'check_out' => '2026-09-18 12:00:00',
            'checked_in_at' => null,
        ]);

        $alreadyIn = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => '2026-09-17 09:00:00',
            'check_out' => '2026-09-18 12:00:00',
            'checked_in_at' => now()->subHour(),
        ]);

        $tomorrow = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => '2026-09-18 14:00:00',
            'check_out' => '2026-09-19 12:00:00',
            'checked_in_at' => null,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reservations.index', ['arriving_today' => 1]));
        $response->assertStatus(200);

        $arrivals = $response->viewData('reservations')->getCollection();
        $this->assertTrue($arrivals->contains('id', $today->id));
        $this->assertTrue($arrivals->contains('id', $pendingToday->id));
        $this->assertFalse($arrivals->contains('id', $alreadyIn->id));
        $this->assertFalse($arrivals->contains('id', $tomorrow->id));

        $group = \App\Models\ReservationGroup::factory()->create();
        foreach ([$today, $pendingToday, $alreadyIn, $tomorrow] as $line) {
            $line->update(['reservation_group_id' => $group->id]);
        }
        $grouped = $this->get(route('admin.reservations.index', ['arriving_today' => 1]));
        $grouped->assertOk()->assertSee(route('admin.reservation-groups.show', $group), false)
            ->assertSee('Awaiting check-in')->assertSee($today->reservation_code)->assertSee($pendingToday->reservation_code)
            ;
        $this->assertSame(1, $grouped->viewData('reservations')->total());
        $this->assertEqualsCanonicalizing([$today->id, $pendingToday->id], $grouped->viewData('reservations')->first()->reservationGroup->reservations->modelKeys());

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
        $response->assertSee('Payment summary');
        $response->assertDontSee('Pricing snapshot');
        $response->assertSeeInOrder([
            'Base price',
            'Other charges (early, late, extra person)',
            'Reservation value',
            'Verified paid',
        ]);

        // Security check: hash should not be rendered
        $response->assertDontSee($this->customer->password);
    }

    public function test_reservation_record_guides_admin_to_a_pending_payment_proof(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'total_price' => 5000.00,
            'deposit_required' => 2500.00,
            'deposit_due_at' => now()->addDay(),
        ]);
        $payment = Payment::factory()->create([
            'reservation_id' => $reservation->id,
            'amount' => 2500.00,
            'status' => PaymentStatus::Pending->value,
            'source' => 'customer_deposit',
            'payment_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reservations.show', $reservation));

        $response->assertOk()
            ->assertSee('Current booking task')
            ->assertSee(route('admin.payments.show', $payment), false)
            ->assertSeeInOrder([
                'Verified paid',
                '0.00',
                'Under review',
                '2,500.00',
                'Balance if verified',
                '2,500.00',
            ])
            ->assertSee('Reconcile the deposit or the expired hold before arranging a changed booking.');
    }

    public function test_reservation_record_omits_decision_analysis_panels(): void
    {
        $hallType = FacilityType::query()->where('slug', FacilityTypeEnum::FunctionHall->value)->firstOrFail();

        $hall = Facility::factory()->create([
            'facility_type_id' => $hallType->id,
            'name' => 'Evening Hall',
            'slug' => 'evening-hall-'.uniqid(),
            'is_active' => true,
            'status' => 'available',
        ]);

        Rate::factory()->create([
            'facility_id' => $hall->id,
            'name' => PricingService::HALL_EXTENSION_RATE_NAME,
            'type' => RateType::AdditionalCharge->value,
            'amount' => 2500,
            'unit' => 'per hour',
            'is_active' => true,
        ]);

        // 9 AM to 10 PM: the hall is already booked for its whole window.
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $hall->id,
            'status' => ReservationStatus::Pending->value,
            'check_in' => Carbon::parse('2026-09-22 09:00:00'),
            'check_out' => Carbon::parse('2026-09-22 22:00:00'),
            'total_price' => 10000.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reservations.show', $reservation));

        $response->assertOk()
            ->assertDontSee('Should you keep this booking?')
            ->assertDontSee('Can they stay longer?')
            ->assertDontSee('Safe to offer');
    }

    // --- The queue count the sidebar shows ---

    public function test_the_reservations_badge_warns_once_a_review_window_closes(): void
    {
        // Ensure at least one pending reservation exists
        $pendingRes = Reservation::factory()->create([
            'status' => ReservationStatus::Pending->value,
            'deposit_due_at' => now()->addDay(),
        ]);

        Reservation::query()
            ->where('status', ReservationStatus::Pending->value)
            ->update(['deposit_due_at' => now()->addDay()]);
        LayoutBadges::forgetPendingReservations();

        $this->assertSame(0, LayoutBadges::overdueReservationCount());
        $this->assertStringNotContainsString('bg-warning', $this->sidebarEntry(route('admin.reservations.index')));

        // A request whose window has passed is no longer waiting for an answer,
        // it is late — which is what the badge has to look like.
        $pendingRes->update(['deposit_due_at' => now()->subHour()]);
        LayoutBadges::forgetPendingReservations();

        $this->assertSame(1, LayoutBadges::overdueReservationCount());

        $entry = $this->sidebarEntry(route('admin.reservations.index'));

        $this->assertStringContainsString('bg-warning', $entry, 'A late request leaves the badge looking like ordinary work.');
        $this->assertStringContainsString('overdue for a decision', $entry, 'Colour alone does not reach every staff member.');
    }

    /** One sidebar link as the browser receives it. */
    private function sidebarEntry(string $url): string
    {
        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->getContent();

        preg_match('#<a[^>]*href="'.preg_quote($url, '#').'"[^>]*>.*?</a>#s', $html, $match);

        return $match[0] ?? '';
    }

    // --- Approval ---

    public function test_admin_can_approve_eligible_pending_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'facility_id' => Facility::whereHas('facilityType', fn ($q) => $q->where('slug', 'room'))->firstOrFail()->id,
            'status' => ReservationStatus::Pending->value,
            'check_in' => now()->addDays(10)->setTime(14, 0),
            'check_out' => now()->addDays(11)->setTime(12, 0),
            'total_price' => 1000,
            'deposit_required' => 500,
            'guest_count' => 1,
            'policy_version' => config('resort.policy.confirmed_version'),
        ]);
        Payment::factory()->create(['reservation_id' => $reservation->id, 'amount' => 500, 'status' => PaymentStatus::Verified]);

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
        $this->assertEquals(GuestIdType::PhilSys, $reservation->guest_valid_id);
        $this->assertEquals(['Ana Dela Cruz', 'Lito Dela Cruz'], $reservation->otherGuestNames());
    }

    public function test_walk_in_reservation_requires_a_guest_email_address(): void
    {
        $facility = Facility::query()->where('slug', 'superior-barkada-room-10')->firstOrFail();
        $payload = $this->guestInformationPayload([
            'guest_name' => 'Juan Dela Cruz',
            'guest_email' => '',
            'guest_phone' => '09175550100',
        ]);

        $response = $this->actingAs($this->admin)
            ->from(route('admin.reservations.create'))
            ->post(route('admin.reservations.store'), [
                'facility_id' => $facility->id,
                'check_in' => now()->addDays(40)->format('Y-m-d\TH:i'),
                'check_out' => now()->addDays(41)->format('Y-m-d\TH:i'),
                'guestCount' => 1,
                'early_check_in_hours' => 0,
                'late_check_out_hours' => 0,
                ...$payload,
            ]);

        $response->assertRedirect(route('admin.reservations.create'))
            ->assertSessionHasErrors('guest_email');

        $this->assertDatabaseMissing('reservations', [
            'guest_name' => 'Juan Dela Cruz',
            'guest_email' => null,
        ]);
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

    public function test_admin_can_reschedule_an_approved_reservation_after_payment_is_verified(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => Carbon::today()->addDays(20),
            'check_out' => Carbon::today()->addDays(21),
            'deposit_required' => 2500.00,
            'deposit_due_at' => now()->addDays(10),
        ]);
        Payment::factory()->create([
            'reservation_id' => $reservation->id,
            'amount' => 2500.00,
            'status' => PaymentStatus::Verified->value,
            'verified_by' => $this->admin->id,
            'verified_at' => now(),
            'payment_date' => now()->toDateString(),
        ]);
        $newCheckIn = Carbon::today()->addDays(40);
        $newCheckOut = Carbon::today()->addDays(41);

        $response = $this->actingAs($this->admin)->patch(route('admin.reservations.reschedule', $reservation), [
            'facility_id' => $this->facility->id,
            'check_in' => $newCheckIn->format('Y-m-d'),
            'check_out' => $newCheckOut->format('Y-m-d'),
            'guestCount' => 2,
            'early_check_in_hours' => 0,
            'late_check_out_hours' => 0,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'Reservation rescheduled successfully.');
        $this->assertSame($newCheckIn->format('Y-m-d'), $reservation->fresh()->check_in->format('Y-m-d'));
    }
}
