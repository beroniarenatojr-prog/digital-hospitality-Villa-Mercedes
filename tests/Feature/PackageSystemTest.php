<?php

namespace Tests\Feature;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Facility;
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

        \App\Models\Rate::create([
            'facility_id' => $facility->id,
            'type' => \App\Enums\RateType::Base->value,
            'name' => 'Base Rate',
            'amount' => $baseAmount,
            'unit' => $unit,
            'is_active' => true,
        ]);
        \App\Models\Rate::create([
            'facility_id' => $facility->id,
            'type' => \App\Enums\RateType::ExtraPerson->value,
            'name' => 'Extra Person Charge',
            'amount' => $extraAmount,
            'unit' => 'per person',
            'is_active' => true,
        ]);
    }

    public function test_capacity_enforcement_succeeds_under_capacity(): void
    {
        $facility = Facility::where('name', 'Deluxe')->first();
        $this->createRatesForFacility($facility, 2500, 500, 'per night');

        $checkIn = now()->addDays(5)->startOfDay();
        $checkOut = now()->addDays(6)->startOfDay();
        
        // Deluxe capacity is 2, max is 2
        $result = $this->availabilityService->isAvailable($facility, $checkIn, $checkOut, null, 2);
        
        $this->assertTrue($result->isAvailable);
    }

    public function test_capacity_enforcement_fails_over_max_capacity(): void
    {
        $facility = Facility::where('name', 'Deluxe')->first();
        $this->createRatesForFacility($facility, 2500, 500, 'per night');

        $checkIn = now()->addDays(5)->startOfDay();
        $checkOut = now()->addDays(6)->startOfDay();
        
        // Deluxe capacity is 2, max is 2
        $result = $this->availabilityService->isAvailable($facility, $checkIn, $checkOut, null, 3);
        
        $this->assertFalse($result->isAvailable);
        $this->assertStringContainsString('exceeds facility maximum capacity', $result->reason);
    }

    public function test_package_availability_fails_when_component_booked(): void
    {
        // Sapphire Hall + Pool + Jacuzzi uses Sapphire Hall
        $package = Facility::where('name', 'Sapphire Hall + Pool + Jacuzzi')->first();
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
        $package = Facility::where('name', 'Sapphire Hall + Pool + Jacuzzi')->first();
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
        $package = Facility::where('name', 'Overnight Package')->first(); // Base: 8000 (per event), capacity: 20, max_capacity: 30, extra: 250
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
        $room = Facility::where('name', 'Room Accommodation Package')->first(); // Base: 7000 (per night), capacity: 4, max_capacity: 6, extra: 500
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
        $room = Facility::where('name', 'Room Accommodation Package')->first();
        $this->createRatesForFacility($room, 7000, 500, 'per night');

        $checkIn = now()->addDays(5)->startOfDay();
        $checkOut = now()->addDays(7)->startOfDay(); // 2 nights
        
        // 4 guests (0 extra)
        $priceBreakdown = $this->pricingService->calculate($room, $checkIn, $checkOut, 0, 0, 4);
        
        $this->assertEquals(14000 * 100, $priceBreakdown->baseAmount);
        $this->assertEquals(0, $priceBreakdown->extraPersonCharge);
        $this->assertEquals(14000 * 100, $priceBreakdown->totalAmount);
    }
}
