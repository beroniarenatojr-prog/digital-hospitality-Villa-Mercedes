<?php

namespace Tests\Feature;

use App\Enums\RateType;
use App\Exceptions\Pricing\InvalidPricingPeriodException;
use App\Exceptions\Pricing\NoApplicableRateException;
use App\Exceptions\Pricing\OverlappingSeasonalRatesException;
use App\Models\Facility;
use App\Models\Rate;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

    private PricingService $pricingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricingService = new PricingService();
        $this->artisan('db:seed');
    }

    private function getFacilityBySlug(string $name): Facility
    {
        return Facility::query()->where('slug', Str::slug($name))->firstOrFail();
    }

    private function calculateRoomPrice(string $roomName, int $nights = 1): int
    {
        $facility = $this->getFacilityBySlug($roomName);
        $checkIn = Carbon::now()->startOfDay()->addHours(14); // 2 PM today
        $checkOut = $checkIn->copy()->addDays($nights)->startOfDay()->addHours(12); // 12 PM in $nights days

        $breakdown = $this->pricingService->calculate($facility, $checkIn, $checkOut);
        
        return $breakdown->totalAmount;
    }
    
    private function calculateHallPrice(string $hallName): int
    {
        $facility = $this->getFacilityBySlug($hallName);
        $checkIn = Carbon::now()->startOfDay()->addHours(8); // 8 AM today
        $checkOut = $checkIn->copy()->addHours(9); // 5 PM today

        $breakdown = $this->pricingService->calculate($facility, $checkIn, $checkOut);
        
        return $breakdown->totalAmount;
    }

    public function test_deluxe_base_price(): void
    {
        $this->assertEquals(250000, $this->calculateRoomPrice('Deluxe'));
    }

    public function test_twin_deluxe_base_price(): void
    {
        $this->assertEquals(350000, $this->calculateRoomPrice('Twin Deluxe'));
    }

    public function test_superior_twin_base_price(): void
    {
        $this->assertEquals(340000, $this->calculateRoomPrice('Superior Twin'));
    }

    public function test_family_room_base_price(): void
    {
        $this->assertEquals(550000, $this->calculateRoomPrice('Family Room'));
    }

    public function test_dormitory_base_price(): void
    {
        $this->assertEquals(1000000, $this->calculateRoomPrice('Dormitory'));
    }

    public function test_diamond_hall_base_price(): void
    {
        $this->assertEquals(2500000, $this->calculateHallPrice('Diamond Hall'));
    }

    public function test_emerald_hall_base_price(): void
    {
        $this->assertEquals(1200000, $this->calculateHallPrice('Emerald Hall'));
    }

    public function test_sapphire_hall_base_price(): void
    {
        $this->assertEquals(1000000, $this->calculateHallPrice('Sapphire Hall'));
    }

    public function test_opal_hall_base_price(): void
    {
        $this->assertEquals(1000000, $this->calculateHallPrice('Opal Hall'));
    }

    public function test_early_check_in_for_one_hour(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe');
        $checkIn = Carbon::now()->startOfDay()->addHours(14);
        $checkOut = $checkIn->copy()->addDays(1)->startOfDay()->addHours(12);

        $breakdown = $this->pricingService->calculate($facility, $checkIn, $checkOut, 1, 0);

        $this->assertEquals(250000, $breakdown->baseAmount);
        $this->assertEquals(20000, $breakdown->earlyCheckInCharge); // 200 * 100
        $this->assertEquals(270000, $breakdown->totalAmount);
    }

    public function test_early_check_in_for_multiple_hours(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe');
        $checkIn = Carbon::now()->startOfDay()->addHours(14);
        $checkOut = $checkIn->copy()->addDays(1)->startOfDay()->addHours(12);

        $breakdown = $this->pricingService->calculate($facility, $checkIn, $checkOut, 3, 0);

        $this->assertEquals(60000, $breakdown->earlyCheckInCharge); // 200 * 3 * 100
        $this->assertEquals(310000, $breakdown->totalAmount);
    }

    public function test_late_check_out_for_one_hour(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe');
        $checkIn = Carbon::now()->startOfDay()->addHours(14);
        $checkOut = $checkIn->copy()->addDays(1)->startOfDay()->addHours(12);

        $breakdown = $this->pricingService->calculate($facility, $checkIn, $checkOut, 0, 1);

        $this->assertEquals(30000, $breakdown->lateCheckOutCharge); // 300 * 100
        $this->assertEquals(280000, $breakdown->totalAmount);
    }

    public function test_late_check_out_for_multiple_hours(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe');
        $checkIn = Carbon::now()->startOfDay()->addHours(14);
        $checkOut = $checkIn->copy()->addDays(1)->startOfDay()->addHours(12);

        $breakdown = $this->pricingService->calculate($facility, $checkIn, $checkOut, 0, 4);

        $this->assertEquals(120000, $breakdown->lateCheckOutCharge); // 300 * 4 * 100
        $this->assertEquals(370000, $breakdown->totalAmount);
    }

    public function test_combined_early_check_in_and_late_check_out(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe');
        $checkIn = Carbon::now()->startOfDay()->addHours(14);
        $checkOut = $checkIn->copy()->addDays(1)->startOfDay()->addHours(12);

        $breakdown = $this->pricingService->calculate($facility, $checkIn, $checkOut, 2, 2);

        $this->assertEquals(40000, $breakdown->earlyCheckInCharge);
        $this->assertEquals(60000, $breakdown->lateCheckOutCharge);
        $this->assertEquals(350000, $breakdown->totalAmount);
    }

    public function test_applicable_seasonal_rate(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe');
        
        // Add a seasonal rate
        Rate::create([
            'facility_id' => $facility->id,
            'name' => 'Summer Promo',
            'type' => RateType::Seasonal->value,
            'amount' => 2000.00,
            'unit' => 'per night',
            'effective_from' => '2026-06-01',
            'effective_until' => '2026-08-31',
            'is_active' => true,
        ]);

        $checkIn = Carbon::parse('2026-07-15 14:00:00');
        $checkOut = Carbon::parse('2026-07-16 12:00:00');

        $breakdown = $this->pricingService->calculate($facility, $checkIn, $checkOut);

        $this->assertEquals(200000, $breakdown->baseAmount);
        $this->assertEquals('Summer Promo', $breakdown->appliedRateName);
    }

    public function test_no_seasonal_rate_falls_back_to_base_rate(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe');
        
        Rate::create([
            'facility_id' => $facility->id,
            'name' => 'Summer Promo',
            'type' => RateType::Seasonal->value,
            'amount' => 2000.00,
            'unit' => 'per night',
            'effective_from' => '2026-06-01',
            'effective_until' => '2026-08-31',
            'is_active' => true,
        ]);

        // Check in is outside the promo period
        $checkIn = Carbon::parse('2026-09-15 14:00:00');
        $checkOut = Carbon::parse('2026-09-16 12:00:00');

        $breakdown = $this->pricingService->calculate($facility, $checkIn, $checkOut);

        $this->assertEquals(250000, $breakdown->baseAmount);
        $this->assertEquals('Base Rate', $breakdown->appliedRateName);
    }

    public function test_inactive_rates_are_ignored(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe');
        
        Rate::create([
            'facility_id' => $facility->id,
            'name' => 'Inactive Promo',
            'type' => RateType::Seasonal->value,
            'amount' => 1000.00,
            'unit' => 'per night',
            'effective_from' => '2026-06-01',
            'effective_until' => '2026-12-31',
            'is_active' => false,
        ]);

        $checkIn = Carbon::parse('2026-07-15 14:00:00');
        $checkOut = Carbon::parse('2026-07-16 12:00:00');

        $breakdown = $this->pricingService->calculate($facility, $checkIn, $checkOut);

        $this->assertEquals(250000, $breakdown->baseAmount);
        $this->assertEquals('Base Rate', $breakdown->appliedRateName);
    }

    public function test_invalid_reservation_period_is_rejected(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe');
        
        $checkIn = Carbon::now();
        $checkOut = Carbon::now()->subDay(); // Checkout before checkin

        $this->expectException(InvalidPricingPeriodException::class);
        $this->expectExceptionMessage('Check-out must be after check-in.');
        
        $this->pricingService->calculate($facility, $checkIn, $checkOut);
    }

    public function test_negative_additional_hours_are_rejected(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe');
        $checkIn = Carbon::now();
        $checkOut = Carbon::now()->addDay();

        $this->expectException(InvalidPricingPeriodException::class);
        $this->expectExceptionMessage('Additional hours cannot be negative.');
        
        $this->pricingService->calculate($facility, $checkIn, $checkOut, -1, 0);
    }

    public function test_missing_package_rate_is_handled_safely(): void
    {
        $facility = Facility::factory()->create([
            'name' => 'Incomplete Resort Package',
            // No rates created
        ]);

        $checkIn = Carbon::now();
        $checkOut = Carbon::now()->addDay();

        $this->expectException(NoApplicableRateException::class);
        $this->expectExceptionMessage('No active base or seasonal rate found for this facility.');

        $this->pricingService->calculate($facility, $checkIn, $checkOut);
    }
    
    public function test_overlapping_seasonal_rates_throws_exception(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe');
        
        Rate::create([
            'facility_id' => $facility->id,
            'name' => 'Promo 1',
            'type' => RateType::Seasonal->value,
            'amount' => 2000.00,
            'unit' => 'per night',
            'effective_from' => '2026-06-01',
            'effective_until' => '2026-08-31',
            'is_active' => true,
        ]);
        
        Rate::create([
            'facility_id' => $facility->id,
            'name' => 'Promo 2',
            'type' => RateType::Seasonal->value,
            'amount' => 1500.00,
            'unit' => 'per night',
            'effective_from' => '2026-07-01',
            'effective_until' => '2026-07-31',
            'is_active' => true,
        ]);

        // Check in falls in BOTH promos
        $checkIn = Carbon::parse('2026-07-15 14:00:00');
        $checkOut = Carbon::parse('2026-07-16 12:00:00');

        $this->expectException(OverlappingSeasonalRatesException::class);
        $this->expectExceptionMessage('Multiple active seasonal rates apply to this check-in date.');

        $this->pricingService->calculate($facility, $checkIn, $checkOut);
    }

    public function test_monetary_calculations_do_not_produce_floating_point_errors(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe');
        $checkIn = Carbon::now();
        $checkOut = Carbon::now()->addDays(3);

        $breakdown = $this->pricingService->calculate($facility, $checkIn, $checkOut);

        // Ensure types are int, not float
        $this->assertIsInt($breakdown->baseAmount);
        $this->assertIsInt($breakdown->totalAmount);
    }

    public function test_price_breakdown_total_equals_sum_of_components(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe');
        $checkIn = Carbon::now();
        $checkOut = Carbon::now()->addDays(2);

        $breakdown = $this->pricingService->calculate($facility, $checkIn, $checkOut, 1, 3);
        $array = $breakdown->toArray();

        $this->assertEquals(
            $array['base_amount'] + $array['early_check_in_charge'] + $array['late_check_out_charge'],
            $array['total_amount']
        );
    }
}
