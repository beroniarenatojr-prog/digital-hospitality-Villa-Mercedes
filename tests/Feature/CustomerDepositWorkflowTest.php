<?php

namespace Tests\Feature;

use App\Enums\{FacilityStatus, PaymentMethod, PaymentStatus, ReservationSource, ReservationStatus};
use App\Exceptions\Payment\{InvalidPaymentAmountException, PaymentNotEligibleException};
use App\Exceptions\Reservation\{InvalidReservationTransitionException, ReservationConflictException};
use App\Models\{Facility, FacilityType, Payment, PaymentDestination, Reservation, User};
use App\Services\{AvailabilityService, PaymentService, ReservationService};
use Database\Seeders\{FacilityTypeSeeder, RoleSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Http, Notification, Storage};
use Tests\TestCase;

class CustomerDepositWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $admin;
    protected Facility $room;
    protected PaymentDestination $destination;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.deposit_workflow' => true, 'payments.allow_gateway_checkout' => false]);
        Notification::fake();
        Http::preventStrayRequests();
        Storage::fake('uploads');
        $this->seed([RoleSeeder::class, FacilityTypeSeeder::class]);
        $this->customer = User::factory()->create(['name' => 'Maria Santos']);
        $this->customer->assignRole('Customer');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Administrator');
        $this->room = Facility::factory()->create(['facility_type_id' => FacilityType::where('slug', 'room')->firstOrFail()->id,
            'status' => FacilityStatus::Available, 'capacity' => 4, 'max_capacity' => 6, 'is_active' => true]);
        $this->room->rates()->create(['name' => 'Test rate', 'type' => 'base', 'amount' => 3000.01, 'unit' => 'per night', 'is_active' => true]);
        $this->destination = PaymentDestination::create(['method' => 'gcash', 'account_name' => 'Test resort account',
            'account_number' => 'TEST-ONLY', 'qr_path' => 'test.png', 'is_active' => true]);
        Storage::disk('uploads')->put('test.png', 'test-only');
    }

    protected function booking(): Reservation
    {
        return app(ReservationService::class)->createReservation($this->room, $this->customer,
            now()->addDays(4)->setTime(14, 0), now()->addDays(5)->setTime(12, 0), ReservationSource::Online, 2);
    }

    protected function proof(Reservation $reservation, ?float $amount = null, ?string $reference = null): Payment
    {
        $details = ['payment_destination_id' => $this->destination->id];
        if ($reference !== null) {
            $details['reference_number'] = $reference;
        }

        return app(PaymentService::class)->createPayment($reservation, $this->customer, $amount ?? $reservation->requiredDepositAmount(),
            PaymentMethod::GCash, 'proof.png', $details);
    }

    public function test_saved_quote_rounds_deposit_up_and_blocks_competing_booking(): void
    {
        $this->freezeTime();
        $reservation = $this->booking();
        $this->assertSame('3000.01', $reservation->total_price);
        $this->assertSame('1500.01', $reservation->deposit_required);
        $this->assertSame(now()->addHours(24)->toDateTimeString(), $reservation->deposit_due_at->toDateTimeString());
        $this->assertTrue($reservation->canAcceptGuestPayment());
        $this->assertFalse($reservation->canShowArrivalPass());
        $this->expectException(ReservationConflictException::class);
        $this->booking();
    }

    public function test_no_active_destination_creates_no_unpayable_hold(): void
    {
        $this->destination->update(['is_active' => false]);
        try { $this->booking(); $this->fail('Booking should be refused'); }
        catch (ReservationConflictException $e) { $this->assertStringContainsString('instructions', $e->getMessage()); }
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_deadline_is_capped_at_visit_start(): void
    {
        $this->freezeTime();
        $reservation = app(ReservationService::class)->createReservation($this->room, $this->customer,
            now('Asia/Manila')->addHours(6)->shiftTimezone(config('app.timezone')), now('Asia/Manila')->addHours(30)->shiftTimezone(config('app.timezone')), ReservationSource::Online, 2);
        $this->assertTrue($reservation->deposit_due_at->equalTo(\App\Support\DepositDeadline::visitInstant($reservation->check_in)));
    }

    public function test_timely_proof_survives_deadline_and_verification_confirms_booking(): void
    {
        $reservation = $this->booking();
        $payment = $this->proof($reservation);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->travel(25)->hours();
        $this->artisan('reservations:expire-unpaid')->assertSuccessful();
        $this->assertSame(ReservationStatus::Pending, $reservation->fresh()->status);
        app(PaymentService::class)->verifyPayment($payment, $this->admin);
        $reservation->refresh();
        $this->assertSame(ReservationStatus::Approved, $reservation->status);
        $this->assertSame(1500.0, $reservation->getRemainingBalance());
        $this->assertFalse($reservation->canAcceptGuestPayment());
        $this->assertTrue($reservation->canShowArrivalPass());
        $explanations = app(\App\Services\Admin\DecisionSupportService::class)->analyze($reservation)->explanations;
        $this->assertContains('The required deposit is already verified.', $explanations);
        $this->assertStringNotContainsString('not fully verified yet', implode(' ', $explanations));
    }

    public function test_expiry_only_releases_new_unpaid_holds_and_late_proof_is_refused(): void
    {
        $reservation = $this->booking();
        $legacy = Reservation::factory()->create(['user_id' => $this->customer->id, 'status' => ReservationStatus::Pending]);
        $this->travel(25)->hours();
        $this->artisan('reservations:expire-unpaid')->assertSuccessful();
        $this->assertSame(ReservationStatus::Expired, $reservation->fresh()->status);
        $this->assertSame(ReservationStatus::Pending, $legacy->fresh()->status);
        $this->assertTrue(app(AvailabilityService::class)->isAvailable($this->room, $reservation->check_in, $reservation->check_out)->isAvailable);
        $this->expectException(PaymentNotEligibleException::class);
        $this->proof($reservation);
    }

    public function test_partial_verification_requests_only_the_deposit_shortfall(): void
    {
        $reservation = $this->booking();
        $payment = $this->proof($reservation, 500);
        app(PaymentService::class)->verifyPayment($payment, $this->admin);
        $this->assertSame(ReservationStatus::Pending, $reservation->fresh()->status);
        $this->assertSame(1000.01, $reservation->fresh()->getDepositAvailableAmount());
        $this->travel(25)->hours();
        $this->artisan('reservations:expire-unpaid')->assertSuccessful();
        $this->assertSame(ReservationStatus::Pending, $reservation->fresh()->status, 'Received funds must be reconciled before release');
    }

    public function test_rejected_proof_can_be_corrected_before_new_deadline(): void
    {
        $reservation = $this->booking();
        $payment = $this->proof($reservation);
        app(PaymentService::class)->rejectPayment($payment, $this->admin, 'Reference not received in the account. Check the recipient.');
        $this->assertTrue($reservation->fresh()->canAcceptGuestPayment());
        $replacement = $this->proof($reservation, null, 'CORRECTED');
        app(PaymentService::class)->verifyPayment($replacement, $this->admin);
        $this->assertSame(ReservationStatus::Approved, $reservation->fresh()->status);
    }

    public function test_verified_duplicate_reference_requires_reconciliation_note(): void
    {
        $reservation = $this->booking();
        $one = $this->proof($reservation, 500, 'DUPLICATE');
        $two = $this->proof($reservation, 500, 'duplicate');
        app(PaymentService::class)->verifyPayment($one, $this->admin);
        $this->expectException(InvalidPaymentAmountException::class);
        app(PaymentService::class)->verifyPayment($two, $this->admin);
    }

    public function test_pending_proof_prevents_admin_cancellation(): void
    {
        $reservation = $this->booking();
        $this->proof($reservation);
        $this->expectException(InvalidReservationTransitionException::class);
        app(ReservationService::class)->changeStatus($reservation, ReservationStatus::Rejected, $this->admin, 'Cancel');
    }

    public function test_customer_balance_post_is_blocked_and_staff_balance_enables_check_in(): void
    {
        $reservation = $this->booking();
        app(PaymentService::class)->verifyPayment($this->proof($reservation), $this->admin);
        $this->actingAs($this->customer)->post(route('customer.payments.store', $reservation), [
            'amount' => 1500, 'payment_method' => 'gcash', 'payment_destination_id' => $this->destination->id,
            'proof' => UploadedFile::fake()->image('proof.png'),
        ])->assertSessionHas('error');
        $this->assertDatabaseCount('payments', 1);
        try { app(ReservationService::class)->checkIn($reservation, $this->admin); $this->fail('Balance must be collected'); }
        catch (InvalidReservationTransitionException $e) { $this->assertStringContainsString('balance', $e->getMessage()); }
        $this->actingAs($this->admin)->post(route('admin.payments.onsite', $reservation), [
            'amount' => 1500, 'method' => 'cash', 'collected_at' => now()->subMinute()->toDateTimeString(), 'funds_received' => 1,
        ])->assertSessionHas('success');
        app(ReservationService::class)->checkIn($reservation, $this->admin);
        $this->assertNotNull($reservation->fresh()->checked_in_at);
        $this->assertDatabaseHas('payments', ['source' => 'onsite', 'verified_by' => $this->admin->id, 'amount' => 1500]);
    }

    public function test_gateway_checkout_is_disabled_even_for_confirmed_booking(): void
    {
        $reservation = $this->booking();
        $reservation->update(['status' => ReservationStatus::Approved]);
        $this->actingAs($this->customer)->post(route('customer.payments.checkout', $reservation), ['amount' => 100, 'payment_method' => 'gcash'])->assertSessionHas('error');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_customer_pages_render_and_admin_tools_are_protected(): void
    {
        $reservation = $this->booking();
        $this->actingAs($this->customer)->get(route('customer.reservations.new'))->assertOk()->assertSee('New reservation')->assertSee('My Inquiries');
        $this->get(route('customer.payments.index', $reservation))->assertOk()->assertSee('Download QR')->assertSee('1,500.01');
        $this->get(route('customer.reservations.show', $reservation))->assertOk()->assertDontSee('Rate your experience');
        $this->get(route('admin.payment-destinations.index'))->assertRedirect(route('admin.login'));
        $this->actingAs($this->admin)->get(route('admin.payment-destinations.index'))->assertOk();
        $this->get(route('admin.reservations.show', $reservation))->assertOk()->assertSee('Record on-site payment');
        $payment = $this->proof($reservation);
        $this->get(route('admin.payments.show', $payment))->assertOk()->assertSee('Deposit review');
        $this->get(route('admin.payments.index'))->assertOk();
    }

    public function test_admin_can_upload_disable_and_reactivate_verified_qr(): void
    {
        $this->actingAs($this->admin)->post(route('admin.payment-destinations.store'), [
            'method' => 'gcash', 'account_name' => 'Test only', 'account_number' => 'TEST',
            'verified_account' => 1, 'qr' => UploadedFile::fake()->image('qr.png'),
        ])->assertSessionHas('success');
        $new = PaymentDestination::latest('id')->first();
        $this->get(route('payment-destinations.image', $new))->assertOk();
        $this->patch(route('admin.payment-destinations.toggle', $new), ['is_active' => 0])->assertSessionHas('success');
        $this->assertFalse($new->fresh()->is_active);
        $this->patch(route('admin.payment-destinations.toggle', $new), ['is_active' => 1])->assertSessionHasErrors('verified_account');
        $this->patch(route('admin.payment-destinations.toggle', $new), ['is_active' => 1, 'verified_account' => 1])->assertSessionHas('success');
    }

    public function test_deposit_review_requires_explicit_account_reconciliation(): void
    {
        $reservation = $this->booking();
        $payment = $this->proof($reservation);
        $this->actingAs($this->admin)->patch(route('admin.payments.verify', $payment))->assertSessionHasErrors('funds_received');
        $this->patch(route('admin.payments.reject', $payment), ['reason' => 'Unclear image'])->assertSessionHasErrors('funds_not_received');
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->patch(route('admin.payments.verify', $payment), ['funds_received' => 1])->assertSessionHas('success');
        $this->assertSame(ReservationStatus::Approved, $reservation->fresh()->status);
    }

    public function test_top_up_reopens_only_a_shortfall_and_preserves_account_snapshot(): void
    {
        $reservation = $this->booking();
        $payment = $this->proof($reservation, 500);
        app(PaymentService::class)->verifyPayment($payment, $this->admin);
        $snapshot = $payment->fresh()->destination_snapshot;
        $this->destination->update(['account_name' => 'Replacement test details']);
        $this->travel(25)->hours();
        $this->assertFalse($reservation->fresh()->canAcceptGuestPayment());
        $this->actingAs($this->admin)->post(route('admin.payments.top-up', $reservation), ['reason' => 'Only the partial transfer was received.'])->assertSessionHas('success');
        $this->assertTrue($reservation->fresh()->canAcceptGuestPayment());
        $this->assertSame(1000.01, $reservation->fresh()->getDepositAvailableAmount());
        $this->assertSame($snapshot, $payment->fresh()->destination_snapshot);
    }

    public function test_late_received_funds_are_recorded_without_restoring_expired_inventory(): void
    {
        $reservation = $this->booking();
        $this->travel(25)->hours();
        $this->artisan('reservations:expire-unpaid')->assertSuccessful();
        app(PaymentService::class)->recordOnSite($reservation, $this->admin, ['amount' => 1500.01, 'method' => 'gcash',
            'reference_number' => 'LATE-RECONCILED', 'collected_at' => now()->toDateTimeString(), 'reconciliation_note' => 'Late transfer matched; contact guest to arrange refund or a new available booking.']);
        $this->assertSame(ReservationStatus::Expired, $reservation->fresh()->status);
        $this->assertSame(1500.01, $reservation->fresh()->getVerifiedTotalPaid());
    }

    public function test_rescheduling_unpaid_hold_requotes_without_restarting_deadline(): void
    {
        $reservation = $this->booking();
        $deadline = $reservation->deposit_due_at->toDateTimeString();
        app(ReservationService::class)->reschedule($reservation, $this->room, now()->addDays(7)->setTime(14, 0),
            now()->addDays(9)->setTime(12, 0), $this->customer, 2);
        $this->assertSame('3000.01', $reservation->fresh()->deposit_required);
        $this->assertSame($deadline, $reservation->fresh()->deposit_due_at->toDateTimeString());
    }

    public function test_direct_approval_cannot_skip_deposit_verification(): void
    {
        $reservation = $this->booking();
        $this->expectException(InvalidReservationTransitionException::class);
        app(ReservationService::class)->changeStatus($reservation, ReservationStatus::Approved, $this->admin);
    }

    public function test_deposit_proof_requires_only_amount_destination_and_proof(): void
    {
        $this->freezeTime();
        $reservation = $this->booking();
        $this->actingAs($this->customer)->post(route('customer.payments.store', $reservation), [
            'amount' => 1500.01, 'payment_method' => 'gcash', 'payment_destination_id' => $this->destination->id,
            'proof' => UploadedFile::fake()->image('proof.png'),
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('payments', [
            'reservation_id' => $reservation->id,
            'reference_number' => null,
            'payment_date' => now()->toDateString(),
            'amount' => 1500.01,
            'source' => 'customer_deposit',
        ]);
    }

    public function test_retiring_an_account_does_not_discard_proof_of_an_existing_transfer(): void
    {
        $reservation = $this->booking();
        $this->travel(1)->minutes();
        $this->destination->update(['is_active' => false]);
        $payment = $this->proof($reservation);
        $this->assertSame('customer_deposit', $payment->source);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->actingAs($this->customer)->get(route('customer.payments.index', $reservation))->assertOk()->assertDontSee('Download QR');
    }

    public function test_other_customer_cannot_upload_proof_or_record_staff_payments(): void
    {
        $reservation = $this->booking();
        $other = User::factory()->create();
        $this->actingAs($other)->post(route('customer.payments.store', $reservation), [
            'amount' => 1500.01, 'payment_method' => 'gcash', 'payment_destination_id' => $this->destination->id,
            'proof' => UploadedFile::fake()->image('proof.png'),
        ])->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }
}
