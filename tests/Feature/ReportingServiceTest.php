<?php

namespace Tests\Feature;

use App\Enums\FacilityType;
use App\Enums\PaymentStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\Admin\ReportingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Setup facilities and types through seeders to ensure enum slugs are correct
        $this->seedReservationCatalog();
        $this->service = app(ReportingService::class);
    }

    public function test_booking_summary()
    {
        $room = Facility::whereHas('facilityType', fn ($q) => $q->where('slug', FacilityType::Room->value))->first();
        $hall = Facility::whereHas('facilityType', fn ($q) => $q->where('slug', FacilityType::FunctionHall->value))->first();

        Reservation::factory()->create([
            'facility_id' => $room->id,
            'status' => ReservationStatus::Approved->value,
            'reservation_source' => ReservationSource::Online->value,
        ]);
        Reservation::factory()->create([
            'facility_id' => $hall->id,
            'status' => ReservationStatus::Pending->value,
            'reservation_source' => ReservationSource::WalkIn->value,
        ]);

        $summary = $this->service->getBookingSummary();

        $this->assertEquals(2, $summary['total']);
        $this->assertEquals(1, $summary['by_status'][ReservationStatus::Approved->value]);
        $this->assertEquals(1, $summary['by_status'][ReservationStatus::Pending->value]);
        $this->assertEquals(1, $summary['by_facility_type'][FacilityType::Room->value]);
        $this->assertEquals(1, $summary['by_facility_type'][FacilityType::FunctionHall->value]);
    }

    public function test_booking_trends()
    {
        $room = Facility::whereHas('facilityType', fn ($q) => $q->where('slug', FacilityType::Room->value))->first();

        // One in Jan, Two in Feb
        Reservation::factory()->create([
            'facility_id' => $room->id,
            'created_at' => Carbon::parse('2026-01-15'),
        ]);
        Reservation::factory()->create([
            'facility_id' => $room->id,
            'created_at' => Carbon::parse('2026-02-10'),
        ]);
        Reservation::factory()->create([
            'facility_id' => $room->id,
            'created_at' => Carbon::parse('2026-02-20'),
        ]);

        $trends = $this->service->getBookingTrends(
            'month',
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-02-28')
        );

        $byPeriod = collect($trends)->mapWithKeys(fn (array $point) => [$point['period'] => $point['value']]);
        $this->assertEquals(1, $byPeriod['2026-01']);
        $this->assertEquals(2, $byPeriod['2026-02']);
        $this->assertCount(2, $trends);

        $yearly = $this->service->getBookingTrends(
            'year',
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-12-31')
        );
        $this->assertEquals(3, collect($yearly)->firstWhere('period', '2026')['value']);

        $weekly = $this->service->getBookingTrends(
            'week',
            Carbon::parse('2026-02-01'),
            Carbon::parse('2026-02-28')
        );
        $this->assertNotEmpty($weekly);
        $this->assertEquals(2, collect($weekly)->sum('value'));
        $this->assertTrue(collect($weekly)->every(fn (array $point) => isset($point['label'], $point['period'], $point['value'])));
    }

    public function test_revenue_and_payment_summary()
    {
        $room = Facility::whereHas('facilityType', fn ($q) => $q->where('slug', FacilityType::Room->value))->first();

        // 1. Approved reservation with a verified payment
        $res1 = Reservation::factory()->create([
            'facility_id' => $room->id,
            'status' => ReservationStatus::Approved->value,
            'total_price' => 5000,
        ]);
        Payment::factory()->create([
            'reservation_id' => $res1->id,
            'amount' => 2500,
            'status' => PaymentStatus::Verified->value,
        ]);

        // 2. Pending reservation with a pending payment
        $res2 = Reservation::factory()->create([
            'facility_id' => $room->id,
            'status' => ReservationStatus::Pending->value,
            'total_price' => 3000,
        ]);
        Payment::factory()->create([
            'reservation_id' => $res2->id,
            'amount' => 1500,
            'status' => PaymentStatus::Pending->value,
        ]);

        // 3. Cancelled reservation (should not be in revenue)
        Reservation::factory()->create([
            'facility_id' => $room->id,
            'status' => ReservationStatus::Cancelled->value,
            'total_price' => 10000,
        ]);

        $revenue = $this->service->getRevenueSummary();
        $payments = $this->service->getPaymentSummary();

        // Reservation value should only be Approved + Pending = 5000 + 3000 = 8000
        $this->assertEquals(8000, $revenue['reservation_value']);

        // Verified Payment revenue should be 2500
        $this->assertEquals(2500, $revenue['verified_payment_revenue']);

        $this->assertEquals(2, $payments['total_payments']);
        $this->assertEquals(1, $payments['by_status'][PaymentStatus::Verified->value]['count']);
        $this->assertEquals(2500, $payments['by_status'][PaymentStatus::Verified->value]['total_amount']);
    }

    public function test_occupancy_by_type_counts_blocking_reservations_today(): void
    {
        $room = Facility::whereHas('facilityType', fn ($q) => $q->where('slug', FacilityType::Room->value))->first();
        $this->assertNotNull($room);

        Reservation::factory()->create([
            'facility_id' => $room->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => now()->startOfDay()->addHours(14),
            'check_out' => now()->addDay()->startOfDay()->addHours(12),
        ]);

        $occupancy = $this->service->getOccupancyByType(now());
        $row = collect($occupancy)->firstWhere('slug', FacilityType::Room->value);

        $this->assertNotNull($row);
        $this->assertGreaterThan(0, $row['occupied']);
        $this->assertGreaterThanOrEqual($row['occupied'], $row['total']);
        $this->assertSame((int) round(($row['occupied'] / $row['total']) * 100), $row['pct']);
    }

    public function test_verified_revenue_trend_fills_daily_periods(): void
    {
        $room = Facility::whereHas('facilityType', fn ($q) => $q->where('slug', FacilityType::Room->value))->first();
        $reservation = Reservation::factory()->create(['facility_id' => $room->id]);

        Payment::factory()->create([
            'reservation_id' => $reservation->id,
            'amount' => 1500,
            'status' => PaymentStatus::Verified->value,
            'created_at' => now(),
        ]);

        $trend = $this->service->getVerifiedRevenueTrend('day', now()->subDays(6), now());

        $this->assertCount(7, $trend);

        $today = collect($trend)->firstWhere('period', now()->format('Y-m-d'));
        $this->assertNotNull($today);
        $this->assertEquals(1500, $today['value']);
    }
}
