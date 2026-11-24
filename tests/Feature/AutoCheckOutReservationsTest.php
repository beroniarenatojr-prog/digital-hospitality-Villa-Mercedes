<?php

namespace Tests\Feature;

use App\Enums\FacilityStatus;
use App\Enums\FacilityType as FacilityTypeEnum;
use App\Enums\ReservationStatus;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Reservation;
use App\Support\ResortTime;
use Database\Seeders\FacilityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AutoCheckOutReservationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FacilityTypeSeeder::class);
        $this->travelTo(Carbon::parse('2026-09-21 12:00:00', 'Asia/Manila'));
    }

    public function test_it_checks_out_every_in_house_facility_type_at_its_scheduled_end(): void
    {
        $reservations = collect(FacilityTypeEnum::cases())->map(function (FacilityTypeEnum $type): Reservation {
            $facility = Facility::factory()->create([
                'facility_type_id' => FacilityType::where('slug', $type->value)->firstOrFail()->id,
                'status' => FacilityStatus::Available->value,
            ]);

            return Reservation::factory()->create([
                'facility_id' => $facility->id,
                'status' => ReservationStatus::Approved->value,
                'check_in' => ResortTime::now()->subDay(),
                'check_out' => ResortTime::now(),
                'checked_in_at' => now()->subDay(),
                'checked_out_at' => null,
            ]);
        });

        $this->artisan('reservations:auto-check-out')
            ->expectsOutput('Automatically checked out 4 reservations.')
            ->assertSuccessful();

        foreach ($reservations as $reservation) {
            $reservation->refresh();
            $this->assertSame(ReservationStatus::Completed, $reservation->status);
            $this->assertSame('2026-09-21 12:00:00', $reservation->checked_out_at->format('Y-m-d H:i:s'));
            $this->assertNotNull($reservation->completed_at);
            $this->assertFalse($reservation->isInHouse());
            $this->assertSame(FacilityStatus::Available, $reservation->facility->status);
        }
    }

    public function test_it_waits_for_late_checkout_and_ignores_guests_who_are_not_in_house(): void
    {
        $facility = Facility::factory()->create(['status' => FacilityStatus::Available->value]);
        $late = Reservation::factory()->create([
            'facility_id' => $facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => ResortTime::now()->subDay(),
            'check_out' => ResortTime::now()->subHour(),
            'late_check_out_hours' => 2,
            'checked_in_at' => now()->subDay(),
            'checked_out_at' => null,
        ]);
        $notArrived = Reservation::factory()->create([
            'facility_id' => $facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => ResortTime::now()->subDays(2),
            'check_out' => ResortTime::now()->subDay(),
            'checked_in_at' => null,
            'checked_out_at' => null,
        ]);

        $this->artisan('reservations:auto-check-out')
            ->expectsOutput('Automatically checked out 0 reservations.')
            ->assertSuccessful();

        $this->assertNull($late->fresh()->checked_out_at);
        $this->assertNull($notArrived->fresh()->checked_out_at);
        $this->assertSame(ReservationStatus::Approved, $late->fresh()->status);
        $this->assertSame(ReservationStatus::Approved, $notArrived->fresh()->status);
    }
}
