<?php

namespace Tests\Feature;

use App\Enums\FacilityType;
use App\Enums\PaymentStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
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
        $this->seed();
        $this->service = app(ReportingService::class);
    }

    public function test_booking_summary()
    {
        $room = Facility::whereHas('facilityType', fn($q) => $q->where('slug', FacilityType::Room->value))->first();
        $hall = Facility::whereHas('facilityType', fn($q) => $q->where('slug', FacilityType::FunctionHall->value))->first();

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
        $room = Facility::whereHas('facilityType', fn($q) => $q->where('slug', FacilityType::Room->value))->first();

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

        $trends = $this->service->getBookingTrends('month');

        $this->assertEquals(1, $trends['2026-01']);
        $this->assertEquals(2, $trends['2026-02']);
    }

    public function test_revenue_and_payment_summary()
    {
        $room = Facility::whereHas('facilityType', fn($q) => $q->where('slug', FacilityType::Room->value))->first();

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
}
