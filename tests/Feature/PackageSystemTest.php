<?php

namespace Tests\Feature;

use App\Enums\RateType;
use App\Enums\ReservationSource;
use App\Models\Facility;
use App\Models\Rate;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\Customer\CustomerReservationService;
use App\Services\PricingService;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageSystemTest extends TestCase
{
    use RefreshDatabase;

    private ReservationService $reservationService;

    private AvailabilityService $availabilityService;

    private PricingService $pricingService;

    private CustomerReservationService $customerReservationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->availabilityService = app(AvailabilityService::class);
        $this->pricingService = app(PricingService::class);
        $this->reservationService = app(ReservationService::class);
        $this->customerReservationService = app(CustomerReservationService::class);
    }

    private function createRatesForFacility(Facility $facility, int $baseAmount, int $extraAmount, string $unit = 'per event'): void
    {
        // The seeded catalogue already prices every facility, so replace those
        // rates instead of stacking a second base rate on top of them.
        $facility->rates()->delete();

        Rate::create([
            'facility_id' => $facility->id,
            'type' => RateType::Base->value,
            'name' => 'Base Rate',
            'amount' => $baseAmount,
            'unit' => $unit,
            'is_active' => true,
        ]);
        Rate::create([
            'facility_id' => $facility->id,
            'type' => RateType::ExtraPerson->value,
            'name' => 'Extra Person Charge',
            'amount' => $extraAmount,
            'unit' => 'per person',
            'is_active' => true,
        ]);
    }

    public function test_capacity_enforcement_succeeds_under_capacity(): void
    {
        $facility = Facility::where('name', 'Deluxe (Room 26)')->first();
        $this->createRatesForFacility($facility, 2500, 500, 'per night');

        // Keep this capacity-only assertion outside the demo booking window.
        $checkIn = now()->addDays(40)->startOfDay();
        $checkOut = now()->addDays(41)->startOfDay();

        // Deluxe included occupancy is 2, max is 4
        $result = $this->availabilityService->isAvailable($facility, $checkIn, $checkOut, null, 2);

        $this->assertTrue($result->isAvailable);
    }

    public function test_capacity_enforcement_fails_over_max_capacity(): void
    {
        $facility = Facility::where('name', 'Deluxe (Room 26)')->first();
        $this->createRatesForFacility($facility, 2500, 500, 'per night');

        $checkIn = now()->addDays(5)->startOfDay();
        $checkOut = now()->addDays(6)->startOfDay();

        // Deluxe included occupancy is 2, max is 4
        $result = $this->availabilityService->isAvailable($facility, $checkIn, $checkOut, null, 5);

        $this->assertFalse($result->isAvailable);
        $this->assertStringContainsString('exceeds facility maximum capacity', $result->reason);
    }

    public function test_package_availability_fails_when_component_booked(): void
    {
        $package = Facility::where('name', 'Sapphire Hall + Pool + Jacuzzi (Day Use)')->first();
        $sapphireHall = Facility::where('name', 'Sapphire Hall')->first();

        $this->createRatesForFacility($package, 18000, 250);
        $this->createRatesForFacility($sapphireHall, 10000, 250);

        $checkIn = now()->addDays(10)->startOfDay();
        $checkOut = now()->addDays(11)->startOfDay();
        $admin = User::role('Administrator')->first();

        // Book the component (Sapphire Hall)
        $this->reservationService->createReservation(
            $sapphireHall,
            $admin,
            $checkIn,
            $checkOut,
            ReservationSource::WalkIn,
            50
        );

        // Check if package is available
        $result = $this->availabilityService->isAvailable($package, $checkIn, $checkOut, null, 50);

        $this->assertFalse($result->isAvailable);
        $this->assertStringContainsString('Package component \'Sapphire Hall\' is not available', $result->reason);
    }

    public function test_component_availability_fails_when_package_booked(): void
    {
        $package = Facility::where('name', 'Sapphire Hall + Pool + Jacuzzi (Day Use)')->first();
        $sapphireHall = Facility::where('name', 'Sapphire Hall')->first();

        $this->createRatesForFacility($package, 18000, 250);
        $this->createRatesForFacility($sapphireHall, 10000, 250);

        $checkIn = now()->addDays(15)->startOfDay();
        $checkOut = now()->addDays(16)->startOfDay();
        $admin = User::role('Administrator')->first();

        // Book the package
        $this->reservationService->createReservation(
            $package,
            $admin,
            $checkIn,
            $checkOut,
            ReservationSource::WalkIn,
            50
        );

        // Check if component (Sapphire Hall) is available
        $result = $this->availabilityService->isAvailable($sapphireHall, $checkIn, $checkOut, null, 50);

        $this->assertFalse($result->isAvailable);
    }

    public function test_package_pricing_includes_extra_person_charges(): void
    {
        $package = Facility::where('name', 'Private Pool + Function Hall (Overnight Stay)')->first();
        $this->createRatesForFacility($package, 8000, 250);

        $checkIn = now()->addDays(5)->startOfDay();
        $checkOut = now()->addDays(6)->startOfDay();

        // 25 guests (5 extra)
        $priceBreakdown = $this->pricingService->calculate($package, $checkIn, $checkOut, 0, 0, 25);

        // 5 extra * 250 = 1250 cents = 125000 cents
        $this->assertEquals(8000 * 100, $priceBreakdown->baseAmount);
        $this->assertEquals(1250 * 100, $priceBreakdown->extraPersonCharge);
        $this->assertEquals(9250 * 100, $priceBreakdown->totalAmount);
    }

    public function test_room_pricing_includes_extra_person_charges_per_night(): void
    {
        $room = Facility::where('name', 'Standard Family (Room 21)')->first();
        $this->createRatesForFacility($room, 7000, 500, 'per night');

        $checkIn = now()->addDays(5)->startOfDay();
        $checkOut = now()->addDays(7)->startOfDay(); // 2 nights

        // 5 guests (1 extra)
        $priceBreakdown = $this->pricingService->calculate($room, $checkIn, $checkOut, 0, 0, 5);

        // 1 extra * 500 * 2 nights = 1000 = 100000 cents
        $this->assertEquals(14000 * 100, $priceBreakdown->baseAmount);
        $this->assertEquals(1000 * 100, $priceBreakdown->extraPersonCharge);
        $this->assertEquals(15000 * 100, $priceBreakdown->totalAmount);
    }

    public function test_no_extra_charge_when_under_capacity(): void
    {
        $room = Facility::where('name', 'Standard Family (Room 21)')->first();
        $this->createRatesForFacility($room, 7000, 500, 'per night');

        $checkIn = now()->addDays(5)->startOfDay();
        $checkOut = now()->addDays(7)->startOfDay(); // 2 nights

        // 4 guests (0 extra)
        $priceBreakdown = $this->pricingService->calculate($room, $checkIn, $checkOut, 0, 0, 4);

        $this->assertEquals(14000 * 100, $priceBreakdown->baseAmount);
        $this->assertEquals(0, $priceBreakdown->extraPersonCharge);
        $this->assertEquals(14000 * 100, $priceBreakdown->totalAmount);
    }

    public function test_private_function_hall_is_not_listed_or_bookable_standalone(): void
    {
        $hall = Facility::where('name', 'Private Function Hall')->firstOrFail();

        $this->assertFalse($hall->is_listed);
        $this->assertFalse($hall->isOnlineBookable());

        $this->get(route('halls'))
            ->assertOk()
            ->assertDontSee('Private Function Hall');
    }

    public function test_overnight_package_remains_online_bookable(): void
    {
        $package = Facility::where('name', 'Private Pool + Function Hall (Overnight Stay)')->firstOrFail();

        $this->assertTrue($package->isOnlineBookable());

        $this->get(route('facilities.show', $package))
            ->assertOk()
            ->assertSee('Check availability');

        $guest = User::factory()->create();
        $guest->assignRole('Customer');

        $this->actingAs($guest)->get(route('customer.booking.create', $package))
            ->assertOk()
            ->assertViewIs('customer.booking.create');
    }
}
