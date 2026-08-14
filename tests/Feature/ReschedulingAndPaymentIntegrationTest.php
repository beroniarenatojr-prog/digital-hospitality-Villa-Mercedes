<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Exceptions\Reservation\InvalidReservationTransitionException;
use App\Exceptions\Reservation\ReservationConflictException;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Customer\CustomerReservationService;
use App\Services\PaymentService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ReschedulingAndPaymentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private ReservationService $reservationService;
    private CustomerReservationService $customerReservationService;
    private PaymentService $paymentService;
    private User $customer;
    private User $admin;
    private Facility $room1;
    private Facility $room2;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->artisan('db:seed');

        $this->reservationService = app(ReservationService::class);
        $this->customerReservationService = app(CustomerReservationService::class);
        $this->paymentService = app(PaymentService::class);
        
        $this->customer = User::factory()->create();
        $this->customer->assignRole(Role::Customer->value);
        
        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Administrator->value);

        $this->room1 = Facility::where('slug', 'deluxe')->firstOrFail();
        $this->room2 = Facility::where('slug', 'superior-twin')->firstOrFail();
    }

    private function createTestReservation(Carbon $checkIn, Carbon $checkOut, Facility $facility = null): Reservation
    {
        return $this->reservationService->createReservation(
            $facility ?? $this->room1,
            $this->customer,
            $checkIn,
            $checkOut,
            ReservationSource::Online,
            2
        );
    }

    // --- PAYMENT BALANCE TESTS ---

    public function test_payment_balance_helpers(): void
    {
        $reservation = $this->createTestReservation(
            Carbon::parse('2026-09-01 14:00:00'),
            Carbon::parse('2026-09-03 12:00:00')
        );

        $totalPrice = (float) $reservation->total_price;
        $this->assertEquals(0, $reservation->getVerifiedTotalPaid());
        $this->assertEquals($totalPrice, $reservation->getRemainingBalance());
        $this->assertFalse($reservation->isFullyPaid());

        // Create pending payment
        $payment1 = $this->paymentService->createPayment(
            $reservation,
            $this->customer,
            500,
            PaymentMethod::GCash
        );

        // Still 0 verified
        $this->assertEquals(0, $reservation->getVerifiedTotalPaid());

        // Verify payment
        $this->paymentService->verifyPayment($payment1, $this->admin);

        $this->assertEquals(500, $reservation->getVerifiedTotalPaid());
        $this->assertEquals($totalPrice - 500, $reservation->getRemainingBalance());

        // Create full payment
        $payment2 = $this->paymentService->createPayment(
            $reservation,
            $this->customer,
            $totalPrice - 500,
            PaymentMethod::BankTransfer
        );
        $this->paymentService->verifyPayment($payment2, $this->admin);

        $this->assertEquals($totalPrice, $reservation->getVerifiedTotalPaid());
        $this->assertEquals(0, $reservation->getRemainingBalance());
        $this->assertTrue($reservation->isFullyPaid());
    }

    // --- RESCHEDULING TESTS ---

    public function test_reschedule_to_available_period_succeeds_and_updates_prices(): void
    {
        $reservation = $this->createTestReservation(
            Carbon::parse('2026-09-01 14:00:00'),
            Carbon::parse('2026-09-03 12:00:00')
        );

        $oldPrice = $reservation->total_price;

        $newCheckIn = Carbon::parse('2026-09-10 14:00:00');
        $newCheckOut = Carbon::parse('2026-09-12 12:00:00');

        $rescheduled = $this->reservationService->reschedule(
            $reservation,
            $this->room2,
            $newCheckIn,
            $newCheckOut,
            $this->admin,
            2
        );

        $this->assertEquals($this->room2->id, $rescheduled->facility_id);
        $this->assertEquals($newCheckIn->toDateTimeString(), $rescheduled->check_in->toDateTimeString());
        $this->assertNotEquals($oldPrice, $rescheduled->total_price); // assuming room2 has different price, or at least it recalculated

        // Verify activity log
        $activity = Activity::where('subject_type', Reservation::class)
            ->where('subject_id', $reservation->id)
            ->where('event', 'rescheduled')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->room1->id, $activity->properties['old']['facility_id']);
        $this->assertEquals($this->room2->id, $activity->properties['new']['facility_id']);
    }

    public function test_reschedule_allows_overlapping_with_its_own_previous_slot(): void
    {
        $reservation = $this->createTestReservation(
            Carbon::parse('2026-09-01 14:00:00'),
            Carbon::parse('2026-09-05 12:00:00')
        );

        $newCheckIn = Carbon::parse('2026-09-02 14:00:00');
        $newCheckOut = Carbon::parse('2026-09-06 12:00:00');

        // Should succeed despite overlapping old slot
        $rescheduled = $this->reservationService->reschedule(
            $reservation,
            $this->room1,
            $newCheckIn,
            $newCheckOut,
            $this->customer,
            2
        );

        $this->assertEquals($newCheckIn->toDateTimeString(), $rescheduled->check_in->toDateTimeString());
    }

    public function test_reschedule_fails_if_conflicting_with_other_reservation(): void
    {
        $reservation1 = $this->createTestReservation(
            Carbon::parse('2026-09-01 14:00:00'),
            Carbon::parse('2026-09-03 12:00:00')
        );

        $reservation2 = $this->createTestReservation(
            Carbon::parse('2026-09-05 14:00:00'),
            Carbon::parse('2026-09-07 12:00:00'),
            $this->room1
        );

        $this->expectException(ReservationConflictException::class);

        // Try to reschedule res1 into res2's slot
        $this->reservationService->reschedule(
            $reservation1,
            $this->room1,
            Carbon::parse('2026-09-06 14:00:00'),
            Carbon::parse('2026-09-08 12:00:00'),
            $this->customer,
            2
        );
    }

    public function test_reschedule_fails_for_invalid_status(): void
    {
        $reservation = $this->createTestReservation(
            Carbon::parse('2026-09-01 14:00:00'),
            Carbon::parse('2026-09-03 12:00:00')
        );

        $this->reservationService->cancel($reservation, $this->customer);

        $this->expectException(InvalidReservationTransitionException::class);

        $this->reservationService->reschedule(
            $reservation,
            $this->room1,
            Carbon::parse('2026-09-10 14:00:00'),
            Carbon::parse('2026-09-12 12:00:00'),
            $this->customer,
            2
        );
    }

    public function test_customer_can_reschedule_via_customer_service(): void
    {
        $reservation = $this->createTestReservation(
            Carbon::parse('2026-09-01 14:00:00'),
            Carbon::parse('2026-09-03 12:00:00')
        );

        $newCheckIn = Carbon::parse('2026-10-01 14:00:00');
        $newCheckOut = Carbon::parse('2026-10-03 12:00:00');

        $rescheduled = $this->customerReservationService->rescheduleReservation(
            $this->customer,
            $reservation,
            $this->room1,
            $newCheckIn,
            $newCheckOut,
            2
        );

        $this->assertEquals($newCheckIn->toDateTimeString(), $rescheduled->check_in->toDateTimeString());
    }
}

