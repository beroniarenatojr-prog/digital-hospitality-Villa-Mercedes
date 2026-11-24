<?php

namespace Tests\Feature;

use App\Enums\{FacilityStatus, GuestPaymentState, PaymentMethod, PaymentStatus, ReservationSource, ReservationStatus};
use App\Exceptions\Payment\{InvalidPaymentAmountException, PaymentNotEligibleException};
use App\Exceptions\Reservation\{InvalidReservationTransitionException, ReservationConflictException};
use App\Models\{Facility, FacilityType, Payment, PaymentDestination, Reservation, User};
use App\Services\{AvailabilityService, PaymentService, ReservationService};
use Database\Seeders\{FacilityTypeSeeder, RoleSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Http, Notification, Storage};
use Tests\TestCase;

/**
 * The deposit workflow, in the order the resort actually runs it: a guest submits
 * a request, staff approve it, and only then does the 50% QR deposit open. Every
 * test here starts from that order — money is never the thing that confirms a
 * booking, and approval is never something a transfer performs on the guest's
 * behalf.
 */
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
        // These fixtures exercise the historical approval-before-deposit contract; new published practices have their own regression suite.
        config(['payments.deposit_workflow' => true, 'payments.allow_gateway_checkout' => false, 'resort.policy.version' => '2026-09-v2']);
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

    /** A submitted request: Review pending until a person answers it. */
    protected function booking(): Reservation
    {
        return app(ReservationService::class)->createReservation($this->room, $this->customer,
            now()->addDays(4)->setTime(14, 0), now()->addDays(5)->setTime(12, 0), ReservationSource::Online, 2);
    }

    /** Staff approve the request. This is what opens the 50% step. */
    protected function approved(): Reservation
    {
        $reservation = $this->booking();
        app(ReservationService::class)->changeStatus($reservation, ReservationStatus::Approved, $this->admin);

        return $reservation->refresh();
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

    public function test_a_submitted_request_waits_for_review_and_pays_nothing_yet(): void
    {
        $this->freezeTime();
        $reservation = $this->booking();
        $this->assertSame('3000.01', $reservation->total_price);
        $this->assertSame('1500.01', $reservation->deposit_required);
        $this->assertSame(now()->addHours(24)->toDateTimeString(), $reservation->deposit_due_at->toDateTimeString(), 'The review window is the hold.');
        $this->assertSame(ReservationStatus::Pending, $reservation->status);
        $this->assertFalse($reservation->canAcceptGuestPayment(), 'Nothing is payable before the resort answers.');
        $this->assertSame(GuestPaymentState::AwaitingApproval, $reservation->guestPaymentState());
        $this->assertFalse($reservation->canShowArrivalPass());
        $this->expectException(ReservationConflictException::class);
        $this->booking();
    }

    public function test_confirming_a_request_lands_on_its_review_pending_tab(): void
    {
        $this->actingAs($this->customer);

        $response = $this->post(route('customer.reservations.store'), [
            'facility_id' => $this->room->id,
            'check_in' => now()->addDays(4)->setTime(14, 0)->toDateTimeString(),
            'check_out' => now()->addDays(5)->setTime(12, 0)->toDateTimeString(),
            'guestCount' => 2,
        ] + $this->guestInformationPayload());

        // The request is not a booking, so the guest is left where its state is.
        $response->assertRedirect(route('customer.reservations.index', ['tab' => 'pending']));
        $response->assertSessionHas('success', fn (string $message) => str_contains($message, 'Review pending'));

        $this->assertSame(ReservationStatus::Pending, Reservation::latest('id')->firstOrFail()->status);
        $this->get(route('customer.reservations.index', ['tab' => 'pending']))->assertOk()->assertSee('Review pending');
    }

    public function test_approval_opens_the_deposit_window_and_survives_the_review_deadline(): void
    {
        $this->freezeTime();
        $reservation = $this->booking();
        $this->travel(5)->hours();

        app(ReservationService::class)->changeStatus($reservation, ReservationStatus::Approved, $this->admin);
        $reservation->refresh();

        $this->assertSame(ReservationStatus::Approved, $reservation->status);
        $this->assertNotNull($reservation->approved_at);
        // A fresh window from the moment the guest is told, not the review window
        // they were never asked to pay inside.
        $this->assertSame(now()->addHours(24)->toDateTimeString(), $reservation->deposit_due_at->toDateTimeString());
        $this->assertSame(GuestPaymentState::PaymentAvailable, $reservation->guestPaymentState());
        $this->assertTrue($reservation->canAcceptGuestPayment());
        $this->assertFalse($reservation->canShowArrivalPass(), 'The pass is still earned by the verified deposit.');
    }

    public function test_no_active_destination_creates_no_unpayable_hold(): void
    {
        $this->destination->update(['is_active' => false]);
        try { $this->booking(); $this->fail('Booking should be refused'); }
        catch (ReservationConflictException $e) { $this->assertStringContainsString('instructions', $e->getMessage()); }
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_review_window_is_capped_at_visit_start(): void
    {
        $this->freezeTime();

        // A visit that begins sooner than the hold window: the space is needed
        // before the window runs out, so the visit's own start is the deadline.
        // The resort's 2 PM check-in is fixed, so the date moves, not the hour.
        $arrival = now('Asia/Manila')->startOfDay()->addHours(14);
        if ($arrival->lessThanOrEqualTo(now('Asia/Manila'))) {
            $arrival->addDay();
        }

        $reservation = app(ReservationService::class)->createReservation($this->room, $this->customer,
            $arrival->copy()->shiftTimezone(config('app.timezone')), $arrival->copy()->addDay()->subHours(2)->shiftTimezone(config('app.timezone')), ReservationSource::Online, 2);
        $this->assertTrue($reservation->deposit_due_at->equalTo(\App\Support\DepositDeadline::visitInstant($reservation->check_in)));
    }

    public function test_unreviewed_request_expires_and_a_late_proof_is_refused(): void
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

    public function test_an_approved_booking_without_its_deposit_is_released_after_the_deadline(): void
    {
        $reservation = $this->approved();
        $this->travel(23)->hours();
        $this->assertTrue($reservation->fresh()->canAcceptGuestPayment(), 'The window approval set is the guest\'s to use.');
        $this->travel(2)->hours();
        $this->assertFalse($reservation->fresh()->canAcceptGuestPayment());
        $this->artisan('reservations:expire-unpaid')->assertSuccessful();
        $this->assertSame(ReservationStatus::Expired, $reservation->fresh()->status);
        $this->assertTrue(app(AvailabilityService::class)->isAvailable($this->room, $reservation->check_in, $reservation->check_out)->isAvailable,
            'An unpaid confirmation must go back on the market.');
    }

    public function test_a_stay_that_has_begun_is_never_released_by_the_payment_deadline(): void
    {
        // Approved hours before arrival, so the deposit window is capped at the
        // visit's own start: the deadline passes while the guest is on site, and
        // the desk settles it rather than a timer releasing an occupied space.
        $arrival = now('Asia/Manila')->startOfDay()->addHours(14);
        if ($arrival->lessThan(now('Asia/Manila')->addHours(2))) {
            $arrival->addDay();
        }

        $reservation = app(ReservationService::class)->createReservation($this->room, $this->customer,
            $arrival->copy()->shiftTimezone(config('app.timezone')), $arrival->copy()->addDay()->subHours(2)->shiftTimezone(config('app.timezone')), ReservationSource::Online, 2);
        app(ReservationService::class)->changeStatus($reservation, ReservationStatus::Approved, $this->admin);

        $this->travelTo($reservation->check_in->copy()->addHour());
        $this->artisan('reservations:expire-unpaid')->assertSuccessful();

        $this->assertSame(ReservationStatus::Approved, $reservation->fresh()->status);
    }

    public function test_timely_proof_survives_the_deadline_and_verification_records_the_transfer(): void
    {
        $reservation = $this->approved();
        $payment = $this->proof($reservation);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->travel(25)->hours();
        $this->artisan('reservations:expire-unpaid')->assertSuccessful();
        $this->assertSame(ReservationStatus::Approved, $reservation->fresh()->status, 'A submitted proof keeps the booking held while staff check it.');
        app(PaymentService::class)->verifyPayment($payment, $this->admin);
        $reservation->refresh();
        $this->assertSame(ReservationStatus::Approved, $reservation->status, 'Verifying money records money — it does not confirm a booking.');
        $this->assertSame(1500.0, $reservation->getRemainingBalance());
        $this->assertFalse($reservation->canAcceptGuestPayment());
        $this->assertTrue($reservation->canShowArrivalPass());
        $explanations = app(\App\Services\Admin\DecisionSupportService::class)->analyze($reservation)->explanations;
        $this->assertContains('The required deposit is already verified.', $explanations);
        $this->assertStringNotContainsString('not fully verified yet', implode(' ', $explanations));
    }

    public function test_partial_verification_requests_only_the_deposit_shortfall(): void
    {
        $reservation = $this->approved();
        $payment = $this->proof($reservation, 500);
        app(PaymentService::class)->verifyPayment($payment, $this->admin);
        $this->assertSame(ReservationStatus::Approved, $reservation->fresh()->status);
        $this->assertSame(1000.01, $reservation->fresh()->getDepositAvailableAmount());
        $this->travel(25)->hours();
        $this->artisan('reservations:expire-unpaid')->assertSuccessful();
        $this->assertSame(ReservationStatus::Approved, $reservation->fresh()->status, 'Received funds must be reconciled before release');
    }

    public function test_rejected_proof_can_be_corrected_before_a_new_deadline(): void
    {
        $reservation = $this->approved();
        $payment = $this->proof($reservation);
        app(PaymentService::class)->rejectPayment($payment, $this->admin, 'Reference not received in the account. Check the recipient.');
        $this->assertTrue($reservation->fresh()->canAcceptGuestPayment());
        $replacement = $this->proof($reservation, null, 'CORRECTED');
        app(PaymentService::class)->verifyPayment($replacement, $this->admin);
        $this->assertSame(ReservationStatus::Approved, $reservation->fresh()->status);
        $this->assertTrue($reservation->fresh()->canShowArrivalPass());
    }

    public function test_verified_duplicate_reference_requires_reconciliation_note(): void
    {
        $reservation = $this->approved();
        $one = $this->proof($reservation, 500, 'DUPLICATE');
        $two = $this->proof($reservation, 500, 'duplicate');
        app(PaymentService::class)->verifyPayment($one, $this->admin);
        $this->expectException(InvalidPaymentAmountException::class);
        app(PaymentService::class)->verifyPayment($two, $this->admin);
    }

    public function test_pending_proof_prevents_admin_cancellation(): void
    {
        $reservation = $this->approved();
        $this->proof($reservation);
        $this->expectException(InvalidReservationTransitionException::class);
        app(ReservationService::class)->changeStatus($reservation, ReservationStatus::Rejected, $this->admin, 'Cancel');
    }

    public function test_customer_balance_post_is_blocked_and_staff_balance_enables_check_in(): void
    {
        $reservation = $this->approved();
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

    public function test_recording_an_on_site_payment_does_not_confirm_a_request(): void
    {
        $reservation = $this->booking();
        app(PaymentService::class)->recordOnSite($reservation, $this->admin, ['amount' => 1500.01, 'method' => 'cash',
            'collected_at' => now()->subMinute()->toDateTimeString(), 'reconciliation_note' => 'Collected at the desk while the request waits for approval.']);
        $this->assertSame(ReservationStatus::Pending, $reservation->fresh()->status, 'Money is not the approval.');
    }

    public function test_gateway_checkout_is_disabled_even_for_confirmed_booking(): void
    {
        $reservation = $this->approved();
        $this->actingAs($this->customer)->post(route('customer.payments.checkout', $reservation), ['amount' => 100, 'payment_method' => 'gcash'])->assertSessionHas('error');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_customer_pages_render_and_admin_tools_are_protected(): void
    {
        $reservation = $this->booking();
        $this->actingAs($this->customer)->get(route('customer.reservations.new'))->assertOk()->assertSee('Choose a space')->assertDontSee('My inquiries');
        // While the request is under review the page explains the wait and offers
        // no QR to scan.
        $this->get(route('customer.payments.index', $reservation))->assertOk()->assertSee('Review pending')->assertDontSee('Download QR');
        $this->get(route('customer.reservations.show', $reservation))->assertOk()->assertDontSee('Rate your experience');
        $this->get(route('admin.payment-destinations.index'))->assertRedirect(route('admin.login'));
        $this->actingAs($this->admin)->get(route('admin.payment-destinations.index'))->assertOk();
        $this->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertDontSee('Record remaining reservation balance')
            ->assertSee('Approve reservation');

        $walkIn = Reservation::factory()->create([
            'user_id' => $this->admin->id,
            'facility_id' => $this->room->id,
            'reservation_source' => ReservationSource::WalkIn->value,
            'status' => ReservationStatus::Approved->value,
            'total_price' => 3000.01,
            'check_in' => now()->addDays(10)->setTime(14, 0),
            'check_out' => now()->addDays(11)->setTime(12, 0),
        ]);

        $this->get(route('admin.reservations.show', $walkIn))
            ->assertOk()
            ->assertSee('Record remaining reservation balance')
            ->assertSee('Remaining balance collected');
        app(ReservationService::class)->changeStatus($reservation, ReservationStatus::Approved, $this->admin);
        $this->actingAs($this->customer);
        $this->get(route('customer.payments.index', $reservation))->assertOk()->assertSee('Download QR')->assertSee('1,500.01');
        $payment = $this->proof($reservation);
        $this->actingAs($this->admin);
        $this->get(route('admin.payments.show', $payment))->assertOk()->assertSee('Deposit review');
        $this->get(route('admin.payments.index'))->assertOk();
    }

    public function test_front_desk_preparation_controls_only_show_during_arrival_preparation(): void
    {
        $reservation = $this->approved();
        $this->actingAs($this->admin)
            ->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertDontSee('Download guest sheet')
            ->assertDontSee('Check in guest');

        app(PaymentService::class)->verifyPayment($this->proof($reservation), $this->admin);

        $this->get(route('admin.reservations.show', $reservation->fresh()))
            ->assertOk()
            ->assertSee('Awaiting check-in')
            ->assertSee('Record remaining reservation balance')
            ->assertSee('Remaining reservation balance:')
            ->assertSee('Remaining balance collected')
            ->assertSee('Record balance payment')
            ->assertSee('Download guest sheet')
            ->assertSee('Print guest sheet')
            ->assertDontSee('Check in guest');

        $reservation->update(['checked_in_at' => now()]);

        $this->get(route('admin.reservations.show', $reservation->fresh()))
            ->assertOk()
            ->assertDontSee('Download guest sheet')
            ->assertDontSee('Check in guest')
            ->assertSee('Check out early');

        $reservation->update([
            'status' => ReservationStatus::Completed->value,
            'checked_out_at' => now(),
        ]);

        $this->get(route('admin.reservations.show', $reservation->fresh()))
            ->assertOk()
            ->assertDontSee('Download guest sheet')
            ->assertDontSee('Check in guest')
            ->assertDontSee('Check out early');
    }

    public function test_admin_can_upload_disable_and_reactivate_verified_qr(): void
    {
        $this->actingAs($this->admin)->post(route('admin.payment-destinations.store'), [
            'method' => 'gcash', 'account_name' => 'Test only', 'account_number' => 'TEST',
            'verified_account' => 1, 'qr' => UploadedFile::fake()->image('qr.png'),
        ])->assertSessionHas('success');
        $new = PaymentDestination::latest('id')->first();
        $this->actingAs($this->customer)->get(route('payment-destinations.image', $new))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->get(route('payment-destinations.image', [$new, 'download' => 1]))
            ->assertDownload('villa-mercedes-gcash-qr.png');
        $this->get(route('customer.grouped-booking.workspace'))->assertOk()->assertSee('qr_download_url');
        $this->actingAs($this->admin)->patch(route('admin.payment-destinations.toggle', $new), ['is_active' => 0])->assertSessionHas('success');
        $this->assertFalse($new->fresh()->is_active);
        $this->actingAs($this->customer)->get(route('payment-destinations.image', [$new, 'download' => 1]))->assertNotFound();
        $this->actingAs($this->admin);
        $this->patch(route('admin.payment-destinations.toggle', $new), ['is_active' => 1])->assertSessionHasErrors('verified_account');
        $this->patch(route('admin.payment-destinations.toggle', $new), ['is_active' => 1, 'verified_account' => 1])->assertSessionHas('success');
    }

    public function test_deposit_review_requires_explicit_account_reconciliation(): void
    {
        $reservation = $this->approved();
        $payment = $this->proof($reservation);
        $this->actingAs($this->admin)->patch(route('admin.payments.verify', $payment))->assertSessionHasErrors('funds_received');
        $this->patch(route('admin.payments.reject', $payment), ['reason' => 'Unclear image'])->assertSessionHasErrors('funds_not_received');
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->patch(route('admin.payments.verify', $payment), ['funds_received' => 1])->assertSessionHas('success');
        $this->assertSame(ReservationStatus::Approved, $reservation->fresh()->status);
        $this->assertTrue($reservation->fresh()->canShowArrivalPass());
    }

    public function test_approval_can_be_refused_while_a_withdrawal_request_waits(): void
    {
        $reservation = $this->booking();
        app(\App\Services\Customer\CustomerReservationService::class)->requestCancellation($this->customer, $reservation, 'Change of plans.');
        $this->expectException(InvalidReservationTransitionException::class);
        app(ReservationService::class)->changeStatus($reservation, ReservationStatus::Approved, $this->admin);
    }

    public function test_approval_does_not_wait_for_the_deposit(): void
    {
        $reservation = $this->booking();
        app(ReservationService::class)->changeStatus($reservation, ReservationStatus::Approved, $this->admin);
        $reservation->refresh();
        $this->assertSame(ReservationStatus::Approved, $reservation->status);
        $this->assertSame(0.0, $reservation->getVerifiedTotalPaid());
        $this->assertTrue($reservation->canAcceptGuestPayment(), 'The guest is told to pay, not asked to pay first.');
    }

    public function test_top_up_reopens_only_a_shortfall_and_preserves_account_snapshot(): void
    {
        $reservation = $this->approved();
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

    public function test_rescheduling_an_unanswered_request_requotes_without_restarting_its_window(): void
    {
        $reservation = $this->booking();
        $deadline = $reservation->deposit_due_at->toDateTimeString();
        app(ReservationService::class)->reschedule($reservation, $this->room, now()->addDays(7)->setTime(14, 0),
            now()->addDays(9)->setTime(12, 0), $this->customer, 2);
        $this->assertSame('3000.01', $reservation->fresh()->deposit_required);
        $this->assertSame($deadline, $reservation->fresh()->deposit_due_at->toDateTimeString());
    }

    public function test_deposit_proof_requires_only_amount_destination_and_proof(): void
    {
        $this->freezeTime();
        $reservation = $this->approved();
        $response = $this->actingAs($this->customer)->post(route('customer.payments.store', $reservation), [
            'amount' => 1500.01, 'payment_method' => 'gcash', 'payment_destination_id' => $this->destination->id,
            'proof' => UploadedFile::fake()->image('proof.png'),
        ]);
        $response
            ->assertRedirect(route('customer.reservations.index', ['tab' => 'upcoming']))
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'The admin will review the transfer'));
        $this->assertDatabaseHas('payments', [
            'reservation_id' => $reservation->id,
            'reference_number' => null,
            'payment_date' => now()->toDateString(),
            'amount' => 1500.01,
            'source' => 'customer_deposit',
        ]);
        $this->get(route('customer.reservations.index', ['tab' => 'upcoming']))
            ->assertOk()
            ->assertSeeInOrder([
                'Verified paid',
                '0.00',
                'Under review',
                '1,500.01',
                'Balance if verified',
                '1,500.00',
            ])
            ->assertSee('Receipt awaiting verification');
    }

    public function test_retiring_an_account_does_not_discard_proof_of_an_existing_transfer(): void
    {
        $reservation = $this->approved();
        $this->travel(1)->minutes();
        $this->destination->update(['is_active' => false]);
        $payment = $this->proof($reservation);
        $this->assertSame('customer_deposit', $payment->source);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->actingAs($this->customer)->get(route('customer.payments.index', $reservation))->assertOk()->assertDontSee('Download QR');
    }

    public function test_other_customer_cannot_upload_proof_or_record_staff_payments(): void
    {
        $reservation = $this->approved();
        $other = User::factory()->create();
        $this->actingAs($other)->post(route('customer.payments.store', $reservation), [
            'amount' => 1500.01, 'payment_method' => 'gcash', 'payment_destination_id' => $this->destination->id,
            'proof' => UploadedFile::fake()->image('proof.png'),
        ])->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }
}
