<?php

namespace Tests\Feature;

use App\Enums\ReservationSource;
use App\Models\Facility;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\PricingService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResortPackagesRedesignTest extends TestCase
{
    use RefreshDatabase;

    private ReservationService $reservations;

    private AvailabilityService $availability;

    private PricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->reservations = app(ReservationService::class);
        $this->availability = app(AvailabilityService::class);
        $this->pricing = app(PricingService::class);
    }

    public function test_packages_page_shows_only_two_flyer_packages(): void
    {
        $response = $this->get(route('packages'));

        $response->assertOk();
        $response->assertSee('Package I');
        $response->assertSee('Package II');
        $response->assertSee('Private Pool + Function Hall');
        $response->assertSee('Sapphire Hall + Private Pool with Jacuzzi');
        $response->assertSee('Day Use');
        $response->assertSee('Night Use');
        $response->assertSee('Overnight Stay');
        $response->assertSee('2 PACKAGES · 3 VARIANTS');
        $response->assertDontSee('Day Use Package');
        $response->assertDontSee('Private Pool Package');
        $response->assertDontSee('Room Accommodation Package');
    }

    public function test_package_i_reservation_does_not_block_listed_halls(): void
    {
        $package = Facility::where('name', 'Private Pool + Function Hall (Day Use)')->firstOrFail();
        $admin = User::role('Administrator')->firstOrFail();
        $day = now()->addDays(20)->toDateString();

        $this->reservations->createReservation(
            $package,
            $admin,
            Carbon::parse($day.' 09:00'),
            Carbon::parse($day.' 17:00'),
            ReservationSource::WalkIn,
            40
        );

        foreach (['Sapphire Hall', 'Emerald Hall', 'Diamond Hall', 'Opal Hall'] as $name) {
            $hall = Facility::where('name', $name)->firstOrFail();
            $result = $this->availability->isAvailable(
                $hall,
                Carbon::parse($day.' 09:00'),
                Carbon::parse($day.' 17:00'),
                null,
                40
            );

            $this->assertTrue($result->isAvailable, $name.' should stay bookable when Package I is reserved.');
        }

        $this->get(route('halls', [
            'check_in' => $day,
            'check_out' => $day,
            'guests' => 40,
        ]))
            ->assertOk()
            ->assertSee('Sapphire Hall')
            // The card's own words, which are the same button a room carries.
            // "Continue to reserve" was never on any page — it came from the design
            // mock this assertion was written against.
            ->assertSee('View details');
    }

    public function test_package_ii_blocks_sapphire_hall_with_exact_message(): void
    {
        $package = Facility::where('name', 'Sapphire Hall + Pool + Jacuzzi (Day Use)')->firstOrFail();
        $sapphire = Facility::where('name', 'Sapphire Hall')->firstOrFail();
        $admin = User::role('Administrator')->firstOrFail();
        $day = now()->addDays(21)->toDateString();

        $this->reservations->createReservation(
            $package,
            $admin,
            Carbon::parse($day.' 09:00'),
            Carbon::parse($day.' 14:00'),
            ReservationSource::WalkIn,
            40,
            ['pax_tier' => 50]
        );

        $result = $this->availability->isAvailable(
            $sapphire,
            Carbon::parse($day.' 09:00'),
            Carbon::parse($day.' 14:00'),
            null,
            40
        );

        $this->assertFalse($result->isAvailable);
        $this->assertSame(AvailabilityService::SAPPHIRE_UNAVAILABLE, $result->reason);

        $guest = User::factory()->create();
        $guest->assignRole('Customer');

        $this->actingAs($guest)
            ->postJson(route('customer.booking.calculate', $sapphire), [
                'check_in' => $day.' 09:00',
                'check_out' => $day.' 14:00',
                'guestCount' => 40,
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => AvailabilityService::SAPPHIRE_UNAVAILABLE]);
    }

    public function test_direct_sapphire_booking_blocks_package_ii(): void
    {
        $package = Facility::where('name', 'Sapphire Hall + Pool + Jacuzzi (Night Use)')->firstOrFail();
        $sapphire = Facility::where('name', 'Sapphire Hall')->firstOrFail();
        $admin = User::role('Administrator')->firstOrFail();
        $day = now()->addDays(22)->toDateString();

        $this->reservations->createReservation(
            $sapphire,
            $admin,
            Carbon::parse($day.' 17:00'),
            Carbon::parse($day.' 22:00'),
            ReservationSource::WalkIn,
            40
        );

        $result = $this->availability->isAvailable(
            $package,
            Carbon::parse($day.' 17:00'),
            Carbon::parse($day.' 22:00'),
            null,
            40
        );

        $this->assertFalse($result->isAvailable);
    }

    public function test_overnight_packages_lock_public_rooms(): void
    {
        $admin = User::role('Administrator')->firstOrFail();
        $packageI = Facility::where('name', 'Private Pool + Function Hall (Overnight Stay)')->firstOrFail();
        $packageII = Facility::where('name', 'Sapphire Hall + Pool + Jacuzzi (Overnight Stay)')->firstOrFail();

        $this->reservations->createReservation(
            $packageI,
            $admin,
            now()->addDays(23)->setTime(14, 0),
            now()->addDays(24)->setTime(12, 0),
            ReservationSource::WalkIn,
            16
        );

        foreach (['Dorm (A)', 'Dorm (B)'] as $name) {
            $room = Facility::where('name', $name)->firstOrFail();
            $this->assertFalse(
                $this->availability->isAvailable(
                    $room,
                    now()->addDays(23)->setTime(14, 0),
                    now()->addDays(24)->setTime(12, 0),
                    null,
                    2
                )->isAvailable,
                $name.' should be locked by Package I overnight.'
            );
        }

        $this->reservations->createReservation(
            $packageII,
            $admin,
            now()->addDays(25)->setTime(15, 0),
            now()->addDays(26)->setTime(11, 0),
            ReservationSource::WalkIn,
            16
        );

        foreach (['Standard Barkada (Room 11)', 'Standard Barkada (Room 12)'] as $name) {
            $room = Facility::where('name', $name)->firstOrFail();
            $this->assertFalse(
                $this->availability->isAvailable(
                    $room,
                    now()->addDays(25)->setTime(15, 0),
                    now()->addDays(26)->setTime(11, 0),
                    null,
                    2
                )->isAvailable,
                $name.' should be locked by Package II overnight.'
            );
        }
    }

    public function test_package_ii_day_use_pax_tiers(): void
    {
        $package = Facility::where('name', 'Sapphire Hall + Pool + Jacuzzi (Day Use)')->firstOrFail();
        $checkIn = now()->addDays(27)->setTime(9, 0);
        $checkOut = now()->addDays(27)->setTime(14, 0);

        $fifty = $this->pricing->calculate($package, $checkIn, $checkOut, 0, 0, 40, 50);
        $this->assertSame(10000 * 100, $fifty->baseAmount);

        $hundred = $this->pricing->calculate($package, $checkIn, $checkOut, 0, 0, 80, 100);
        $this->assertSame(15000 * 100, $hundred->baseAmount);

        $overTier = $this->availability->isAvailable($package, $checkIn, $checkOut, null, 80, 0, 0, false, 50);
        $this->assertFalse($overTier->isAvailable);
        $this->assertStringContainsString('exceeds facility maximum capacity', (string) $overTier->reason);
    }

    public function test_same_day_day_and_night_are_allowed_but_overnight_conflicts(): void
    {
        $day = Facility::where('name', 'Private Pool + Function Hall (Day Use)')->firstOrFail();
        $night = Facility::where('name', 'Private Pool + Function Hall (Night Use)')->firstOrFail();
        $overnight = Facility::where('name', 'Private Pool + Function Hall (Overnight Stay)')->firstOrFail();
        $admin = User::role('Administrator')->firstOrFail();
        $date = now()->addDays(28)->toDateString();

        $this->reservations->createReservation(
            $day,
            $admin,
            Carbon::parse($date.' 09:00'),
            Carbon::parse($date.' 17:00'),
            ReservationSource::WalkIn,
            40
        );

        $this->assertTrue(
            $this->availability->isAvailable(
                $night,
                Carbon::parse($date.' 17:00'),
                Carbon::parse($date.' 22:00'),
                null,
                40
            )->isAvailable
        );

        $this->assertFalse(
            $this->availability->isAvailable(
                $overnight,
                Carbon::parse($date.' 14:00'),
                Carbon::parse($date.' 12:00')->addDay(),
                null,
                16
            )->isAvailable
        );
    }
}
