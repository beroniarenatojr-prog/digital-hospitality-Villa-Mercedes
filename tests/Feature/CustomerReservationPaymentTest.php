<?php

namespace Tests\Feature;

use App\DTOs\AvailabilityResult;
use App\DTOs\PriceBreakdown;
use App\Enums\FacilityStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RateType;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Exceptions\Customer\ReservationOwnershipException;
use App\Exceptions\Payment\InvalidPaymentAmountException;
use App\Exceptions\Reservation\ReservationCancellationException;
use App\Exceptions\Reservation\ReservationConflictException;
use App\Models\Facility;
use App\Models\Rate;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Customer\CustomerPaymentService;
use App\Services\Customer\CustomerReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerReservationPaymentTest extends TestCase
{
    use RefreshDatabase;

    private CustomerReservationService $reservationService;
    private CustomerPaymentService $paymentService;
    private User $customer;
    private User $otherCustomer;
    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        $this->reservationService = app(CustomerReservationService::class);
        $this->paymentService = app(CustomerPaymentService::class);

        $this->customer = User::factory()->create();
        $this->customer->assignRole(Role::Customer->value);

        $this->otherCustomer = User::factory()->create();
        $this->otherCustomer->assignRole(Role::Customer->value);

        $this->facility = Facility::where('slug', 'deluxe')->firstOrFail();
    }

    // =========================================================================
    // AUTHORIZATION & OWNERSHIP
    // =========================================================================

    public function test_customer_can_retrieve_own_reservations(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->customer,
            $this->facility,
            Carbon::parse('2026-09-01 14:00:00'),
            Carbon::parse('2026-09-03 12:00:00'),
            2
        );

        $reservations = $this->reservationService->getMyReservations($this->customer);

        $this->assertCount(1, $reservations);
        $this->assertEquals($reservation->id, $reservations->first()->id);
        $this->assertTrue($reservations->first()->relationLoaded('facility'));
        $this->assertTrue($reservations->first()->relationLoaded('payments'));
    }

    public function test_customer_can_retrieve_specific_own_reservation(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->customer,
            $this->facility,
            Carbon::parse('2026-09-01 14:00:00'),
            Carbon::parse('2026-09-03 12:00:00'),
            2
        );

        $retrieved = $this->reservationService->getMyReservation($this->customer, $reservation->id);

        $this->assertEquals($reservation->id, $retrieved->id);
        $this->assertTrue($retrieved->relationLoaded('facility'));
        $this->assertTrue($retrieved->relationLoaded('payments'));
    }

    public function test_customer_cannot_retrieve_another_customers_reservation(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->customer,
            $this->facility,
            Carbon::parse('2026-09-01 14:00:00'),
            Carbon::parse('2026-09-03 12:00:00'),
            2
        );

        $this->expectException(ReservationOwnershipException::class);

        $this->reservationService->getMyReservation($this->otherCustomer, $reservation->id);
    }

    public function test_customer_cannot_cancel_another_customers_reservation(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->customer,
            $this->facility,
            Carbon::parse('2026-09-01 14:00:00'),
            Carbon::parse('2026-09-03 12:00:00'),
            2
        );

        $this->expectException(ReservationOwnershipException::class);

        $this->reservationService->cancelReservation($this->otherCustomer, $reservation);
    }

    public function test_customer_cannot_view_payments_on_another_customers_reservation(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->customer,
            $this->facility,
            Carbon::parse('2026-09-01 14:00:00'),
            Carbon::parse('2026-09-03 12:00:00'),
            2
        );

        $this->expectException(ReservationOwnershipException::class);

        $this->paymentService->getPaymentsForReservation($this->otherCustomer, $reservation);
    }

    // =========================================================================
    // RESERVATION CREATION
    // =========================================================================

    public function test_customer_can_create_reservation_through_customer_layer(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->customer,
            $this->facility,
            Carbon::parse('2026-09-05 14:00:00'),
            Carbon::parse('2026-09-07 12:00:00'),
            2
        );

        $this->assertNotNull($reservation->id);
        $this->assertEquals(ReservationSource::Online, $reservation->reservation_source);
        $this->assertEquals(ReservationStatus::Pending, $reservation->status);
        $this->assertEquals($this->customer->id, $reservation->user_id);
        $this->assertEquals($this->facility->id, $reservation->facility_id);
    }

    public function test_customer_reservation_always_uses_online_source(): void
    {
        // Even though the customer layer does not accept source as a parameter,
        // verify the created reservation is always Online.
        $reservation = $this->reservationService->createReservation(
            $this->customer,
            $this->facility,
            Carbon::parse('2026-09-10 14:00:00'),
            Carbon::parse('2026-09-12 12:00:00'),
            2
        );

        $this->assertEquals(ReservationSource::Online, $reservation->reservation_source);
        $this->assertEquals(ReservationStatus::Pending, $reservation->status);
    }

    public function test_conflicting_reservation_is_rejected_by_underlying_service(): void
    {
        $this->reservationService->createReservation(
            $this->customer,
            $this->facility,
            Carbon::parse('2026-09-15 14:00:00'),
            Carbon::parse('2026-09-17 12:00:00'),
            2
        );

        $this->expectException(ReservationConflictException::class);

        $this->reservationService->createReservation(
            $this->otherCustomer,
            $this->facility,
            Carbon::parse('2026-09-16 14:00:00'),
            Carbon::parse('2026-09-18 12:00:00'),
            2
        );
    }

    public function test_customer_reservation_pricing_is_delegated_to_pricing_service(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->customer,
            $this->facility,
            Carbon::parse('2026-09-20 14:00:00'),
            Carbon::parse('2026-09-22 12:00:00'),
            2
        );

        // Price was calculated by PricingService and persisted
        $this->assertGreaterThan(0, (float) $reservation->total_price);
        $this->assertGreaterThan(0, (float) $reservation->base_price);
    }

    // =========================================================================
    // CANCELLATION
    // =========================================================================

    public function test_customer_can_cancel_own_eligible_reservation(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->customer,
            $this->facility,
            Carbon::parse('2026-10-01 14:00:00'),
            Carbon::parse('2026-10-03 12:00:00'),
            2
        );

        // Pending cannot be cancelled directly per Phase 3C lifecycle.
        // Must first be Approved, then Cancelled.
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);
        app(\App\Services\ReservationService::class)->changeStatus($reservation, ReservationStatus::Approved, $admin);
        $this->assertEquals(ReservationStatus::Approved, $reservation->refresh()->status);

        $this->reservationService->cancelReservation($this->customer, $reservation, 'Changed plans');

        $this->assertEquals(ReservationStatus::Cancelled, $reservation->refresh()->status);
    }

    public function test_customer_cannot_cancel_non_cancellable_reservation(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->customer,
            $this->facility,
            Carbon::parse('2026-10-05 14:00:00'),
            Carbon::parse('2026-10-07 12:00:00'),
            2
        );

        // Force to Rejected status (non-cancellable)
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);
        app(\App\Services\ReservationService::class)->changeStatus($reservation, ReservationStatus::Rejected, $admin, 'No availability');

        $this->expectException(ReservationCancellationException::class);

        $this->reservationService->cancelReservation($this->customer, $reservation);
    }

    public function test_cancellation_reason_is_persisted(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->customer,
            $this->facility,
            Carbon::parse('2026-10-10 14:00:00'),
            Carbon::parse('2026-10-12 12:00:00'),
            2
        );

        // Approve first so it becomes cancellable per Phase 3C lifecycle
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);
        app(\App\Services\ReservationService::class)->changeStatus($reservation, ReservationStatus::Approved, $admin);

        $this->reservationService->cancelReservation($this->customer, $reservation, 'Family emergency');

        $reservation->refresh();
        $this->assertEquals('Family emergency', $reservation->cancellation_reason);
        $this->assertNotNull($reservation->cancelled_at);
    }

    // =========================================================================
    // PAYMENT SUBMISSION
    // =========================================================================

    public function test_customer_can_submit_payment_for_own_reservation(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->customer,
            $this->facility,
            Carbon::parse('2026-11-01 14:00:00'),
            Carbon::parse('2026-11-03 12:00:00'),
            2
        );

        $payment = $this->paymentService->submitPayment(
            $this->customer,
            $reservation,
            1500,
            PaymentMethod::GCash,
            'proof/receipt.jpg'
        );

        $this->assertNotNull($payment->id);
        $this->assertEquals(PaymentStatus::Pending, $payment->status);
        $this->assertEquals($reservation->id, $payment->reservation_id);
        $this->assertNull($payment->verified_by);
        $this->assertNull($payment->verified_at);
    }

    public function test_customer_cannot_submit_payment_for_another_customers_reservation(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->customer,
            $this->facility,
            Carbon::parse('2026-11-05 14:00:00'),
            Carbon::parse('2026-11-07 12:00:00'),
            2
        );

        $this->expectException(ReservationOwnershipException::class);

        $this->paymentService->submitPayment(
            $this->otherCustomer,
            $reservation,
            1500,
            PaymentMethod::GCash
        );
    }

    public function test_payment_amount_validation_is_delegated_to_payment_service(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->customer,
            $this->facility,
            Carbon::parse('2026-11-10 14:00:00'),
            Carbon::parse('2026-11-12 12:00:00'),
            2
        );

        $this->expectException(InvalidPaymentAmountException::class);

        $this->paymentService->submitPayment(
            $this->customer,
            $reservation,
            -100,
            PaymentMethod::Cash
        );
    }

    public function test_payment_method_and_proof_are_correctly_persisted(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->customer,
            $this->facility,
            Carbon::parse('2026-11-15 14:00:00'),
            Carbon::parse('2026-11-17 12:00:00'),
            2
        );

        $payment = $this->paymentService->submitPayment(
            $this->customer,
            $reservation,
            2500.50,
            PaymentMethod::BankTransfer,
            'proof/bank_slip.jpg'
        );

        $this->assertEquals(PaymentMethod::BankTransfer, $payment->method);
        $this->assertEquals('proof/bank_slip.jpg', $payment->proof_path);
        $this->assertEquals('2500.50', $payment->amount);
    }

    // =========================================================================
    // PAYMENT ACCESS
    // =========================================================================

    public function test_customer_can_view_payments_for_own_reservation(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->customer,
            $this->facility,
            Carbon::parse('2026-12-01 14:00:00'),
            Carbon::parse('2026-12-03 12:00:00'),
            2
        );

        $this->paymentService->submitPayment($this->customer, $reservation, 500, PaymentMethod::GCash);
        $this->paymentService->submitPayment($this->customer, $reservation, 1000, PaymentMethod::Cash);

        $payments = $this->paymentService->getPaymentsForReservation($this->customer, $reservation);

        $this->assertCount(2, $payments);
    }

    public function test_customer_cannot_view_payments_for_another_customers_reservation(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->customer,
            $this->facility,
            Carbon::parse('2026-12-05 14:00:00'),
            Carbon::parse('2026-12-07 12:00:00'),
            2
        );

        $this->paymentService->submitPayment($this->customer, $reservation, 500, PaymentMethod::GCash);

        $this->expectException(ReservationOwnershipException::class);

        $this->paymentService->getPaymentsForReservation($this->otherCustomer, $reservation);
    }

    public function test_payment_verification_metadata_is_visible_but_not_modifiable(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->customer,
            $this->facility,
            Carbon::parse('2026-12-10 14:00:00'),
            Carbon::parse('2026-12-12 12:00:00'),
            2
        );

        $payment = $this->paymentService->submitPayment($this->customer, $reservation, 1000, PaymentMethod::GCash);

        // Verify that verification fields are null for a new payment (not modifiable by customer)
        $this->assertNull($payment->verified_by);
        $this->assertNull($payment->verified_at);
        $this->assertNull($payment->rejection_reason);

        // Confirm CustomerPaymentService has no verify/reject methods
        $this->assertFalse(method_exists($this->paymentService, 'verifyPayment'));
        $this->assertFalse(method_exists($this->paymentService, 'rejectPayment'));
    }

    // =========================================================================
    // DELEGATION VERIFICATION
    // =========================================================================

    public function test_availability_check_delegates_to_availability_service(): void
    {
        $result = $this->reservationService->checkAvailability(
            $this->facility,
            Carbon::parse('2027-01-01 14:00:00'),
            Carbon::parse('2027-01-03 12:00:00')
        );

        $this->assertInstanceOf(AvailabilityResult::class, $result);
        $this->assertTrue($result->isAvailable);
    }

    public function test_price_estimate_delegates_to_pricing_service(): void
    {
        $breakdown = $this->reservationService->getEstimatedPrice(
            $this->facility,
            Carbon::parse('2027-02-01 14:00:00'),
            Carbon::parse('2027-02-03 12:00:00')
        );

        $this->assertInstanceOf(PriceBreakdown::class, $breakdown);
        $this->assertGreaterThan(0, $breakdown->totalAmount);
        $this->assertNotEmpty($breakdown->appliedRateName);
    }

    public function test_no_duplicate_pricing_or_availability_logic_exists(): void
    {
        // Structural assertion: CustomerReservationService does not query Rate or Reservation
        // overlap logic directly. It only delegates to existing services.
        $reflection = new \ReflectionClass(CustomerReservationService::class);
        $source = file_get_contents($reflection->getFileName());

        // Must NOT contain direct Rate queries
        $this->assertStringNotContainsString('Rate::query', $source);
        $this->assertStringNotContainsString("->rates()", $source);

        // Must NOT contain direct overlap detection
        $this->assertStringNotContainsString('check_in', $source);
        $this->assertStringNotContainsString('check_out', $source);

        // Must NOT contain lockForUpdate (that belongs to ReservationService)
        $this->assertStringNotContainsString('lockForUpdate', $source);
    }

    // =========================================================================
    // SECURITY
    // =========================================================================

    public function test_sensitive_user_fields_are_never_exposed(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->customer,
            $this->facility,
            Carbon::parse('2027-03-01 14:00:00'),
            Carbon::parse('2027-03-03 12:00:00'),
            2
        );

        $retrieved = $this->reservationService->getMyReservation($this->customer, $reservation->id);

        // User model's $hidden should prevent password/token exposure
        $userArray = $retrieved->user->toArray();
        $this->assertArrayNotHasKey('password', $userArray);
        $this->assertArrayNotHasKey('remember_token', $userArray);
    }

    public function test_customer_cannot_modify_reservation_status_directly(): void
    {
        // CustomerReservationService has no changeStatus() method
        $this->assertFalse(method_exists($this->reservationService, 'changeStatus'));
    }

    public function test_customer_cannot_verify_or_reject_payments(): void
    {
        // CustomerPaymentService has no verify/reject methods
        $this->assertFalse(method_exists($this->paymentService, 'verifyPayment'));
        $this->assertFalse(method_exists($this->paymentService, 'rejectPayment'));
    }
}
