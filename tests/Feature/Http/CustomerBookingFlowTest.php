<?php

namespace Tests\Feature\Http;

use App\Enums\FacilityType as FacilityTypeEnum;
use App\Enums\GuestGender;
use App\Enums\GuestIdType;
use App\Enums\RateType;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Rate;
use App\Models\Reservation;
use App\Models\User;
use App\Services\PricingService;
use Database\Seeders\FacilityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBookingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FacilityTypeSeeder::class);
    }

    private function createFacility(string $typeSlug, bool $isActive = true, int $capacity = 2, int $maxCapacity = 4): Facility
    {
        $type = FacilityType::where('slug', $typeSlug)->first();

        $facility = Facility::factory()->create([
            'facility_type_id' => $type->id,
            'is_active' => $isActive,
            'status' => 'available',
            'capacity' => $capacity,
            'max_capacity' => $maxCapacity,
            'sort_order' => 1,
        ]);

        Rate::factory()->create([
            'facility_id' => $facility->id,
            'type' => RateType::Base->value,
            'amount' => 5000, // 50.00
            'is_active' => true,
        ]);

        Rate::factory()->create([
            'facility_id' => $facility->id,
            'type' => RateType::ExtraPerson->value,
            'amount' => 500, // 5.00
            'is_active' => true,
        ]);

        Rate::factory()->create([
            'facility_id' => $facility->id,
            'name' => PricingService::EARLY_CHECK_IN_RATE_NAME,
            'type' => RateType::AdditionalCharge->value,
            'amount' => 200,
            'unit' => 'per hour',
            'is_active' => true,
        ]);

        Rate::factory()->create([
            'facility_id' => $facility->id,
            'name' => PricingService::LATE_CHECK_OUT_RATE_NAME,
            'type' => RateType::AdditionalCharge->value,
            'amount' => 300,
            'unit' => 'per hour',
            'is_active' => true,
        ]);

        return $facility;
    }

    public function test_unauthenticated_user_is_redirected_to_login_before_booking()
    {
        $room = $this->createFacility(FacilityTypeEnum::Room->value);

        $response = $this->get(route('customer.booking.create', $room));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('url.intended', route('customer.booking.create', $room));
    }

    public function test_hall_booking_wizard_uses_attendee_and_event_labels(): void
    {
        $hall = $this->createFacility(FacilityTypeEnum::FunctionHall->value, true, 50, 80);

        $this->actingAs(User::factory()->create())->get(route('customer.booking.create', $hall))
            ->assertOk()
            ->assertSee('How many attendees?')
            ->assertSee('Event start')
            ->assertSee('Event ends at')
            ->assertSee('Four standard hours.');
    }

    public function test_authenticated_customer_can_access_booking_wizard()
    {
        $customer = User::factory()->create();
        $room = $this->createFacility(FacilityTypeEnum::Room->value);

        $response = $this->actingAs($customer)->get(route('customer.booking.create', $room));

        $this->assertStringContainsString('refreshQuote', $response->getContent());
        $this->assertStringContainsString('displayPrice.total_amount', $response->getContent());
    }

    public function test_booking_wizard_shows_gender_from_registered_profile(): void
    {
        $customer = User::factory()->create([
            'gender' => GuestGender::Male,
            'valid_id_type' => GuestIdType::PhilSys,
        ]);
        $room = $this->createFacility(FacilityTypeEnum::Room->value);

        $this->actingAs($customer)
            ->get(route('customer.booking.create', $room))
            ->assertOk()
            ->assertSee('id="guest_gender"', false)
            ->assertSee('value="Male"', false)
            ->assertSee('readonly', false)
            ->assertDontSee('Taken from your account');
    }

    public function test_online_booking_uses_account_gender_instead_of_submitted_value(): void
    {
        $customer = User::factory()->create([
            'gender' => GuestGender::Male,
            'name' => 'Juan Dela Cruz',
            'phone' => '09171234567',
            'email' => 'juan@example.com',
            'address' => 'Ilagan, Isabela',
            'nationality' => 'Filipino',
            'valid_id_type' => GuestIdType::PhilSys,
        ]);
        $room = $this->createFacility(FacilityTypeEnum::Room->value);

        $this->actingAs($customer)
            ->post(route('customer.reservations.store'), [
                'facility_id' => $room->id,
                'check_in' => now()->addDays(5)->setTime(14, 0)->format('Y-m-d\TH:i'),
                'check_out' => now()->addDays(6)->setTime(12, 0)->format('Y-m-d\TH:i'),
                'guestCount' => 1,
            ] + $this->guestInformationPayload([
                'guest_name' => 'Juan Dela Cruz',
                'guest_gender' => GuestGender::Female->value,
                'guest_phone' => '09171234567',
                'guest_email' => 'juan@example.com',
                'guest_place_of_origin' => 'Ilagan, Isabela',
                'guest_company' => null,
            ]))
            ->assertRedirect();

        $reservation = Reservation::query()->first();
        $this->assertNotNull($reservation);
        $this->assertSame(GuestGender::Male, $reservation->guest_gender);
    }

    public function test_preview_total_increases_when_guest_count_exceeds_included_capacity(): void
    {
        $customer = User::factory()->create();
        $room = $this->createFacility(FacilityTypeEnum::Room->value, true, 2, 4);

        $dates = [
            'check_in' => now()->addDays(5)->setTime(14, 0)->format('Y-m-d\TH:i'),
            'check_out' => now()->addDays(6)->setTime(12, 0)->format('Y-m-d\TH:i'),
        ];

        $included = $this->actingAs($customer)->postJson(route('customer.booking.calculate', $room), $dates + ['guestCount' => 2]);
        $extra = $this->actingAs($customer)->postJson(route('customer.booking.calculate', $room), $dates + ['guestCount' => 3]);

        $included->assertOk();
        $extra->assertOk();
        $this->assertSame(500000, $included->json('data.total_amount'));
        $this->assertSame(550000, $extra->json('data.total_amount'));
        $this->assertSame(50000, $extra->json('data.extra_person_charge'));
    }

    public function test_preview_endpoint_returns_success_with_valid_dates()
    {
        $customer = User::factory()->create();
        $room = $this->createFacility(FacilityTypeEnum::Room->value, true, 2, 4);

        $payload = [
            'check_in' => now()->addDays(5)->setTime(14, 0)->format('Y-m-d\TH:i'),
            'check_out' => now()->addDays(6)->setTime(12, 0)->format('Y-m-d\TH:i'),
            'guestCount' => 3,
        ];

        $response = $this->actingAs($customer)->postJson(route('customer.booking.calculate', $room), $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'base_amount' => 500000, // 5000 * 100 for cents
                'extra_person_charge' => 50000, // 1 extra * 500 * 100
                'early_check_in_charge' => 0,
                'late_check_out_charge' => 0,
                'total_amount' => 550000,
            ],
        ]);
    }

    public function test_preview_quotes_early_check_in_and_requires_staff_for_late_check_out(): void
    {
        $customer = User::factory()->create();
        $room = $this->createFacility(FacilityTypeEnum::Room->value, true, 2, 4);

        $payload = [
            'check_in' => now()->addDays(5)->setTime(14, 0)->format('Y-m-d\TH:i'),
            'check_out' => now()->addDays(6)->setTime(12, 0)->format('Y-m-d\TH:i'),
            'guestCount' => 2,
            'early_check_in_hours' => 2,
            'late_check_out_hours' => 1,
        ];

        $response = $this->actingAs($customer)->postJson(route('customer.booking.calculate', $room), $payload);

        $response->assertUnprocessable()->assertJsonValidationErrors('late_check_out_hours');
        $payload['late_check_out_hours'] = 0;
        $response = $this->postJson(route('customer.booking.calculate', $room), $payload);
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'base_amount' => 500000,
                'early_check_in_charge' => 40000,
                'late_check_out_charge' => 0,
                'extra_person_charge' => 0,
                'total_amount' => 540000,
            ],
        ]);
    }

    public function test_booking_wizard_offers_early_requests_and_explains_staff_approved_late_departure(): void
    {
        $this->actingAs(User::factory()->create());
        $room = $this->createFacility(FacilityTypeEnum::Room->value);

        $response = $this->get(route('customer.booking.create', $room));

        $response->assertOk();
        // Assert on the fields themselves rather than their labels, so wording
        // can change without breaking this test.
        $response->assertSee('Early check-in request');
        $response->assertSee('2 hours early');
        $response->assertSee('it requires staff approval');
        $response->assertDontSee('id="late_check_out_hours"', false);
    }

    public function test_booking_wizard_prefills_dates_and_guests_from_query(): void
    {
        $this->actingAs(User::factory()->create());
        $room = $this->createFacility(FacilityTypeEnum::Room->value);
        $arrivalDate = now()->addDays(5)->toDateString();
        $departureDate = now()->addDays(6)->toDateString();

        $response = $this->get(route('customer.booking.create', [
            'facility' => $room,
            'check_in' => $arrivalDate,
            'check_out' => $departureDate,
            'guests' => 3,
        ]));

        $response->assertOk();
        $response->assertViewHas('prefill', function (array $prefill) use ($arrivalDate, $departureDate) {
            return $prefill['guest_count'] === 3
                && $prefill['check_in'] === $arrivalDate.' 14:00'
                && $prefill['check_out'] === $departureDate.' 12:00';
        });
    }

    public function test_checked_public_availability_is_silently_revalidated_in_wizard(): void
    {
        $this->actingAs(User::factory()->create());
        $room = $this->createFacility(FacilityTypeEnum::Room->value);

        $response = $this->get(route('customer.booking.create', [
            'facility' => $room,
            'arrival' => '2026-10-12 14:00',
            'departure' => '2026-10-13 12:00',
            'guests' => 3,
            'availability_checked' => 1,
        ]));

        $response->assertOk()
            ->assertViewHas('prefill', fn (array $prefill) => $prefill['availability_checked'] === true)
            ->assertSee('"continueAfterAvailability":true', false)
            ->assertSee('this.$nextTick(() => this.refreshQuote())', false);
    }

    public function test_preview_endpoint_returns_error_for_over_capacity()
    {
        $customer = User::factory()->create();
        $room = $this->createFacility(FacilityTypeEnum::Room->value, true, 2, 4);

        $payload = [
            'check_in' => now()->addDays(5)->setTime(14, 0)->format('Y-m-d\TH:i'),
            'check_out' => now()->addDays(6)->setTime(12, 0)->format('Y-m-d\TH:i'),
            'guestCount' => 5, // Exceeds max_capacity of 4
        ];

        $response = $this->actingAs($customer)->postJson(route('customer.booking.calculate', $room), $payload);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Guest count exceeds facility maximum capacity.',
        ]);
    }

    public function test_preview_endpoint_returns_error_for_conflicting_reservation()
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $room = $this->createFacility(FacilityTypeEnum::Room->value);

        $checkIn = now()->addDays(5)->setTime(14, 0);
        $checkOut = now()->addDays(6)->setTime(12, 0);

        // Create an existing reservation
        Reservation::create([
            'user_id' => $otherCustomer->id,
            'facility_id' => $room->id,
            'reservation_code' => 'RES-12345',
            'reservation_date' => now(),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guest_count' => 2,
            'base_price' => 50.00,
            'total_price' => 50.00,
            'status' => 'approved',
            'reservation_source' => 'online',
        ]);

        $payload = [
            'check_in' => $checkIn->format('Y-m-d\TH:i'),
            'check_out' => $checkOut->format('Y-m-d\TH:i'),
            'guestCount' => 2,
        ];

        $response = $this->actingAs($customer)->postJson(route('customer.booking.calculate', $room), $payload);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);

        $this->assertStringContainsString('is already booked for the requested period', $response->json('message'));
    }

    public function test_final_submission_recalculates_price_ignoring_stale_preview()
    {
        $customer = User::factory()->create([
            'gender' => GuestGender::Female,
        ]);
        $room = $this->createFacility(FacilityTypeEnum::Room->value);

        $payload = [
            'facility_id' => $room->id,
            'check_in' => now()->addDays(10)->setTime(14, 0)->format('Y-m-d\TH:i'),
            'check_out' => now()->addDays(11)->setTime(12, 0)->format('Y-m-d\TH:i'),
            'guestCount' => 2,
            'early_check_in_hours' => 1,
            'late_check_out_hours' => 0,
            // The frontend doesn't even send price/status, but if they tried to forge it:
            'total_price' => 1.00,
            'status' => 'approved',
        ] + $this->guestInformationPayload();

        $response = $this->actingAs($customer)->post(route('customer.reservations.store'), $payload);

        $response->assertRedirect();

        // Assert reservation created with SERVER price, not forged price
        $reservation = Reservation::first();

        $this->assertNotNull($reservation);
        $this->assertEquals(1, $reservation->early_check_in_hours);
        $this->assertEquals(0, $reservation->late_check_out_hours);
        $this->assertEquals(5200, $reservation->total_price); // Saved base plus the selected early hour.
        $this->assertEquals('pending', $reservation->status->value); // Ignores forged status
        $this->assertEquals($customer->id, $reservation->user_id);
        $this->assertEquals($customer->name, $reservation->guest_name);
        $this->assertEquals($customer->address, $reservation->guest_place_of_origin);
        $this->assertEquals($customer->nationality, $reservation->guest_nationality);
        $this->assertEquals($customer->valid_id_type, $reservation->guest_valid_id);
        $this->assertEquals('female', $reservation->guest_gender->value);
    }

    public function test_overlapping_store_redirects_with_error_instead_of_server_error(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $room = $this->createFacility(FacilityTypeEnum::Room->value);

        $payload = [
            'facility_id' => $room->id,
            'check_in' => now()->addDays(10)->setTime(14, 0)->format('Y-m-d\TH:i'),
            'check_out' => now()->addDays(11)->setTime(12, 0)->format('Y-m-d\TH:i'),
            'guestCount' => 2,
        ] + $this->guestInformationPayload();

        $this->actingAs($customer)->post(route('customer.reservations.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('reservations', 1);

        $response = $this->actingAs($other)->from(route('customer.booking.create', $room))
            ->post(route('customer.reservations.store'), $payload);

        $response->assertRedirect(route('customer.booking.create', $room));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('reservations', 1);
    }
}
