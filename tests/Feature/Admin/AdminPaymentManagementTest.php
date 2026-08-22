<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPaymentManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;
    private Facility $facility;
    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Administrator->value);

        $this->customer = User::factory()->create();
        $this->customer->assignRole(Role::Customer->value);

        $this->facility = Facility::first();

        $this->reservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'total_price' => 5000.00,
        ]);
    }

    private function createPayment(array $overrides = []): Payment
    {
        return Payment::factory()->create(array_merge([
            'reservation_id' => $this->reservation->id,
            'amount' => 2500.00,
            'method' => PaymentMethod::GCash->value,
            'status' => PaymentStatus::Pending->value,
            'payment_date' => now()->toDateString(),
        ], $overrides));
    }

    // --- Access Control ---

    public function test_unauthenticated_user_cannot_access_payment_management(): void
    {
        $response = $this->get(route('admin.payments.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_payment_management(): void
    {
        $response = $this->actingAs($this->customer)->get(route('admin.payments.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_access_payment_listing(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('Payment Management');
    }

    // --- Listing ---

    public function test_payments_display_in_listing(): void
    {
        $payment = $this->createPayment();

        $response = $this->actingAs($this->admin)->get(route('admin.payments.index'));
        $response->assertStatus(200);
        $response->assertSee($this->reservation->reservation_code);
        $response->assertSee('2,500.00');
    }

    public function test_pending_payments_are_visible(): void
    {
        $this->createPayment(['status' => PaymentStatus::Pending->value]);

        $response = $this->actingAs($this->admin)->get(route('admin.payments.index', ['status' => 'pending']));
        $response->assertStatus(200);

        $payments = $response->viewData('payments');
        $this->assertTrue($payments->every(fn ($p) => $p->status === PaymentStatus::Pending));
    }

    public function test_listing_filters_by_method(): void
    {
        $this->createPayment(['method' => PaymentMethod::GCash->value]);
        $this->createPayment(['method' => PaymentMethod::BankTransfer->value]);

        $response = $this->actingAs($this->admin)->get(route('admin.payments.index', ['method' => 'gcash']));
        $response->assertStatus(200);

        $payments = $response->viewData('payments');
        $this->assertTrue($payments->every(fn ($p) => $p->method === PaymentMethod::GCash));
    }

    public function test_listing_search_by_reservation_code(): void
    {
        $this->createPayment();

        $response = $this->actingAs($this->admin)->get(route('admin.payments.index', ['search' => $this->reservation->reservation_code]));
        $response->assertStatus(200);

        $payments = $response->viewData('payments');
        $this->assertGreaterThanOrEqual(1, $payments->count());
    }

    public function test_listing_pagination_works(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.payments.index'));
        $response->assertStatus(200);

        // View data should be a paginator
        $payments = $response->viewData('payments');
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $payments);
    }

    // --- Detail Page ---

    public function test_payment_details_render(): void
    {
        $payment = $this->createPayment();

        $response = $this->actingAs($this->admin)->get(route('admin.payments.show', $payment));
        $response->assertStatus(200);
        $response->assertSee('Payment Information');
        $response->assertSee('2,500.00');
        $response->assertSee($payment->method->label());
    }

    public function test_reservation_context_renders(): void
    {
        $payment = $this->createPayment();

        $response = $this->actingAs($this->admin)->get(route('admin.payments.show', $payment));
        $response->assertStatus(200);
        $response->assertSee('Reservation Context');
        $response->assertSee($this->reservation->reservation_code);
        $response->assertSee($this->facility->name);
    }

    public function test_balance_is_correct(): void
    {
        // Create one verified and one pending payment
        Payment::factory()->create([
            'reservation_id' => $this->reservation->id,
            'amount' => 2000.00,
            'status' => PaymentStatus::Verified->value,
            'payment_date' => now()->toDateString(),
        ]);

        $pendingPayment = $this->createPayment(['amount' => 1500.00]);

        $response = $this->actingAs($this->admin)->get(route('admin.payments.show', $pendingPayment));
        $response->assertStatus(200);

        // Reservation total is 5000, verified paid is 2000, remaining is 3000
        $response->assertSee('5,000.00');
        $response->assertSee('2,000.00');
        $response->assertSee('3,000.00');
    }

    public function test_sensitive_user_fields_not_exposed(): void
    {
        $payment = $this->createPayment();

        $response = $this->actingAs($this->admin)->get(route('admin.payments.show', $payment));
        $response->assertStatus(200);

        // Password hash must never appear in the rendered HTML
        $response->assertDontSee($this->customer->password);
    }

    // --- Proof of Payment ---

    public function test_authorized_admin_can_download_proof(): void
    {
        Storage::fake('uploads');
        Storage::disk('uploads')->put('proofs/test-proof.jpg', 'fake-content');

        $payment = $this->createPayment(['proof_path' => 'proofs/test-proof.jpg']);

        $response = $this->actingAs($this->admin)->get(route('admin.payments.download', $payment));
        $response->assertStatus(200);
    }

    public function test_customer_cannot_access_admin_proof_route(): void
    {
        $payment = $this->createPayment(['proof_path' => 'proofs/test.jpg']);

        $response = $this->actingAs($this->customer)->get(route('admin.payments.download', $payment));
        $response->assertStatus(403);
    }

    public function test_missing_proof_handled_safely(): void
    {
        $payment = $this->createPayment(['proof_path' => null]);

        $response = $this->actingAs($this->admin)->get(route('admin.payments.download', $payment));
        $response->assertStatus(404);
    }

    public function test_filesystem_path_not_exposed(): void
    {
        $payment = $this->createPayment(['proof_path' => 'proofs/secret-file.jpg']);

        $response = $this->actingAs($this->admin)->get(route('admin.payments.show', $payment));
        $response->assertStatus(200);

        // The raw filesystem path must never be rendered in HTML
        $response->assertDontSee('proofs/secret-file.jpg');
    }

    // --- Verification ---

    public function test_pending_payment_can_be_verified(): void
    {
        $payment = $this->createPayment();

        $response = $this->actingAs($this->admin)->patch(route('admin.payments.verify', $payment));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Verified, $payment->status);
        $this->assertEquals($this->admin->id, $payment->verified_by);
        $this->assertNotNull($payment->verified_at);
    }

    public function test_verified_payment_cannot_be_verified_again(): void
    {
        $payment = $this->createPayment(['status' => PaymentStatus::Verified->value, 'verified_by' => $this->admin->id, 'verified_at' => now()]);

        $response = $this->actingAs($this->admin)->patch(route('admin.payments.verify', $payment));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_verifier_is_set_by_backend(): void
    {
        $payment = $this->createPayment();

        $this->actingAs($this->admin)->patch(route('admin.payments.verify', $payment));

        $payment->refresh();
        $this->assertEquals($this->admin->id, $payment->verified_by);
    }

    // --- Rejection ---

    public function test_pending_payment_can_be_rejected(): void
    {
        $payment = $this->createPayment();

        $response = $this->actingAs($this->admin)->patch(route('admin.payments.reject', $payment), [
            'reason' => 'Invalid proof of payment.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Rejected, $payment->status);
        $this->assertEquals('Invalid proof of payment.', $payment->rejection_reason);
    }

    public function test_rejection_reason_is_required(): void
    {
        $payment = $this->createPayment();

        $response = $this->actingAs($this->admin)->patch(route('admin.payments.reject', $payment), [
            'reason' => '',
        ]);

        $response->assertSessionHasErrors(['reason']);
    }

    public function test_rejected_payment_remains_stored(): void
    {
        $payment = $this->createPayment();

        $this->actingAs($this->admin)->patch(route('admin.payments.reject', $payment), [
            'reason' => 'Bad proof.',
        ]);

        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    public function test_rejected_payment_cannot_be_verified(): void
    {
        $payment = $this->createPayment([
            'status' => PaymentStatus::Rejected->value,
            'rejection_reason' => 'Bad proof.',
            'verified_by' => $this->admin->id,
            'verified_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->patch(route('admin.payments.verify', $payment));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Rejected, $payment->status);
    }

    // --- Security ---

    public function test_status_cannot_be_spoofed(): void
    {
        $payment = $this->createPayment();

        // Attempt to send status in the verify request body (should be ignored)
        $response = $this->actingAs($this->admin)->patch(route('admin.payments.verify', $payment), [
            'status' => 'rejected',
        ]);

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Verified, $payment->status);
    }

    public function test_verified_by_cannot_be_spoofed(): void
    {
        $payment = $this->createPayment();

        $otherAdmin = User::factory()->create();
        $otherAdmin->assignRole(Role::Administrator->value);

        // Attempt to spoof verified_by
        $this->actingAs($this->admin)->patch(route('admin.payments.verify', $payment), [
            'verified_by' => $otherAdmin->id,
        ]);

        $payment->refresh();
        $this->assertEquals($this->admin->id, $payment->verified_by);
    }

    public function test_customer_cannot_perform_admin_payment_actions(): void
    {
        $payment = $this->createPayment();

        $verifyResponse = $this->actingAs($this->customer)->patch(route('admin.payments.verify', $payment));
        $verifyResponse->assertStatus(403);

        $rejectResponse = $this->actingAs($this->customer)->patch(route('admin.payments.reject', $payment), [
            'reason' => 'Hack attempt',
        ]);
        $rejectResponse->assertStatus(403);
    }
}
