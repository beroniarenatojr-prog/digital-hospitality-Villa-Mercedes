<?php

namespace Tests\Feature;

use App\Enums\FacilityStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Exceptions\Availability\InvalidAvailabilityPeriodException;
use App\Models\Facility;
use App\Models\Reservation;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private AvailabilityService $availabilityService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->availabilityService = new AvailabilityService;
        $this->artisan('db:seed');
    }

    private function getFacilityBySlug(string $name): Facility
    {
        return Facility::query()->where('slug', Str::slug($name))->firstOrFail();
    }

    private function createReservation(
        Facility $facility,
        string $checkIn,
        string $checkOut,
        string $status = ReservationStatus::Approved->value,
        string $source = ReservationSource::Online->value
    ): Reservation {
        return Reservation::factory()->create([
            'facility_id' => $facility->id,
            'check_in' => Carbon::parse($checkIn),
            'check_out' => Carbon::parse($checkOut),
            'status' => $status,
            'reservation_source' => $source,
        ]);
    }

    public function test_facility_with_no_reservations_is_available(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');

        $result = $this->availabilityService->isAvailable(
            $facility,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00')
        );

        $this->assertTrue($result->isAvailable);
    }

    public function test_exact_same_reservation_period_is_unavailable(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');
        $this->createReservation($facility, '2026-06-10 14:00:00', '2026-06-12 12:00:00');

        $result = $this->availabilityService->isAvailable(
            $facility,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00')
        );

        $this->assertFalse($result->isAvailable);
    }

    public function test_requested_period_starts_during_existing_reservation(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');
        $this->createReservation($facility, '2026-06-10 14:00:00', '2026-06-14 12:00:00');

        $result = $this->availabilityService->isAvailable(
            $facility,
            Carbon::parse('2026-06-12 14:00:00'),
            Carbon::parse('2026-06-16 12:00:00')
        );

        $this->assertFalse($result->isAvailable);
    }

    public function test_requested_period_ends_during_existing_reservation(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');
        $this->createReservation($facility, '2026-06-12 14:00:00', '2026-06-16 12:00:00');

        $result = $this->availabilityService->isAvailable(
            $facility,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-14 12:00:00')
        );

        $this->assertFalse($result->isAvailable);
    }

    public function test_requested_period_completely_contains_existing_reservation(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');
        $this->createReservation($facility, '2026-06-12 14:00:00', '2026-06-14 12:00:00');

        $result = $this->availabilityService->isAvailable(
            $facility,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-16 12:00:00')
        );

        $this->assertFalse($result->isAvailable);
    }

    public function test_requested_period_is_completely_inside_existing_reservation(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');
        $this->createReservation($facility, '2026-06-10 14:00:00', '2026-06-16 12:00:00');

        $result = $this->availabilityService->isAvailable(
            $facility,
            Carbon::parse('2026-06-12 14:00:00'),
            Carbon::parse('2026-06-14 12:00:00')
        );

        $this->assertFalse($result->isAvailable);
    }

    public function test_requested_period_ends_exactly_when_existing_reservation_starts(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');
        $this->createReservation($facility, '2026-06-12 14:00:00', '2026-06-14 12:00:00');

        $result = $this->availabilityService->isAvailable(
            $facility,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 14:00:00')
        );

        $this->assertTrue($result->isAvailable);
    }

    public function test_requested_period_starts_exactly_when_existing_reservation_ends(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');
        $this->createReservation($facility, '2026-06-10 14:00:00', '2026-06-12 12:00:00');

        $result = $this->availabilityService->isAvailable(
            $facility,
            Carbon::parse('2026-06-12 12:00:00'),
            Carbon::parse('2026-06-14 12:00:00')
        );

        $this->assertTrue($result->isAvailable);
    }

    public function test_separate_non_overlapping_period_is_available(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');
        $this->createReservation($facility, '2026-06-10 14:00:00', '2026-06-12 12:00:00');

        $result = $this->availabilityService->isAvailable(
            $facility,
            Carbon::parse('2026-06-14 14:00:00'),
            Carbon::parse('2026-06-16 12:00:00')
        );

        $this->assertTrue($result->isAvailable);
    }

    public function test_pending_reservation_blocks_availability(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');
        $this->createReservation($facility, '2026-06-10 14:00:00', '2026-06-12 12:00:00', ReservationStatus::Pending->value);

        $result = $this->availabilityService->isAvailable(
            $facility,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00')
        );

        $this->assertFalse($result->isAvailable);
    }

    public function test_approved_reservation_blocks_availability(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');
        $this->createReservation($facility, '2026-06-10 14:00:00', '2026-06-12 12:00:00', ReservationStatus::Approved->value);

        $result = $this->availabilityService->isAvailable(
            $facility,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00')
        );

        $this->assertFalse($result->isAvailable);
    }

    public function test_cancelled_reservation_does_not_block_availability(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');
        $this->createReservation($facility, '2026-06-10 14:00:00', '2026-06-12 12:00:00', ReservationStatus::Cancelled->value);

        $result = $this->availabilityService->isAvailable(
            $facility,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00')
        );

        $this->assertTrue($result->isAvailable);
    }

    public function test_rejected_reservation_does_not_block_availability(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');
        $this->createReservation($facility, '2026-06-10 14:00:00', '2026-06-12 12:00:00', ReservationStatus::Rejected->value);

        $result = $this->availabilityService->isAvailable(
            $facility,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00')
        );

        $this->assertTrue($result->isAvailable);
    }

    public function test_completed_reservation_does_not_block_availability(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');
        $this->createReservation($facility, '2026-06-10 14:00:00', '2026-06-12 12:00:00', ReservationStatus::Completed->value);

        $result = $this->availabilityService->isAvailable(
            $facility,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00')
        );

        $this->assertTrue($result->isAvailable);
    }

    public function test_walk_in_reservation_blocks_availability(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');
        $this->createReservation($facility, '2026-06-10 14:00:00', '2026-06-12 12:00:00', ReservationStatus::Approved->value, ReservationSource::WalkIn->value);

        $result = $this->availabilityService->isAvailable(
            $facility,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00')
        );

        $this->assertFalse($result->isAvailable);
    }

    public function test_online_reservation_blocks_availability(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');
        $this->createReservation($facility, '2026-06-10 14:00:00', '2026-06-12 12:00:00', ReservationStatus::Approved->value, ReservationSource::Online->value);

        $result = $this->availabilityService->isAvailable(
            $facility,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00')
        );

        $this->assertFalse($result->isAvailable);
    }

    public function test_inactive_facility_is_unavailable(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');
        $facility->is_active = false;
        $facility->save();

        $result = $this->availabilityService->isAvailable(
            $facility,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00')
        );

        $this->assertFalse($result->isAvailable);
        $this->assertEquals('Facility is not active or has been removed.', $result->reason);
    }

    public function test_maintenance_facility_is_unavailable(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');
        $facility->status = FacilityStatus::Maintenance->value;
        $facility->save();

        $result = $this->availabilityService->isAvailable(
            $facility,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00')
        );

        $this->assertFalse($result->isAvailable);
        $this->assertEquals('Facility is under maintenance.', $result->reason);
    }

    public function test_soft_deleted_facility_cannot_be_booked(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');
        $facility->delete(); // Soft delete

        $deletedFacility = Facility::withTrashed()->find($facility->id);

        $result = $this->availabilityService->isAvailable(
            $deletedFacility,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00')
        );

        $this->assertFalse($result->isAvailable);
        $this->assertEquals('Facility is not active or has been removed.', $result->reason);
    }

    public function test_excluded_reservation_does_not_cause_its_own_conflict(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');
        $reservation = $this->createReservation($facility, '2026-06-10 14:00:00', '2026-06-12 12:00:00');

        $result = $this->availabilityService->isAvailable(
            $facility,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00'),
            $reservation->id
        );

        $this->assertTrue($result->isAvailable);
    }

    public function test_excluded_reservation_still_allows_other_conflicting_reservations_to_block(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');
        $reservationToExclude = $this->createReservation($facility, '2026-06-10 14:00:00', '2026-06-12 12:00:00');
        $otherConflict = $this->createReservation($facility, '2026-06-11 14:00:00', '2026-06-13 12:00:00');

        $result = $this->availabilityService->isAvailable(
            $facility,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-13 12:00:00'),
            $reservationToExclude->id
        );

        $this->assertFalse($result->isAvailable);
        $this->assertEquals($otherConflict->id, $result->conflictingReservationId);
    }

    public function test_invalid_check_in_check_out_period_is_rejected_via_exception(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');

        $this->expectException(InvalidAvailabilityPeriodException::class);
        $this->expectExceptionMessage('Invalid reservation period: check-out must be after check-in.');

        $this->availabilityService->isAvailable(
            $facility,
            Carbon::parse('2026-06-12 12:00:00'),
            Carbon::parse('2026-06-10 14:00:00') // End before start
        );
    }

    public function test_different_facility_does_not_cause_a_conflict(): void
    {
        $facility1 = $this->getFacilityBySlug('Deluxe (Room 26)');
        $facility2 = $this->getFacilityBySlug('Standard Family (Room 21)');

        $this->createReservation($facility1, '2026-06-10 14:00:00', '2026-06-12 12:00:00');

        $result = $this->availabilityService->isAvailable(
            $facility2,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00')
        );

        $this->assertTrue($result->isAvailable);
    }

    public function test_function_hall_overlap_is_detected(): void
    {
        $hall = $this->getFacilityBySlug('Diamond Hall');

        $this->createReservation($hall, '2026-06-10 08:00:00', '2026-06-10 17:00:00');

        $result = $this->availabilityService->isAvailable(
            $hall,
            Carbon::parse('2026-06-10 10:00:00'),
            Carbon::parse('2026-06-10 14:00:00')
        );

        $this->assertFalse($result->isAvailable);
    }

    public function test_room_overlap_is_detected(): void
    {
        $room = $this->getFacilityBySlug('Deluxe (Room 26)');

        $this->createReservation($room, '2026-06-10 14:00:00', '2026-06-12 12:00:00');

        $result = $this->availabilityService->isAvailable(
            $room,
            Carbon::parse('2026-06-11 14:00:00'),
            Carbon::parse('2026-06-13 12:00:00')
        );

        $this->assertFalse($result->isAvailable);
    }

    public function test_multiple_reservations_are_handled_correctly(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');

        $this->createReservation($facility, '2026-06-10 14:00:00', '2026-06-12 12:00:00');
        $this->createReservation($facility, '2026-06-14 14:00:00', '2026-06-16 12:00:00');

        // Between the two, should be available
        $result = $this->availabilityService->isAvailable(
            $facility,
            Carbon::parse('2026-06-12 14:00:00'),
            Carbon::parse('2026-06-14 12:00:00')
        );

        $this->assertTrue($result->isAvailable);
    }

    public function test_two_deluxe_rooms_can_be_reserved_on_the_same_nights(): void
    {
        $room26 = $this->getFacilityBySlug('Deluxe (Room 26)');
        $room27 = $this->getFacilityBySlug('Deluxe (Room 27)');

        $this->createReservation($room26, '2026-06-10 14:00:00', '2026-06-12 12:00:00');

        $result = $this->availabilityService->isAvailable(
            $room27,
            Carbon::parse('2026-06-10 14:00:00'),
            Carbon::parse('2026-06-12 12:00:00')
        );

        $this->assertTrue($result->isAvailable);
    }

    public function test_primed_overlap_index_matches_per_facility_lookup(): void
    {
        $facility = $this->getFacilityBySlug('Deluxe (Room 26)');
        $existing = $this->createReservation($facility, '2026-06-10 14:00:00', '2026-06-12 12:00:00');

        $checkIn = Carbon::parse('2026-06-10 14:00:00');
        $checkOut = Carbon::parse('2026-06-12 12:00:00');

        $unprimed = $this->availabilityService->isAvailable($facility, $checkIn, $checkOut);

        $primedService = new AvailabilityService;
        $primedService->primeOverlaps(collect([$facility]), $checkIn, $checkOut);
        $primed = $primedService->isAvailable($facility, $checkIn, $checkOut);

        $this->assertFalse($unprimed->isAvailable);
        $this->assertFalse($primed->isAvailable);
        $this->assertSame($existing->id, $unprimed->conflictingReservationId);
        $this->assertSame($existing->id, $primed->conflictingReservationId);
    }
}
