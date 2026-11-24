<?php

namespace Tests\Feature\Admin;

use App\Enums\FacilityType as FacilityTypeEnum;
use App\Enums\RateType;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Rate;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Admin\DecisionSupportService;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDecisionBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_administrator_can_open_decision_board(): void
    {
        $admin = User::role(Role::Administrator->value)->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.decision-board'));

        $response->assertOk();
        $response->assertViewIs('admin.decision-board.index');
        $response->assertSee('Reservation Calendar')->assertDontSee('Needs your decision')->assertDontSee('Net if approved');
        $response->assertViewHas('days', fn ($days) => count($days) === 14);
    }

    public function test_calendar_date_range_does_not_load_decision_analysis(): void
    {
        $this->mock(DecisionSupportService::class, function ($mock) {
            $mock->shouldReceive('timeline')->once()->withArgs(fn ($from, $to, $type) => $from->toDateString() === '2026-11-11' && $to->toDateString() === '2026-11-12' && $type === 'room')->andReturn([]);
            $mock->shouldNotReceive('summary');
            $mock->shouldNotReceive('pendingQueue');
        });
        $this->actingAs(User::role(Role::Administrator->value)->firstOrFail())
            ->get(route('admin.decision-board', ['from' => '2026-11-11', 'to' => '2026-11-12', 'facility_type' => 'room']))
            ->assertOk()->assertViewHas('days', fn ($days) => count($days) === 2);
    }

    public function test_calendar_shows_current_restrictions_without_promising_empty_spaces_are_free(): void
    {
        $room = Facility::whereHas('facilityType', fn ($q) => $q->where('slug', 'room'))->firstOrFail();
        $room->update(['status' => 'maintenance', 'cleaning_required_at' => now()]);
        $this->actingAs(User::role(Role::Administrator->value)->firstOrFail())
            ->get(route('admin.decision-board', ['from' => '2035-01-01']))
            ->assertOk()->assertSee('Cleaning required now')->assertSee('Under Maintenance')
            ->assertSee('No reservations shown')->assertDontSee('Still free');
    }

    public function test_timeline_shows_pending_halls_and_inactive_packages_not_only_rooms(): void
    {
        $hallType = FacilityType::query()->where('slug', FacilityTypeEnum::FunctionHall->value)->firstOrFail();
        $packageType = FacilityType::query()->where('slug', FacilityTypeEnum::ResortPackage->value)->firstOrFail();

        $hall = Facility::factory()->create([
            'facility_type_id' => $hallType->id,
            'name' => 'Decision Board Hall',
            'slug' => 'decision-board-hall-'.uniqid(),
            'is_active' => true,
            'status' => 'available',
        ]);

        $retiredPackage = Facility::factory()->create([
            'facility_type_id' => $packageType->id,
            'name' => 'Retired Overnight Package',
            'slug' => 'retired-overnight-package-'.uniqid(),
            'is_active' => false,
            'status' => 'available',
        ]);

        $from = Carbon::parse('2026-09-14')->startOfDay();
        $to = $from->copy()->addDays(13)->endOfDay();

        Reservation::factory()->create([
            'facility_id' => $hall->id,
            'status' => ReservationStatus::Pending->value,
            'check_in' => $from->copy()->addDays(2)->setTime(8, 0),
            'check_out' => $from->copy()->addDays(2)->setTime(22, 0),
            'guest_count' => 20,
            'total_price' => 15000,
        ]);

        Reservation::factory()->create([
            'facility_id' => $retiredPackage->id,
            'status' => ReservationStatus::Pending->value,
            'check_in' => $from->copy()->addDay()->setTime(14, 0),
            'check_out' => $from->copy()->addDays(2)->setTime(12, 0),
            'guest_count' => 10,
            'total_price' => 25000,
        ]);

        $rows = app(DecisionSupportService::class)->timeline($from, $to);
        $byId = collect($rows)->keyBy(fn (array $row) => $row['facility']->id);

        $this->assertTrue($byId->has($hall->id), 'Active hall with a pending booking must appear on the calendar');
        $this->assertTrue($byId->has($retiredPackage->id), 'Inactive package with a pending hold must still appear on the calendar');

        $hallPending = collect($byId[$hall->id]['claims'])
            ->contains(fn (array $bar) => $bar['claim']->status === 'Pending' && ! $bar['claim']->isLinkedHold);
        $packagePending = collect($byId[$retiredPackage->id]['claims'])
            ->contains(fn (array $bar) => $bar['claim']->status === 'Pending' && ! $bar['claim']->isLinkedHold);

        $this->assertTrue($hallPending);
        $this->assertTrue($packagePending);

        $impact = app(DecisionSupportService::class)->analyze(
            Reservation::query()->where('facility_id', $hall->id)->firstOrFail()
        );
        $this->assertStringContainsString('This booking keeps', $impact->explanations[0]);
        $this->assertStringContainsString('waiting for your answer', implode(' ', $impact->explanations));
    }

    public function test_extra_hours_are_refused_once_the_hall_closes(): void
    {
        $hall = $this->hallWithExtensionRate();

        // A full hall day: 9 AM to 10 PM is already the venue's whole window.
        $reservation = Reservation::factory()->create([
            'facility_id' => $hall->id,
            'status' => ReservationStatus::Pending->value,
            'check_in' => Carbon::parse('2026-09-22 09:00:00'),
            'check_out' => Carbon::parse('2026-09-22 22:00:00'),
        ]);

        $advice = app(DecisionSupportService::class)->extraHourOptions($reservation);

        $this->assertSame('Sep 22, 10:00 PM', $advice['closes_at']);
        $this->assertSame(0, $advice['offerable_hours']);
        $this->assertCount(1, $advice['options'], 'Only the first refused hour is listed, not twelve promises');
        $this->assertTrue($advice['options'][0]['collides']);
        $this->assertSame('Past the hall’s 10:00 PM closing time', $advice['options'][0]['verdict']);
        $this->assertSame(2500.0, $advice['hourly_rate']);
    }

    public function test_hall_extra_hours_use_the_hall_rate_and_fit_inside_its_hours(): void
    {
        $hall = $this->hallWithExtensionRate();

        $reservation = Reservation::factory()->create([
            'facility_id' => $hall->id,
            'status' => ReservationStatus::Pending->value,
            'check_in' => Carbon::parse('2026-09-22 09:00:00'),
            'check_out' => Carbon::parse('2026-09-22 13:00:00'),
        ]);

        $advice = app(DecisionSupportService::class)->extraHourOptions($reservation);

        // Four standard hours to 1 PM leaves nine hours before the 10 PM shutters.
        $this->assertSame(PricingService::HALL_EXTENSION_RATE_NAME, $advice['rate_name']);
        $this->assertSame(2500.0, $advice['hourly_rate'], 'The room late check-out rate must not price a hall');
        $this->assertSame(9, $advice['offerable_hours']);
        $this->assertCount(10, $advice['options']);

        $lastSafe = collect($advice['options'])->last(fn (array $option) => ! $option['collides']);
        $this->assertSame(9, $lastSafe['hours']);
        $this->assertSame('Sep 22, 10:00 PM', $lastSafe['blocked_release']);
        $this->assertSame(22500.0, $lastSafe['charge']);
        $this->assertSame('Past the hall’s 10:00 PM closing time', $advice['options'][9]['verdict']);
    }

    private function hallWithExtensionRate(): Facility
    {
        $hallType = FacilityType::query()->where('slug', FacilityTypeEnum::FunctionHall->value)->firstOrFail();

        $hall = Facility::factory()->create([
            'facility_type_id' => $hallType->id,
            'name' => 'Board Hall',
            'slug' => 'board-hall-'.uniqid(),
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

        return $hall;
    }
}
