<?php

namespace Tests\Feature\Http;

use App\Enums\BookingServiceType;
use App\Enums\FacilityStatus;
use App\Enums\FacilityType as FacilityTypeEnum;
use App\Enums\RateType;
use App\Enums\ReservationStatus;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\PackageComponent;
use App\Models\Rate;
use App\Models\Reservation;
use App\Models\SwimmingSession;
use App\Support\StayPeriod;
use Database\Seeders\CottageSeeder;
use Database\Seeders\FacilityTypeSeeder;
use Database\Seeders\ResortServicesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AvailabilitySearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FacilityTypeSeeder::class, CottageSeeder::class, ResortServicesSeeder::class]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function facility(string $type, array $overrides = []): Facility
    {
        $facility = Facility::factory()->create([
            'facility_type_id' => FacilityType::query()->where('slug', $type)->firstOrFail()->id,
            'is_active' => true,
            'status' => FacilityStatus::Available,
            'capacity' => 4,
            'max_capacity' => 4,
            ...$overrides,
        ]);
        Rate::factory()->create([
            'facility_id' => $facility->id,
            'type' => RateType::Base,
            'amount' => 2500,
            'unit' => 'per night',
            'is_active' => true,
        ]);

        return $facility;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function search(array $query): array
    {
        return $this->getJson(route('availability.search', $query))->assertOk()->json();
    }

    public function test_guests_can_search_without_signing_in_and_no_reservation_is_created(): void
    {
        $room = $this->facility(FacilityTypeEnum::Room->value, ['name' => 'Garden Room']);
        $checkIn = now()->addDays(10)->toDateString();
        $checkOut = now()->addDays(11)->toDateString();

        $payload = $this->search([
            'service_type' => BookingServiceType::Room->value,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guests' => 2,
        ]);

        $this->assertTrue($payload['available']);
        $this->assertSame('available', $payload['status']);
        $this->assertNull($payload['message']);
        $this->assertSame($room->id, $payload['results'][0]['id']);
        $this->assertTrue($payload['results'][0]['bookable']);
        $this->assertStringContainsString('/book?facility_id='.$room->id, $payload['results'][0]['booking_url']);
        $this->assertStringContainsString('guests=2', $payload['results'][0]['booking_url']);
        $this->assertSame(250000, $payload['results'][0]['price_cents']);
        $this->assertGuest();
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_multi_day_search_keeps_published_starting_rate_instead_of_range_total(): void
    {
        $room = $this->facility(FacilityTypeEnum::Room->value, ['name' => 'Four Day Room']);

        $payload = $this->search([
            'service_type' => BookingServiceType::Room->value,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(14)->toDateString(),
            'guests' => 2,
        ]);

        $match = collect($payload['results'])->firstWhere('id', $room->id);

        $this->assertNotNull($match);
        $this->assertSame(250000, $match['price_cents']);
        $this->assertSame('per night', $match['price_unit']);
        $this->assertStringContainsString('/facilities/'.$room->id, $match['detail_url']);
        $this->assertStringContainsString('availability_checked=1', $match['detail_url']);
    }

    public function test_available_result_opens_details_before_continuing_to_reservation(): void
    {
        $room = $this->facility(FacilityTypeEnum::Room->value, ['name' => 'Detail First Room']);
        $payload = $this->search([
            'service_type' => BookingServiceType::Room->value,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'guests' => 2,
        ]);
        $result = collect($payload['results'])->firstWhere('id', $room->id);

        $this->get($result['detail_url'])
            ->assertOk()
            ->assertSee('Add to booking')
            ->assertSee(route('customer.booking.legacy', $room), false)
            ->assertSee('check live availability');
    }

    public function test_unavailable_selected_service_returns_the_standard_message_and_alternatives(): void
    {
        $room = $this->facility(FacilityTypeEnum::Room->value, ['name' => 'Only Room']);
        $hall = $this->facility(FacilityTypeEnum::FunctionHall->value, [
            'name' => 'Sapphire Hall',
            'capacity' => 40,
            'max_capacity' => 40,
        ]);
        $period = StayPeriod::fromDates(now()->addDays(12)->toDateString(), now()->addDays(13)->toDateString());
        Reservation::factory()->create([
            'facility_id' => $room->id,
            'check_in' => $period->arrival,
            'check_out' => $period->departure,
            'status' => ReservationStatus::Pending,
        ]);

        $payload = $this->search([
            'service_type' => BookingServiceType::Room->value,
            'check_in' => $period->checkInDate,
            'check_out' => $period->checkOutDate,
            'guests' => 2,
        ]);

        $this->assertFalse($payload['available']);
        $this->assertSame('fully_booked', $payload['status']);
        $this->assertSame('Fully booked for the selected period. Not available on the selected date/time.', $payload['message']);
        $this->assertSame([], $payload['results']);
        $this->assertTrue(collect($payload['alternatives'])->contains(fn (array $row): bool => $row['id'] === $hall->id));
    }

    public function test_guest_count_over_capacity_excludes_the_facility(): void
    {
        $this->facility(FacilityTypeEnum::Room->value, ['capacity' => 2, 'max_capacity' => 2]);

        $payload = $this->search([
            'service_type' => BookingServiceType::Room->value,
            'check_in' => now()->addDays(8)->toDateString(),
            'check_out' => now()->addDays(9)->toDateString(),
            'guests' => 6,
        ]);

        $this->assertFalse($payload['available']);
        $this->assertSame('unavailable', $payload['status']);
        $this->assertSame('Unavailable for the selected period. Not available on the selected date/time.', $payload['message']);
    }

    public function test_package_is_unavailable_when_a_component_is_booked(): void
    {
        $room = $this->facility(FacilityTypeEnum::Room->value);
        $package = $this->facility(FacilityTypeEnum::ResortPackage->value, ['name' => 'Overnight Bundle']);
        PackageComponent::create([
            'package_facility_id' => $package->id,
            'component_facility_id' => $room->id,
        ]);
        $period = StayPeriod::fromDates(now()->addDays(14)->toDateString(), now()->addDays(15)->toDateString());
        Reservation::factory()->create([
            'facility_id' => $room->id,
            'check_in' => $period->arrival,
            'check_out' => $period->departure,
            'status' => ReservationStatus::Approved,
        ]);

        $payload = $this->search([
            'service_type' => BookingServiceType::ResortPackage->value,
            'check_in' => $period->checkInDate,
            'check_out' => $period->checkOutDate,
            'guests' => 2,
        ]);

        $this->assertFalse($payload['available']);
        $this->assertSame('fully_booked', $payload['status']);
        $this->assertSame('Fully booked for the selected period. Not available on the selected date/time.', $payload['message']);
    }

    public function test_cottage_search_is_per_session_and_stays_public(): void
    {
        $cottage = Facility::query()->where('slug', 'cottage-01')->firstOrFail();
        $date = now()->addDays(6)->toDateString();
        $session = SwimmingSession::query()->where('slug', 'day')->firstOrFail();
        [$checkIn, $checkOut] = $session->periodFor($date);
        Reservation::factory()->create([
            'facility_id' => $cottage->id,
            'swimming_session_id' => $session->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'status' => ReservationStatus::Pending,
        ]);

        $day = $this->search([
            'service_type' => BookingServiceType::Cottage->value,
            'check_in' => $date,
            'check_out' => $date,
            'guests' => 8,
            'session' => 'day',
        ]);
        $this->assertFalse(collect($day['results'])->contains(fn ($row) => $row['id'] === $cottage->id));

        $night = $this->search([
            'service_type' => BookingServiceType::Cottage->value,
            'check_in' => $date,
            'check_out' => $date,
            'guests' => 8,
            'session' => 'night',
        ]);
        $match = collect($night['results'])->firstWhere('id', $cottage->id);
        $this->assertNotNull($match);
        $this->assertSame('night', $match['session']);
        $this->assertStringContainsString('session=night', $match['booking_url']);
        $this->assertSame(50000, $match['price_cents']);
        $this->assertGuest();
    }

    public function test_pool_search_explains_walk_in_entrance_and_suggests_reservable_options(): void
    {
        $payload = $this->search([
            'service_type' => BookingServiceType::Pool->value,
            'check_in' => now()->addDays(4)->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
            'guests' => 3,
        ]);

        $this->assertFalse($payload['available']);
        $this->assertSame('walk_in', $payload['status']);
        $this->assertStringContainsString('does not require a reservation', $payload['message']);
        $this->assertTrue(collect($payload['results'])->contains(fn ($row) => $row['name'] === 'Adult Pool' && $row['bookable'] === false));
        $this->assertNotEmpty($payload['alternatives']);
        $this->assertTrue(collect($payload['alternatives'])->every(fn ($row) => $row['bookable'] === true));
    }

    public function test_search_rejects_invalid_input_without_creating_a_reservation(): void
    {
        $this->getJson(route('availability.search', [
            'service_type' => 'spa',
            'check_in' => now()->subDay()->toDateString(),
            'check_out' => now()->subDays(3)->toDateString(),
            'guests' => 0,
        ]))->assertUnprocessable();

        $this->assertDatabaseCount('reservations', 0);
        $this->assertGuest();
    }

    public function test_same_day_search_uses_published_day_use_hours(): void
    {
        $room = $this->facility(FacilityTypeEnum::Room->value);
        $date = now()->addDays(7)->toDateString();
        $payload = $this->search([
            'service_type' => BookingServiceType::Room->value,
            'check_in' => $date,
            'check_out' => $date,
            'guests' => 2,
        ]);

        $this->assertSame('day_use', $payload['period']['preset']);
        $this->assertSame($date.' '.config('resort.stay.day_use_hours.start'), $payload['period']['arrival']);
        $this->assertStringContainsString('/book?facility_id='.$room->id, $payload['results'][0]['booking_url']);
        $this->assertSame($room->id, $payload['results'][0]['id']);
    }

    public function test_rooms_page_shows_availability_for_query_without_requiring_login(): void
    {
        $room = $this->facility(FacilityTypeEnum::Room->value, ['name' => 'Garden Room']);
        $checkIn = now()->addDays(10)->toDateString();
        $checkOut = now()->addDays(11)->toDateString();

        $this->get(route('rooms', [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guests' => 2,
        ]))
            ->assertOk()
            ->assertSee('Garden Room')
            ->assertSee('Available for your selected dates')
            ->assertSee('View details')
            ->assertSee('/facilities/'.$room->id, false)
            ->assertDontSee('/reservation/'.$room->id, false)
            ->assertSee('id="availability-space"', false)
            ->assertSee('Update dates');

        $this->assertGuest();
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_unavailable_rooms_page_shows_standard_message_and_hall_alternative(): void
    {
        $room = $this->facility(FacilityTypeEnum::Room->value, ['name' => 'Only Room']);
        $hall = $this->facility(FacilityTypeEnum::FunctionHall->value, [
            'name' => 'Sapphire Hall',
            'capacity' => 40,
            'max_capacity' => 40,
        ]);
        $period = StayPeriod::fromDates(now()->addDays(12)->toDateString(), now()->addDays(13)->toDateString());
        Reservation::factory()->create([
            'facility_id' => $room->id,
            'check_in' => $period->arrival,
            'check_out' => $period->departure,
            'status' => ReservationStatus::Pending,
        ]);

        $this->get(route('rooms', [
            'check_in' => $period->checkInDate,
            'check_out' => $period->checkOutDate,
            'guests' => 2,
        ]))
            ->assertOk()
            ->assertSee('Not available on the selected date/time.')
            ->assertSee('Sapphire Hall')
            ->assertSee('Other options for these dates');
    }

    public function test_halls_and_packages_pages_run_availability_on_load(): void
    {
        $hall = $this->facility(FacilityTypeEnum::FunctionHall->value, [
            'name' => 'Emerald Hall',
            'capacity' => 40,
            'max_capacity' => 40,
        ]);
        $room = $this->facility(FacilityTypeEnum::Room->value);
        $package = $this->facility(FacilityTypeEnum::ResortPackage->value, [
            'name' => 'Family Bundle',
            'capacity' => 6,
            'max_capacity' => 6,
        ]);
        PackageComponent::create([
            'package_facility_id' => $package->id,
            'component_facility_id' => $room->id,
        ]);
        $checkIn = now()->addDays(10)->toDateString();
        $checkOut = now()->addDays(11)->toDateString();

        $this->get(route('halls', [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guests' => 20,
        ]))
            ->assertOk()
            ->assertSee('Emerald Hall')
            ->assertSee('View details')
            ->assertSee('/facilities/'.$hall->id, false)
            ->assertDontSee('/reservation/'.$hall->id, false);

        $this->get(route('packages', [
            'checkin' => $checkIn,
            'checkout' => $checkOut,
            'guests' => 2,
        ]))
            ->assertOk()
            ->assertSee('Family Bundle')
            ->assertSee('View details')
            ->assertSee('/facilities/'.$package->id, false)
            ->assertDontSee('/reservation/'.$package->id, false);
    }

    public function test_cottage_and_pool_pages_run_availability_on_load(): void
    {
        $date = now()->addDays(6)->toDateString();

        $this->get(route('cottages', [
            'check_in' => $date,
            'check_out' => $date,
            'guests' => 8,
            'session' => 'day',
        ]))
            ->assertOk()
            ->assertSee('Cottage 01')
            ->assertSee('View details');

        $this->get(route('pools', [
            'checkin' => $date,
            'checkout' => $date,
            'guests' => 3,
        ]))
            ->assertOk()
            ->assertSee('Adult Pool')
            ->assertSee('does not require a reservation');
    }

    public function test_room_search_batches_overlap_and_rate_queries_instead_of_checking_per_facility(): void
    {
        foreach (range(1, 8) as $index) {
            $this->facility(FacilityTypeEnum::Room->value, [
                'name' => "Garden Room {$index}",
                'sort_order' => $index,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $payload = $this->search([
            'service_type' => BookingServiceType::Room->value,
            'check_in' => now()->addDays(15)->toDateString(),
            'check_out' => now()->addDays(16)->toDateString(),
            'guests' => 2,
        ]);

        $this->assertTrue($payload['available']);
        $this->assertCount(8, $payload['results']);

        $log = collect(DB::getQueryLog());
        $reservationQueries = $log
            ->filter(fn (array $query) => str_contains(strtolower($query['query']), 'from `reservations`'))
            ->count();
        $rateQueries = $log
            ->filter(fn (array $query) => str_contains(strtolower($query['query']), 'from `rates`'))
            ->count();

        $this->assertSame(1, $reservationQueries, 'Search should load blocking reservations once for the catalog.');
        $this->assertSame(1, $rateQueries, 'Search should eager-load rates instead of quoting per facility.');
        $this->assertLessThanOrEqual(20, $log->count());
    }

    public function test_cottage_search_does_not_reload_sessions_or_reservations_per_cottage(): void
    {
        $date = now()->addDays(9)->toDateString();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $payload = $this->search([
            'service_type' => BookingServiceType::Cottage->value,
            'check_in' => $date,
            'check_out' => $date,
            'guests' => 8,
            'session' => 'day',
        ]);

        $this->assertNotEmpty($payload['results']);

        $log = collect(DB::getQueryLog());
        $sessionByIdQueries = $log
            ->filter(function (array $query): bool {
                $sql = strtolower($query['query']);

                return str_contains($sql, 'from `swimming_sessions`') && str_contains($sql, 'where `id`');
            })
            ->count();
        $reservationQueries = $log
            ->filter(fn (array $query) => str_contains(strtolower($query['query']), 'from `reservations`'))
            ->count();

        $this->assertSame(0, $sessionByIdQueries, 'Cottage search should reuse the already-loaded swimming session.');
        $this->assertSame(1, $reservationQueries, 'Cottage search should load blocking reservations once.');
    }
}
