<?php

namespace Tests\Feature\Admin;

use App\Enums\FacilityType;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Admin\ReportingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * The back office reports on the resort's calendar, not on UTC's.
 *
 * Freezing the clock at 12:30 AM Manila is the case that exposes the
 * difference: the UTC date is still 4:30 PM the day before, so a UTC-based
 * "today" hides tonight's arrivals and tonight's occupancy until 8:00 AM.
 */
class AdminResortClockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReservationCatalog();

        SpatieRole::firstOrCreate(['name' => Role::Administrator->value]);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Administrator->value);

        Carbon::setTestNow(Carbon::parse('2026-09-17 00:30:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_arrival_lists_show_the_manila_day_not_the_utc_day(): void
    {
        $tonight = $this->blockingStay('2026-09-17 14:00:00', '2026-09-18 12:00:00', 'Arriving Tonight');
        $lastNight = $this->blockingStay('2026-09-16 14:00:00', '2026-09-17 12:00:00', 'Arrived Yesterday');

        $dashboard = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $dashboard->assertOk();
        $this->assertContains($tonight->id, $this->arrivalIds($dashboard->viewData('arrivalsToday')));
        $this->assertNotContains($lastNight->id, $this->arrivalIds($dashboard->viewData('arrivalsToday')));

        // The page header names the resort day, and "Open arrival list" filters
        // on that same day rather than on the UTC date.
        $dashboard->assertSee('September 17, 2026');
        $dashboard->assertSee('arriving_today=1', false);

        $index = $this->actingAs($this->admin)->get(route('admin.reservations.index', ['arriving_today' => 1]));
        $index->assertOk();
        $this->assertContains($tonight->id, $this->arrivalIds($index->viewData('reservations')->getCollection()));
        $this->assertNotContains($lastNight->id, $this->arrivalIds($index->viewData('reservations')->getCollection()));
    }

    public function test_tonight_occupancy_counts_the_stay_checking_in_after_manila_midnight(): void
    {
        $this->blockingStay('2026-09-17 14:00:00', '2026-09-18 12:00:00', 'Arriving Tonight');

        $dashboard = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $dashboard->assertOk();

        $rooms = collect($dashboard->viewData('occupancyByType'))->firstWhere('slug', FacilityType::Room->value);

        $this->assertNotNull($rooms, 'Expected the seeded room inventory in the occupancy panel.');
        $this->assertSame(1, $rooms['occupied'], 'Occupancy tonight must count the stay that checks in today on the resort calendar.');
    }

    public function test_daily_revenue_series_ends_on_the_manila_day(): void
    {
        $trend = app(ReportingService::class)->getVerifiedRevenueTrend('day');

        $this->assertSame('2026-09-17', $trend[array_key_last($trend)]['period']);
    }

    /**
     * An approved, not-yet-checked-in stay that reaches the resort on a day of
     * the resort calendar.
     */
    private function blockingStay(string $checkIn, string $checkOut, string $guestName): Reservation
    {
        return Reservation::factory()->create([
            'facility_id' => Facility::whereHas('facilityType', fn ($query) => $query->where('slug', FacilityType::Room->value))->firstOrFail()->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guest_name' => $guestName,
            'checked_in_at' => null,
        ]);
    }

    /**
     * @return list<int>
     */
    private function arrivalIds(iterable $arrivals): array
    {
        return collect($arrivals)->pluck('id')->all();
    }
}
