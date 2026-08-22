<?php

namespace Tests\Feature\Http;

use App\Enums\FacilityType as FacilityTypeEnum;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\User;
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
            'sort_order' => 1
        ]);

        \App\Models\Rate::factory()->create([
            'facility_id' => $facility->id,
            'type' => \App\Enums\RateType::Base->value,
            'amount' => 5000, // 50.00
            'is_active' => true,
        ]);

        \App\Models\Rate::factory()->create([
            'facility_id' => $facility->id,
            'type' => \App\Enums\RateType::ExtraPerson->value,
            'amount' => 500, // 5.00
            'is_active' => true,
        ]);

        return $facility;
    }

    public function test_unauthenticated_user_can_access_booking_wizard()
    {
        $room = $this->createFacility(FacilityTypeEnum::Room->value);

        $response = $this->get(route('customer.booking.create', $room));

        $response->assertStatus(200);
        $response->assertViewIs('customer.booking.create');
    }

    public function test_authenticated_customer_can_access_booking_wizard()
    {
        $customer = User::factory()->create();
        $room = $this->createFacility(FacilityTypeEnum::Room->value);

        $response = $this->actingAs($customer)->get(route('customer.booking.create', $room));

        $response->assertStatus(200);
        $response->assertViewIs('customer.booking.create');
        $response->assertSee($room->name);
    }

    public function test_preview_endpoint_returns_success_with_valid_dates()
    {
        $customer = User::factory()->create();
        $room = $this->createFacility(FacilityTypeEnum::Room->value, true, 2, 4);

        $payload = [
            'check_in' => now()->addDays(5)->startOfDay()->format('Y-m-d\TH:i'),
            'check_out' => now()->addDays(6)->startOfDay()->format('Y-m-d\TH:i'),
            'guestCount' => 3
        ];

        $response = $this->actingAs($customer)->postJson(route('customer.booking.calculate', $room), $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'base_amount' => 500000, // 5000 * 100 for cents
                'extra_person_charge' => 50000, // 1 extra * 500 * 100
                'total_amount' => 550000,
            ]
        ]);
    }

    public function test_preview_endpoint_returns_error_for_over_capacity()
    {
        $customer = User::factory()->create();
        $room = $this->createFacility(FacilityTypeEnum::Room->value, true, 2, 4);

        $payload = [
            'check_in' => now()->addDays(5)->format('Y-m-d\TH:i'),
            'check_out' => now()->addDays(6)->format('Y-m-d\TH:i'),
            'guestCount' => 5 // Exceeds max_capacity of 4
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

        $checkIn = now()->addDays(5)->startOfDay();
        $checkOut = now()->addDays(6)->startOfDay();

        // Create an existing reservation
        \App\Models\Reservation::create([
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
            'reservation_source' => 'online'
        ]);

        $payload = [
            'check_in' => $checkIn->format('Y-m-d\TH:i'),
            'check_out' => $checkOut->format('Y-m-d\TH:i'),
            'guestCount' => 2
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
        $customer = User::factory()->create();
        $room = $this->createFacility(FacilityTypeEnum::Room->value);

        $payload = [
            'facility_id' => $room->id,
            'check_in' => now()->addDays(10)->startOfDay()->format('Y-m-d\TH:i'),
            'check_out' => now()->addDays(11)->startOfDay()->format('Y-m-d\TH:i'),
            'guestCount' => 2,
            // The frontend doesn't even send price/status, but if they tried to forge it:
            'total_price' => 1.00,
            'status' => 'approved'
        ];

        $response = $this->actingAs($customer)->post(route('customer.reservations.store'), $payload);

        $response->assertRedirect();
        
        // Assert reservation created with SERVER price, not forged price
        $reservation = \App\Models\Reservation::first();
        
        $this->assertNotNull($reservation);
        $this->assertEquals(5000, $reservation->total_price); // 5000.00 DB decimal
        $this->assertEquals('pending', $reservation->status->value); // Ignores forged status
        $this->assertEquals($customer->id, $reservation->user_id);
    }
}


