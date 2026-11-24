<?php

namespace Tests\Feature;

use App\Enums\FacilityStatus;
use App\Enums\FacilityType;
use App\Enums\PaymentStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Services\Admin\ReportingService;
use App\Support\Admin\ReportFilters;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_report_payment_allocation_uses_all_group_lines_and_only_group_level_payments(): void
    {
        $group = ReservationGroup::factory()->create();
        $first = Reservation::factory()->create([
            'reservation_group_id' => $group->id,
            'total_price' => 1000,
        ]);
        $last = Reservation::factory()->create([
            'reservation_group_id' => $group->id,
            'total_price' => 2000,
        ]);
        Payment::factory()->create([
            'reservation_group_id' => $group->id, 'reservation_id' => null,
            'status' => PaymentStatus::Verified, 'amount' => 1000.01,
        ]);
        Payment::factory()->create([
            'reservation_group_id' => $group->id, 'reservation_id' => null,
            'status' => PaymentStatus::Pending, 'amount' => 300,
        ]);
        Payment::factory()->create([
            'reservation_group_id' => $group->id, 'reservation_id' => $last->id,
            'status' => PaymentStatus::Verified, 'amount' => 100,
        ]);

        $firstForReport = Reservation::query()->withPaymentAllocation()->findOrFail($first->id);
        $lastForReport = Reservation::query()->withPaymentAllocation()->findOrFail($last->id);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $this->assertSame(333.33, $firstForReport->getVerifiedTotalPaid());
            $this->assertSame(766.68, $lastForReport->getVerifiedTotalPaid());
            $this->assertSame(200.0, $lastForReport->getPendingVerificationTotal());
            $this->assertCount(0, DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_chart_date_bases_zero_fill_and_manila_boundary(): void
    {
        $room = Facility::firstOrFail();
        $reservation = Reservation::factory()->create([
            'facility_id' => $room->id, 'status' => 'completed', 'total_price' => 3000,
            'created_at' => '2026-08-31 16:30:00', 'check_in' => '2026-10-01 14:00:00',
            'check_out' => '2026-10-02 12:00:00', 'checked_out_at' => '2026-10-02 04:00:00',
        ]);
        Payment::factory()->create(['reservation_id' => $reservation->id, 'status' => 'verified', 'amount' => 1200, 'payment_date' => '2026-09-10', 'created_at' => '2026-10-20 09:00:00']);
        $filters = new ReportFilters(Carbon::parse('2026-09-01', 'Asia/Manila'), Carbon::parse('2026-11-30', 'Asia/Manila'));
        $chart = $this->service->getCharts($filters)['financial'];
        $this->assertCount(3, $chart['rows']);
        $this->assertEquals(3000, $chart['rows'][0]['booking_value']);
        $this->assertEquals(1200, $chart['rows'][0]['cash_collected']);
        $this->assertEquals(0, $chart['rows'][0]['earned_revenue']);
        $this->assertEquals(3000, $chart['rows'][1]['earned_revenue']);
        $this->assertEquals(0, $chart['rows'][2]['cash_collected']);
        $this->assertEquals(3000, $this->service->getRevenueSummary($filters->startDate, $filters->endDate)['booking_value']);
    }

    public function test_filtered_charts_push_financial_date_ranges_into_sql(): void
    {
        DB::enableQueryLog();

        $this->service->getCharts(new ReportFilters(
            Carbon::parse('2026-09-01', 'Asia/Manila'),
            Carbon::parse('2026-09-30', 'Asia/Manila'),
        ));

        $queries = collect(DB::getQueryLog())->pluck('query')->map(
            fn (string $sql): string => strtolower(str_replace(['`', '"'], '', $sql))
        );

        $this->assertTrue($queries->contains(
            fn (string $sql): bool => str_contains($sql, 'from reservations')
                && str_contains($sql, 'reservations.created_at')
                && str_contains($sql, 'reservations.check_out')
        ), 'The chart reservation query did not apply its date range in SQL.');
        $this->assertTrue($queries->contains(
            fn (string $sql): bool => str_contains($sql, 'from payments')
                && str_contains($sql, 'payments.payment_date')
        ), 'The chart payment query did not apply payment_date in SQL.');
    }

    public function test_operations_use_today_and_full_counts_and_physical_state(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-16 08:00:00', 'Asia/Manila'));
        try {
            $room = Facility::firstOrFail();
            $room->update(['status' => 'maintenance', 'is_active' => true]);
            Reservation::factory()->count(10)->create(['facility_id' => $room->id, 'status' => 'approved', 'check_in' => '2026-09-16 14:00:00', 'check_out' => '2026-09-17 12:00:00', 'checked_in_at' => null]);
            Reservation::factory()->create(['facility_id' => $room->id, 'status' => 'pending', 'check_in' => '2026-09-16 14:00:00', 'check_out' => '2026-09-17 12:00:00']);
            Reservation::factory()->create(['facility_id' => $room->id, 'status' => 'approved', 'check_in' => '2026-09-15 14:00:00', 'check_out' => '2026-09-16 12:00:00', 'checked_in_at' => '2026-09-15 06:00:00', 'checked_out_at' => null]);
            $filters = new ReportFilters(Carbon::parse('2020-01-01'), Carbon::parse('2020-01-31'));
            $this->assertCount(10, $this->service->getOperationsSummary($filters)['arrivals']);
            $charts = $this->service->getCharts($filters);
            $this->assertCount(14, $charts['movement']['rows']);
            $this->assertEquals(10, $charts['movement']['rows'][0]['arrivals']);
            $this->assertSame('Occupied', collect($charts['facility_grid'])->firstWhere('label', $room->name)['state']);
        } finally {
            Carbon::setTestNow();
        }
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
        $this->assertEquals(5000, $revenue['booking_value']);

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
            'payment_date' => now()->toDateString(),
        ]);

        $trend = $this->service->getVerifiedRevenueTrend('day', now()->subDays(6), now());

        $this->assertCount(7, $trend);

        $today = collect($trend)->firstWhere('period', now()->format('Y-m-d'));
        $this->assertNotNull($today);
        $this->assertEquals(1500, $today['value']);
    }

    public function test_cash_reports_use_payment_date_instead_of_record_creation_date(): void
    {
        $room = Facility::whereHas('facilityType', fn ($q) => $q->where('slug', FacilityType::Room->value))->firstOrFail();
        $reservation = Reservation::factory()->create(['facility_id' => $room->id]);

        Payment::factory()->create([
            'reservation_id' => $reservation->id,
            'amount' => 1800,
            'status' => PaymentStatus::Verified->value,
            'payment_date' => '2026-09-10',
            'created_at' => '2026-09-15 09:00:00',
        ]);

        $inside = $this->service->getRevenueSummary(Carbon::parse('2026-09-10'), Carbon::parse('2026-09-10'));
        $outside = $this->service->getRevenueSummary(Carbon::parse('2026-09-15'), Carbon::parse('2026-09-15'));

        $this->assertSame(1800.0, $inside['verified_payment_revenue']);
        $this->assertSame(0.0, $outside['verified_payment_revenue']);
    }

    public function test_room_performance_separates_actual_and_on_books_room_nights(): void
    {
        Facility::whereHas('facilityType', fn ($q) => $q->where('slug', FacilityType::Room->value))
            ->update(['status' => FacilityStatus::Unavailable->value]);
        $room = Facility::whereHas('facilityType', fn ($q) => $q->where('slug', FacilityType::Room->value))->firstOrFail();
        $room->update(['status' => FacilityStatus::Available->value, 'is_active' => true]);

        Reservation::factory()->create([
            'facility_id' => $room->id,
            'status' => ReservationStatus::Completed->value,
            'check_in' => '2026-09-10 14:00:00',
            'check_out' => '2026-09-11 12:00:00',
            'checked_in_at' => '2026-09-10 14:00:00',
            'checked_out_at' => '2026-09-11 12:00:00',
            'base_price' => 3000,
            'total_price' => 3000,
        ]);
        Reservation::factory()->create([
            'facility_id' => $room->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => '2026-09-10 14:00:00',
            'check_out' => '2026-09-11 12:00:00',
            'checked_in_at' => null,
        ]);

        Carbon::setTestNow('2026-09-01 08:00:00');
        $metrics = $this->service->getRoomPerformance(new ReportFilters(
            Carbon::parse('2026-09-10'),
            Carbon::parse('2026-09-10')
        ));
        Carbon::setTestNow();

        $this->assertSame(1, $metrics['available_room_nights']);
        $this->assertSame(1, $metrics['occupied_room_nights']);
        $this->assertSame(1, $metrics['on_books_room_nights']);
        $this->assertSame(100.0, $metrics['occupancy']);
        $this->assertSame(3000.0, $metrics['adr']);
        $this->assertSame(3000.0, $metrics['revpar']);
    }
}
