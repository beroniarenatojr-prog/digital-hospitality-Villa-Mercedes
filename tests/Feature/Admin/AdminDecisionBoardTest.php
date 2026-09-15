<?php

namespace Tests\Feature\Admin;

use App\Enums\FacilityType as FacilityTypeEnum;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Admin\DecisionSupportService;
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
}
