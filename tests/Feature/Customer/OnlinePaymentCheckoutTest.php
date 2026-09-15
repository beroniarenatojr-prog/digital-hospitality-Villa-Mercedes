<?php

namespace Tests\Feature\Customer;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Payments\CheckoutSession;
use App\Services\Payments\ManualPaymentGateway;
use App\Services\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnlinePaymentCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $otherCustomer;

    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();
        // Exercise preserved historical gateway behavior explicitly; new
        // production checkout is disabled and covered by CustomerDepositWorkflowTest.
        config(['payments.allow_gateway_checkout' => true]);

        $this->customer = User::factory()->create();
        $this->otherCustomer = User::factory()->create();

        $facility = Facility::factory()->create(['is_active' => true]);

        $this->reservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $facility->id,
            'status' => ReservationStatus::Approved->value,
            'base_price' => 5000,
            'total_price' => 5000,
        ]);
    }

    private function fakeGateway(bool $paid = true): FakePaymentGateway
    {
        $gateway = new FakePaymentGateway($paid);
        $this->app->instance(PaymentGateway::class, $gateway);

        return $gateway;
    }

    public function test_online_checkout_is_hidden_for_pending_reservations(): void
    {
        $this->fakeGateway();
        $this->reservation->update(['status' => ReservationStatus::Pending->value]);

        $this->actingAs($this->customer)
            ->get(route('customer.payments.index', $this->reservation))
            ->assertOk()
            ->assertSee('Waiting for resort approval')
            ->assertDontSee('Continue to secure checkout');
    }

    public function test_online_checkout_is_refused_for_pending_reservations(): void
    {
        $this->fakeGateway();
        $this->reservation->update(['status' => ReservationStatus::Pending->value]);

        $this->actingAs($this->customer)
            ->post(route('customer.payments.checkout', $this->reservation), [
                'amount' => 2500,
                'payment_method' => PaymentMethod::GCash->value,
            ])
            ->assertRedirect(route('customer.payments.index', $this->reservation))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_online_checkout_is_hidden_when_no_gateway_is_configured(): void
    {
        $this->app->instance(PaymentGateway::class, new ManualPaymentGateway);

        $this->actingAs($this->customer)
            ->get(route('customer.payments.index', $this->reservation))
            ->assertOk()
            ->assertDontSee('Continue to secure checkout');
    }

    public function test_online_checkout_is_offered_when_gateway_is_configured(): void
    {
        $this->fakeGateway();

        $this->actingAs($this->customer)
            ->get(route('customer.payments.index', $this->reservation))
            ->assertOk()
            ->assertSee('Continue to secure checkout');
    }

    public function test_customer_is_redirected_to_the_gateway_and_a_pending_payment_is_recorded(): void
    {
        $this->fakeGateway();

        $this->actingAs($this->customer)
            ->post(route('customer.payments.checkout', $this->reservation), [
                'amount' => 2500,
                'payment_method' => PaymentMethod::GCash->value,
            ])
            ->assertRedirect('https://gateway.test/checkout/fake-session');

        $this->assertDatabaseHas('payments', [
            'reservation_id' => $this->reservation->id,
            'amount' => 2500,
            'method' => PaymentMethod::GCash->value,
            'status' => PaymentStatus::Pending->value,
            'reference_number' => 'fake-session',
        ]);
    }

    public function test_paying_more_than_the_remaining_balance_is_refused(): void
    {
        $this->fakeGateway();

        $this->actingAs($this->customer)
            ->post(route('customer.payments.checkout', $this->reservation), [
                'amount' => 9000,
                'payment_method' => PaymentMethod::Maya->value,
            ])
            ->assertRedirect(route('customer.payments.index', $this->reservation))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cash_is_not_accepted_for_online_checkout(): void
    {
        $this->fakeGateway();

        $this->actingAs($this->customer)
            ->post(route('customer.payments.checkout', $this->reservation), [
                'amount' => 1000,
                'payment_method' => PaymentMethod::Cash->value,
            ])
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_return_from_gateway_verifies_a_paid_session(): void
    {
        $this->fakeGateway(paid: true);

        $payment = Payment::factory()->create([
            'reservation_id' => $this->reservation->id,
            'amount' => 5000,
            'method' => PaymentMethod::GCash->value,
            'status' => PaymentStatus::Pending->value,
            'reference_number' => 'fake-session',
            'proof_path' => null,
        ]);

        $this->actingAs($this->customer)
            ->get(route('customer.payments.return', $payment))
            ->assertRedirect(route('customer.payments.index', $this->reservation))
            ->assertSessionHas('success');

        $payment->refresh();

        $this->assertSame(PaymentStatus::Verified, $payment->status);
        $this->assertNull($payment->verified_by, 'Gateway verification must not credit a staff member.');
        $this->assertNotNull($payment->verified_at);
        $this->assertSame(0.0, $this->reservation->fresh()->getRemainingBalance());
    }

    public function test_return_from_gateway_leaves_an_unpaid_session_pending(): void
    {
        $this->fakeGateway(paid: false);

        $payment = Payment::factory()->create([
            'reservation_id' => $this->reservation->id,
            'amount' => 5000,
            'status' => PaymentStatus::Pending->value,
            'reference_number' => 'fake-session',
        ]);

        $this->actingAs($this->customer)
            ->get(route('customer.payments.return', $payment))
            ->assertRedirect(route('customer.payments.index', $this->reservation))
            ->assertSessionHas('error');

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_another_customer_cannot_confirm_someone_elses_payment(): void
    {
        $this->fakeGateway(paid: true);

        $payment = Payment::factory()->create([
            'reservation_id' => $this->reservation->id,
            'amount' => 5000,
            'status' => PaymentStatus::Pending->value,
            'reference_number' => 'fake-session',
        ]);

        $this->actingAs($this->otherCustomer)
            ->get(route('customer.payments.return', $payment))
            ->assertForbidden();

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_cancelling_at_the_gateway_removes_the_placeholder_payment(): void
    {
        $this->fakeGateway(paid: false);

        $payment = Payment::factory()->create([
            'reservation_id' => $this->reservation->id,
            'amount' => 5000,
            'status' => PaymentStatus::Pending->value,
            'reference_number' => 'fake-session',
        ]);

        $this->actingAs($this->customer)
            ->get(route('customer.payments.cancel', $payment))
            ->assertRedirect(route('customer.payments.index', $this->reservation))
            ->assertSessionHas('error');

        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
    }

    public function test_paid_checkout_is_recorded_when_guest_visits_cancel_url(): void
    {
        $this->fakeGateway(paid: true);
        $payment = Payment::factory()->create([
            'reservation_id' => $this->reservation->id, 'amount' => 5000,
            'status' => PaymentStatus::Pending, 'reference_number' => 'paid-session',
        ]);

        $this->actingAs($this->customer)->get(route('customer.payments.cancel', $payment))->assertSessionHas('success');
        $this->assertFalse($payment->fresh()->trashed());
        $this->assertSame(PaymentStatus::Verified, $payment->fresh()->status);
    }

    public function test_late_provider_confirmation_restores_cancelled_checkout_and_displays_excess(): void
    {
        $this->fakeGateway(paid: false);
        $payment = Payment::factory()->create([
            'reservation_id' => $this->reservation->id, 'amount' => 5000,
            'status' => PaymentStatus::Pending, 'reference_number' => 'late-session',
        ]);
        $this->actingAs($this->customer)->get(route('customer.payments.cancel', $payment))->assertRedirect();
        $this->assertSoftDeleted($payment);
        Payment::factory()->create([
            'reservation_id' => $this->reservation->id, 'amount' => 5000, 'status' => PaymentStatus::Verified,
        ]);
        $this->fakeGateway(paid: true);

        $this->get(route('customer.payments.return', $payment))->assertSessionHas('success');
        $this->assertFalse(Payment::withTrashed()->findOrFail($payment->id)->trashed());
        $this->assertSame(5000.0, $this->reservation->fresh()->getOverpaymentAmount());
        $this->get(route('customer.payments.index', $this->reservation))->assertOk()->assertSee('Overpayment requires reconciliation');
    }

    public function test_gateway_cancel_cannot_delete_a_manual_receipt(): void
    {
        $payment = Payment::factory()->create([
            'reservation_id' => $this->reservation->id, 'amount' => 5000,
            'status' => PaymentStatus::Pending, 'reference_number' => null, 'proof_path' => 'receipts/manual.jpg',
        ]);
        $this->actingAs($this->customer)->get(route('customer.payments.cancel', $payment))->assertSessionHas('error');
        $this->assertFalse($payment->fresh()->trashed());
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_online_checkout_counts_pending_manual_payments_toward_submission_limit(): void
    {
        $this->fakeGateway();
        Payment::factory()->create([
            'reservation_id' => $this->reservation->id, 'amount' => 4000, 'status' => PaymentStatus::Pending,
        ]);
        $this->actingAs($this->customer)->post(route('customer.payments.checkout', $this->reservation), [
            'amount' => 2000, 'payment_method' => PaymentMethod::GCash->value,
        ])->assertSessionHas('error');
        $this->assertSame(1, $this->reservation->payments()->count());
    }
}

class FakePaymentGateway implements PaymentGateway
{
    public function __construct(private readonly bool $paid) {}

    public function isConfigured(): bool
    {
        return true;
    }

    public function label(): string
    {
        return 'Fake gateway';
    }

    public function createCheckout(Payment $payment, string $successUrl, string $cancelUrl): CheckoutSession
    {
        return new CheckoutSession('fake-session', 'https://gateway.test/checkout/fake-session');
    }

    public function isPaid(string $sessionId): bool
    {
        return $this->paid;
    }
}
