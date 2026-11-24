<?php

namespace Tests\Feature;

use App\Enums\FacilityStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Exceptions\Availability\InvalidAvailabilityPeriodException;
use App\Exceptions\Reservation\InvalidReservationTransitionException;
use App\Exceptions\Reservation\ReservationConflictException;
use App\Exceptions\Reservation\UnauthorizedReservationSourceException;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/** Legacy service contracts remain supported for bookings accepted before the confirmed policy. */
class ReservationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReservationService $reservationService;

    private Facility $facility;

    private User $customer;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // These fixtures exercise pre-existing accepted terms. Current policy flows
        // have separate quote/acceptance/refund coverage in ConfirmedBookingPracticesTest.
        $this->travelTo(Carbon::parse('2026-05-01 00:00:00'));
        config(['resort.policy.version' => '2026-09-v2']);
        $this->seedReservationCatalog();

        $this->reservationService = app(ReservationService::class);
        $this->facility = Facility::where('slug', 'deluxe-room-26')->firstOrFail();

        $this->customer = User::factory()->create();
        $this->customer->assignRole(Role::Customer->value);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Administrator->value);
    }

    public function test_online_reservation_starts_as_pending(): void
    {
        $checkIn = Carbon::parse('2026-06-10 14:00:00');
        $checkOut = Carbon::parse('2026-06-12 12:00:00');

        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            $checkIn,
            $checkOut,
            ReservationSource::Online,
            2
        );

        $this->assertEquals(ReservationStatus::Pending, $reservation->status);
        $this->assertNull($reservation->approved_at);
    }

    public function test_ordinary_customer_cannot_create_walk_in_reservation(): void
    {
        $checkIn = Carbon::parse('2026-06-10 14:00:00');
        $checkOut = Carbon::parse('2026-06-12 12:00:00');

        $this->expectException(UnauthorizedReservationSourceException::class);

        $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            $checkIn,
            $checkOut,
            ReservationSource::WalkIn,
            2
        );
    }

    public function test_walk_in_reservation_starts_as_approved(): void
    {
        $checkIn = Carbon::parse('2026-06-10 14:00:00');
        $checkOut = Carbon::parse('2026-06-12 12:00:00');

        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->admin,
            $checkIn,
            $checkOut,
            ReservationSource::WalkIn,
            2
        );

        $this->assertEquals(ReservationStatus::Approved, $reservation->status);
        $this->assertNotNull($reservation->approved_at);
    }

    public function test_authorized_staff_can_create_walk_in_reservation(): void
    {
        $checkIn = Carbon::parse('2026-06-10 14:00:00');
        $checkOut = Carbon::parse('2026-06-12 12:00:00');

        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->admin,
            $checkIn,
            $checkOut,
            ReservationSource::WalkIn,
            2
        );

        $this->assertEquals(ReservationStatus::Approved, $reservation->status);
    }

    public function test_reservation_is_created_with_unique_reservation_code(): void
    {
        $checkIn = Carbon::parse('2026-06-10 14:00:00');
        $checkOut = Carbon::parse('2026-06-12 12:00:00');

        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            $checkIn,
            $checkOut,
            ReservationSource::Online,
            2
        );

        $this->assertNotNull($reservation->reservation_code);
        $this->assertStringStartsWith('VM-'.now()->format('Ymd').'-', $reservation->reservation_code);
    }

    public function test_reservation_belongs_to_correct_user_and_facility(): void
    {
        $checkIn = Carbon::parse('2026-06-10 14:00:00');
        $checkOut = Carbon::parse('2026-06-12 12:00:00');

        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            $checkIn,
            $checkOut,
            ReservationSource::Online,
            2
        );

        $this->assertEquals($this->customer->id, $reservation->user_id);
        $this->assertEquals($this->facility->id, $reservation->facility_id);
    }

    public function test_reservation_stores_requested_check_in_and_check_out(): void
    {
        $checkIn = Carbon::parse('2026-06-10 14:00:00');
        $checkOut = Carbon::parse('2026-06-12 12:00:00');

        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            $checkIn,
            $checkOut,
            ReservationSource::Online,
            2
        );

        $this->assertEquals($checkIn->toDateTimeString(), $reservation->check_in->toDateTimeString());
        $this->assertEquals($checkOut->toDateTimeString(), $reservation->check_out->toDateTimeString());
    }

    public function test_pricing_service_is_used_and_calculated_price_is_persisted(): void
    {
        $checkIn = Carbon::parse('2026-06-10 14:00:00');
        $checkOut = Carbon::parse('2026-06-12 12:00:00'); // 2 nights

        $baseRate = $this->facility->rates()->where('type', 'base')->first();
        $expectedTotal = (float) $baseRate->amount * 2; // e.g. 5000.00 * 2 = 10000.00

        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            $checkIn,
            $checkOut,
            ReservationSource::Online,
            2
        );

        // Fetch directly from DB to verify exact string representation
        $dbReservation = DB::table('reservations')->where('id', $reservation->id)->first();

        $this->assertEquals(number_format($expectedTotal, 2, '.', ''), $dbReservation->total_price);
    }

    public function test_existing_conflicting_reservation_prevents_creation(): void
    {
        $checkIn = Carbon::parse('2026-06-10 14:00:00');
        $checkOut = Carbon::parse('2026-06-12 12:00:00');

        $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            $checkIn,
            $checkOut,
            ReservationSource::Online,
            2
        );

        $this->expectException(ReservationConflictException::class);

        // Try booking overlapping period
        $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            Carbon::parse('2026-06-11 14:00:00'),
            Carbon::parse('2026-06-13 12:00:00'),
            ReservationSource::Online,
            2
        );
    }

    public function test_exact_boundary_touching_reservation_can_be_created(): void
    {
        $checkIn = Carbon::parse('2026-06-10 14:00:00');
        $checkOut = Carbon::parse('2026-06-12 12:00:00');

        $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            $checkIn,
            $checkOut,
            ReservationSource::Online,
            2
        );

        // Starts exactly when previous ends
        $checkIn2 = Carbon::parse('2026-06-12 14:00:00');
        $checkOut2 = Carbon::parse('2026-06-14 12:00:00');

        $reservation2 = $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            $checkIn2,
            $checkOut2,
            ReservationSource::Online,
            2,
            ['early_check_in_hours' => 2]
        );

        $this->assertNotNull($reservation2->id);
    }

    public function test_invalid_date_period_is_rejected(): void
    {
        $checkIn = Carbon::parse('2026-06-12 12:00:00');
        $checkOut = Carbon::parse('2026-06-10 14:00:00'); // Check out before check in

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            $checkIn,
            $checkOut,
            ReservationSource::Online,
            2
        );
    }

    public function test_inactive_facility_cannot_be_reserved(): void
    {
        $this->facility->is_active = false;
        $this->facility->save();

        $checkIn = Carbon::parse('2026-06-10 14:00:00');
        $checkOut = Carbon::parse('2026-06-12 12:00:00');

        $this->expectException(ReservationConflictException::class);
        $this->expectExceptionMessage('Facility is not active or has been removed.');

        $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            $checkIn,
            $checkOut,
            ReservationSource::Online,
            2
        );
    }

    public function test_maintenance_facility_cannot_be_reserved(): void
    {
        $this->facility->status = FacilityStatus::Maintenance->value;
        $this->facility->save();

        $checkIn = Carbon::parse('2026-06-10 14:00:00');
        $checkOut = Carbon::parse('2026-06-12 12:00:00');

        $this->expectException(ReservationConflictException::class);
        $this->expectExceptionMessage('Facility is under maintenance.');

        $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            $checkIn,
            $checkOut,
            ReservationSource::Online,
            2
        );
    }

    public function test_soft_deleted_facility_cannot_be_reserved(): void
    {
        $this->facility->delete();
        $deletedFacility = Facility::withTrashed()->find($this->facility->id);

        $checkIn = Carbon::parse('2026-06-10 14:00:00');
        $checkOut = Carbon::parse('2026-06-12 12:00:00');

        $this->expectException(ReservationConflictException::class);

        $this->reservationService->createReservation(
            $deletedFacility,
            $this->customer,
            $checkIn,
            $checkOut,
            ReservationSource::Online,
            2
        );
    }

    public function test_pending_reservation_blocks_subsequent_booking(): void
    {
        $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00'),
            ReservationSource::Online, // Pending
            2
        );

        $this->expectException(ReservationConflictException::class);

        $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00'),
            ReservationSource::Online,
            2
        );
    }

    public function test_approved_reservation_blocks_subsequent_booking(): void
    {
        $this->reservationService->createReservation(
            $this->facility,
            $this->admin,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00'),
            ReservationSource::WalkIn, // Approved
            2
        );

        $this->expectException(ReservationConflictException::class);

        $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00'),
            ReservationSource::Online,
            2
        );
    }

    public function test_cancelled_reservation_does_not_block_subsequent_booking(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->admin,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00'),
            ReservationSource::WalkIn,
            2
        );

        $this->reservationService->cancel($reservation, $this->admin);

        // Should succeed now
        $newReservation = $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00'),
            ReservationSource::Online,
            2
        );

        $this->assertNotNull($newReservation->id);
    }

    public function test_different_facility_can_be_reserved_for_same_period(): void
    {
        $otherFacility = Facility::where('slug', 'standard-family-room-21')->firstOrFail();

        $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00'),
            ReservationSource::Online,
            2
        );

        $newReservation = $this->reservationService->createReservation(
            $otherFacility,
            $this->customer,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00'),
            ReservationSource::Online,
            2
        );

        $this->assertNotNull($newReservation->id);
    }

    public function test_cancellation_changes_status_to_cancelled_and_preserves_data(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->admin,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00'),
            ReservationSource::WalkIn, // Approved
            2
        );

        $this->reservationService->cancel($reservation, $this->admin, 'Customer request');

        $this->assertEquals(ReservationStatus::Cancelled, $reservation->fresh()->status);
        $this->assertNotNull($reservation->fresh()->cancelled_at);
        $this->assertEquals('Customer request', $reservation->fresh()->cancellation_reason);

        // Data preserved
        $this->assertNotNull($reservation->fresh()->check_in);
        $this->assertNotNull($reservation->fresh()->total_price);
    }

    public function test_invalid_transition_from_rejected_to_approved_fails(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00'),
            ReservationSource::Online, // Pending
            2
        );

        $this->reservationService->changeStatus($reservation, ReservationStatus::Rejected, $this->admin);

        $this->expectException(InvalidReservationTransitionException::class);

        $this->reservationService->changeStatus($reservation, ReservationStatus::Approved, $this->admin);
    }

    public function test_valid_pending_to_approved_transition_works(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00'),
            ReservationSource::Online, // Pending
            2
        );

        $this->reservationService->changeStatus($reservation, ReservationStatus::Approved, $this->admin);

        $this->assertEquals(ReservationStatus::Approved, $reservation->fresh()->status);
        $this->assertNotNull($reservation->fresh()->approved_at);
    }

    public function test_valid_pending_to_rejected_transition_works(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00'),
            ReservationSource::Online, // Pending
            2
        );

        $this->reservationService->changeStatus($reservation, ReservationStatus::Rejected, $this->admin, 'Not enough funds');

        $this->assertEquals(ReservationStatus::Rejected, $reservation->fresh()->status);
        $this->assertNotNull($reservation->fresh()->rejected_at);
        $this->assertEquals('Not enough funds', $reservation->fresh()->rejection_reason);
    }

    public function test_valid_approved_to_cancelled_transition_works(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->admin,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00'),
            ReservationSource::WalkIn, // Approved
            2
        );

        $this->reservationService->changeStatus($reservation, ReservationStatus::Cancelled, $this->admin);

        $this->assertEquals(ReservationStatus::Cancelled, $reservation->fresh()->status);
    }

    public function test_invalid_approved_to_rescheduled_transition_fails(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->admin, // Admin used here to allow walk-in creation easily without failing
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00'),
            ReservationSource::WalkIn, // Approved
            2
        );

        $this->expectException(InvalidReservationTransitionException::class);

        $this->reservationService->changeStatus($reservation, ReservationStatus::Rescheduled, $this->admin);
    }

    public function test_invalid_cancelled_to_approved_transition_fails(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->admin,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00'),
            ReservationSource::WalkIn, // Approved
            2
        );

        $this->reservationService->cancel($reservation, $this->admin);

        $this->expectException(InvalidReservationTransitionException::class);

        $this->reservationService->changeStatus($reservation, ReservationStatus::Approved, $this->admin);
    }

    public function test_invalid_completed_to_pending_transition_fails(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->admin,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00'),
            ReservationSource::WalkIn, // Approved
            2
        );

        $this->reservationService->changeStatus($reservation, ReservationStatus::Completed, $this->admin);

        $this->expectException(InvalidReservationTransitionException::class);

        $this->reservationService->changeStatus($reservation, ReservationStatus::Pending, $this->admin);
    }

    public function test_appropriate_activity_log_is_created_for_reservation_creation(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00'),
            ReservationSource::Online,
            2
        );

        $activity = Activity::where('subject_type', Reservation::class)
            ->where('subject_id', $reservation->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->customer->id, $activity->causer_id);
    }

    public function test_appropriate_activity_log_is_created_for_status_changes(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00'),
            ReservationSource::Online,
            2
        );

        $this->reservationService->changeStatus($reservation, ReservationStatus::Approved, $this->admin);

        $activity = Activity::where('subject_type', Reservation::class)
            ->where('subject_id', $reservation->id)
            ->where('event', 'status_changed')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->admin->id, $activity->causer_id);
        $this->assertStringContainsString('Pending to Confirmed', $activity->description);
    }

    public function test_failed_transaction_does_not_leave_partial_reservation_data(): void
    {
        $initialCount = Reservation::count();

        try {
            $this->reservationService->createReservation(
                $this->facility,
                $this->customer,
                Carbon::parse('2026-06-12 12:00:00'),
                Carbon::parse('2026-06-10 14:00:00'), // Invalid dates, will throw InvalidAvailabilityPeriodException
                ReservationSource::Online,
                2
            );
        } catch (\Exception $e) {
            // Ignored
        }

        $this->assertEquals($initialCount, Reservation::count());
    }

    public function test_payment_records_are_not_incorrectly_created(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00'),
            ReservationSource::Online,
            2
        );

        $this->assertEquals(0, $reservation->payments()->count());
    }

    public function test_concurrency_race_condition_behavior_requests_lock_for_update(): void
    {
        DB::enableQueryLog();

        $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00'),
            ReservationSource::Online,
            2
        );

        $queryLog = DB::getQueryLog();
        $hasForUpdate = false;

        foreach ($queryLog as $log) {
            if (str_contains(strtoupper($log['query']), 'FOR UPDATE') && str_contains(strtoupper($log['query']), 'FACILITIES')) {
                $hasForUpdate = true;
                break;
            }
        }

        $this->assertTrue($hasForUpdate, 'Expected a pessimistic row-level lock (FOR UPDATE) on the facilities table.');
    }
}
