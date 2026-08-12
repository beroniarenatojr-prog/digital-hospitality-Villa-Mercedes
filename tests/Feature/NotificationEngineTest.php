<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\User;
use App\Notifications\Payment\PaymentStatusChangedNotification;
use App\Notifications\Payment\PaymentSubmittedNotification;
use App\Notifications\Reservation\ReservationCreatedNotification;
use App\Notifications\Reservation\ReservationStatusChangedNotification;
use App\Services\PaymentService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationEngineTest extends TestCase
{
    use RefreshDatabase;

    private ReservationService $reservationService;
    private PaymentService $paymentService;
    private User $customer;
    private User $admin;
    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->artisan('db:seed');

        $this->reservationService = app(ReservationService::class);
        $this->paymentService = app(PaymentService::class);

        $this->customer = User::factory()->create();
        $this->customer->assignRole(Role::Customer->value);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Administrator->value);

        $this->facility = Facility::where('slug', 'deluxe')->firstOrFail();
    }

    public function test_reservation_creation_triggers_notifications(): void
    {
        Notification::fake();

        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            Carbon::parse('2026-10-01 14:00:00'),
            Carbon::parse('2026-10-03 12:00:00'),
            \App\Enums\ReservationSource::Online,
            2
        );

        // Assert customer received it
        Notification::assertSentTo(
            $this->customer,
            ReservationCreatedNotification::class,
            function ($notification) use ($reservation) {
                return $notification->reservation->id === $reservation->id 
                    && $notification->isAdminNotification === false;
            }
        );

        // Assert admin received it
        Notification::assertSentTo(
            $this->admin,
            ReservationCreatedNotification::class,
            function ($notification) use ($reservation) {
                return $notification->reservation->id === $reservation->id 
                    && $notification->isAdminNotification === true;
            }
        );
    }

    public function test_reservation_status_change_triggers_notification(): void
    {
        Notification::fake();

        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            Carbon::parse('2026-11-01 14:00:00'),
            Carbon::parse('2026-11-03 12:00:00'),
            \App\Enums\ReservationSource::Online,
            2
        );

        // Clear fake to ignore the creation notifications
        Notification::fake();

        $this->reservationService->changeStatus($reservation, ReservationStatus::Approved, $this->admin);

        Notification::assertSentTo(
            $this->customer,
            ReservationStatusChangedNotification::class,
            function ($notification) use ($reservation) {
                return $notification->reservation->id === $reservation->id;
            }
        );
    }

    public function test_payment_submission_triggers_admin_notification(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            Carbon::parse('2026-12-01 14:00:00'),
            Carbon::parse('2026-12-03 12:00:00'),
            \App\Enums\ReservationSource::Online,
            2
        );

        Notification::fake();

        $payment = $this->paymentService->createPayment(
            $reservation,
            $this->customer,
            1500,
            PaymentMethod::GCash
        );

        Notification::assertSentTo(
            $this->admin,
            PaymentSubmittedNotification::class,
            function ($notification) use ($payment) {
                return $notification->payment->id === $payment->id;
            }
        );

        // Customers do not get a submitted notification, only admins
        Notification::assertNotSentTo($this->customer, PaymentSubmittedNotification::class);
    }

    public function test_payment_verification_triggers_customer_notification(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            Carbon::parse('2027-01-01 14:00:00'),
            Carbon::parse('2027-01-03 12:00:00'),
            \App\Enums\ReservationSource::Online,
            2
        );

        $payment = $this->paymentService->createPayment(
            $reservation,
            $this->customer,
            1500,
            PaymentMethod::GCash
        );

        Notification::fake();

        $this->paymentService->verifyPayment($payment, $this->admin);

        Notification::assertSentTo(
            $this->customer,
            PaymentStatusChangedNotification::class,
            function ($notification) use ($payment) {
                return $notification->payment->id === $payment->id;
            }
        );
    }
    
    public function test_payment_rejection_triggers_customer_notification_with_reason(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            Carbon::parse('2027-02-01 14:00:00'),
            Carbon::parse('2027-02-03 12:00:00'),
            \App\Enums\ReservationSource::Online,
            2
        );

        $payment = $this->paymentService->createPayment(
            $reservation,
            $this->customer,
            1500,
            PaymentMethod::GCash
        );

        Notification::fake();

        $this->paymentService->rejectPayment($payment, $this->admin, 'Blurry image');

        Notification::assertSentTo(
            $this->customer,
            PaymentStatusChangedNotification::class,
            function ($notification) use ($payment) {
                $arrayData = $notification->toArray($this->customer);
                return $notification->payment->id === $payment->id
                    && $arrayData['reason'] === 'Blurry image';
            }
        );
    }
    
    public function test_notification_payload_contains_no_sensitive_data(): void
    {
        $reservation = $this->reservationService->createReservation(
            $this->facility,
            $this->customer,
            Carbon::parse('2027-03-01 14:00:00'),
            Carbon::parse('2027-03-03 12:00:00'),
            \App\Enums\ReservationSource::Online,
            2
        );

        $notification = new ReservationCreatedNotification($reservation, false);
        $arrayData = $notification->toArray($this->customer);

        $this->assertArrayHasKey('reservation_id', $arrayData);
        $this->assertArrayHasKey('reservation_code', $arrayData);
        
        // Assert no full model serialization or passwords
        $this->assertArrayNotHasKey('password', $arrayData);
        $this->assertArrayNotHasKey('remember_token', $arrayData);
        $this->assertArrayNotHasKey('user_id', $arrayData); // we didn't include it in toArray
    }
}
