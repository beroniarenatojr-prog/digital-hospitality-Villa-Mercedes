<?php

namespace Tests\Feature;

use App\Enums\FacilityStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Exceptions\Payment\InvalidPaymentAmountException;
use App\Exceptions\Payment\PaymentNotEligibleException;
use App\Exceptions\Reservation\InvalidReservationTransitionException;
use App\Exceptions\Reservation\ReservationConflictException;
use App\Models\EmailOtpVerification;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\SwimmingSession;
use App\Models\User;
use App\Services\Admin\FacilityManagementService;
use App\Services\AvailabilityService;
use App\Services\PaymentService;
use App\Services\PricingService;
use App\Services\ReservationService;
use Database\Seeders\FacilityTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReviewRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Http::preventStrayRequests();
        $this->seed([RoleSeeder::class, FacilityTypeSeeder::class]);
        $this->customer = User::factory()->create();
        $this->customer->assignRole('Customer');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Administrator');
    }

    private function facility(string $slug = 'room', bool $withRate = true): Facility
    {
        $facility = Facility::factory()->create([
            'facility_type_id' => FacilityType::where('slug', $slug)->firstOrFail()->id,
            'status' => FacilityStatus::Available,
            'capacity' => 4,
            'max_capacity' => 6,
            'is_active' => true,
        ]);
        if ($withRate) {
            $facility->rates()->create(['name' => 'Base', 'type' => 'base', 'amount' => 3000, 'unit' => 'per night', 'is_active' => true]);
        }

        return $facility;
    }

    private function reservation(Facility $facility, array $overrides = []): Reservation
    {
        return Reservation::factory()->create(array_merge([
            'user_id' => $this->customer->id,
            'facility_id' => $facility->id,
            'status' => ReservationStatus::Approved,
            'check_in' => now()->addDays(40)->setTime(14, 0),
            'check_out' => now()->addDays(43)->setTime(12, 0),
            'guest_count' => 2,
            'total_price' => 3000,
        ], $overrides));
    }

    private function pendingPayment(Reservation $reservation, float $amount = 3000): Payment
    {
        return Payment::factory()->create([
            'reservation_id' => $reservation->id,
            'amount' => $amount,
            'status' => PaymentStatus::Pending,
            'verified_at' => null,
            'verified_by' => null,
        ]);
    }

    public function test_admin_nightly_rate_charges_all_nights_and_extra_guests(): void
    {
        $facility = $this->facility(withRate: false);
        $this->actingAs($this->admin)->post(route('admin.rates.store', $facility), [
            'name' => 'Nightly rate', 'type' => 'base', 'amount' => 3000, 'unit' => 'per_night', 'is_active' => 1,
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('per night', $facility->rates()->firstOrFail()->unit);
        $facility->rates()->create(['name' => 'Extra guest', 'type' => 'extra_person', 'amount' => 100, 'unit' => 'per_person', 'is_active' => true]);

        $quote = app(PricingService::class)->calculate($facility, now()->addDays(40)->setTime(14, 0), now()->addDays(43)->setTime(12, 0), 0, 0, 5);
        $this->assertSame(900000, $quote->baseAmount);
        $this->assertSame(30000, $quote->extraPersonCharge);
        $this->assertSame(930000, $quote->totalAmount);
    }

    public function test_legacy_rate_units_work_before_migration_and_migration_preserves_reservation_totals(): void
    {
        $facility = $this->facility();
        $rate = $facility->rates()->firstOrFail();
        DB::table('rates')->where('id', $rate->id)->update(['unit' => 'per_night']);
        $reservation = $this->reservation($facility);
        $quote = app(PricingService::class)->calculate($facility, $reservation->check_in, $reservation->check_out, 0, 0, 2);
        $this->assertSame(900000, $quote->totalAmount);

        $migration = require database_path('migrations/2026_09_12_100000_normalize_rate_units.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseHas('rates', ['id' => $rate->id, 'unit' => 'per night']);
        $this->assertSame('3000.00', $reservation->fresh()->total_price);
        $this->actingAs($this->admin)->get(route('admin.rates.edit', $rate))->assertOk()->assertSee('value="per night" selected', false);
    }

    public function test_unknown_rate_units_are_rejected(): void
    {
        $facility = $this->facility(withRate: false);
        $this->actingAs($this->admin)->post(route('admin.rates.store', $facility), [
            'name' => 'Invalid rate', 'type' => 'base', 'amount' => 3000, 'unit' => 'per_nigth',
        ])->assertSessionHasErrors('unit');
        $this->assertSame(0, $facility->rates()->count());
    }

    public function test_sibling_packages_conflict_in_both_search_and_booking(): void
    {
        $room = $this->facility();
        $first = $this->facility('resort_package');
        $second = $this->facility('resort_package');
        foreach ([$first, $second] as $package) {
            $package->packageComponents()->create(['component_facility_id' => $room->id]);
        }
        $service = app(ReservationService::class);
        $from = now()->addDays(40)->setTime(14, 0);
        $to = now()->addDays(42)->setTime(12, 0);
        $service->createReservation($first, $this->customer, $from, $to, ReservationSource::Online, 2);
        $availability = app(AvailabilityService::class);
        $this->assertFalse($availability->isAvailable($second, $from, $to)->isAvailable);
        $availability->primeOverlaps([$second], $from, $to);
        $this->assertFalse($availability->isAvailable($second, $from, $to)->isAvailable);

        try {
            $service->createReservation($second, $this->customer, $from, $to, ReservationSource::Online, 2);
            $this->fail('A sibling package booked an occupied component.');
        } catch (ReservationConflictException) {
            $this->assertSame(1, Reservation::count());
        }
    }

    public function test_package_respects_maintenance_inactive_and_archived_components(): void
    {
        foreach (['maintenance', 'inactive', 'archived'] as $condition) {
            $room = $this->facility();
            $package = $this->facility('resort_package');
            $package->packageComponents()->create(['component_facility_id' => $room->id]);
            match ($condition) {
                'maintenance' => $room->update(['status' => FacilityStatus::Maintenance]),
                'inactive' => $room->update(['is_active' => false]),
                'archived' => $room->delete(),
            };
            $this->assertFalse(app(AvailabilityService::class)->isAvailable($package, now()->addDays(40), now()->addDays(42))->isAvailable, $condition);
        }
    }

    public function test_archived_parent_still_holds_its_reserved_components(): void
    {
        $room = $this->facility();
        $package = $this->facility('resort_package');
        $package->packageComponents()->create(['component_facility_id' => $room->id]);
        $reservation = $this->reservation($package);
        $package->delete();
        $this->assertFalse(app(AvailabilityService::class)->isAvailable($room, $reservation->check_in, $reservation->check_out)->isAvailable);
    }

    public function test_admin_cannot_nest_packages(): void
    {
        $package = $this->facility('resort_package');
        $child = $this->facility('resort_package');
        $this->expectException(\InvalidArgumentException::class);
        app(FacilityManagementService::class)->syncComponents($this->admin, $package, [$child->id]);
    }

    public function test_stale_approval_cannot_revive_a_rejected_booking(): void
    {
        $facility = $this->facility();
        $original = $this->reservation($facility, ['status' => ReservationStatus::Pending]);
        $staleCopy = $original->fresh();
        $service = app(ReservationService::class);
        $service->changeStatus($original, ReservationStatus::Rejected, $this->admin, 'Rejected first');
        $service->createReservation($facility, $this->customer, $original->check_in, $original->check_out, ReservationSource::Online, 2);
        try {
            $service->changeStatus($staleCopy, ReservationStatus::Approved, $this->admin);
            $this->fail('Stale approval revived a rejected booking.');
        } catch (InvalidReservationTransitionException) {
            $this->assertSame(ReservationStatus::Rejected, $original->fresh()->status);
            $this->assertSame(1, Reservation::whereIn('status', Reservation::blockingStatuses())->count());
        }
    }

    public function test_stale_reschedule_cannot_change_a_completed_reservation(): void
    {
        $facility = $this->facility();
        $reservation = $this->reservation($facility);
        $stale = $reservation->fresh();
        app(ReservationService::class)->changeStatus($reservation, ReservationStatus::Completed, $this->admin);
        try {
            app(ReservationService::class)->reschedule($stale, $facility, now()->addDays(50), now()->addDays(52), $this->admin, 2);
            $this->fail('Completed reservation was rescheduled.');
        } catch (InvalidReservationTransitionException) {
            $this->assertTrue($reservation->check_in->equalTo($reservation->fresh()->check_in));
            $this->assertSame(ReservationStatus::Completed, $reservation->fresh()->status);
        }
    }

    public function test_failed_check_out_does_not_stamp_departure(): void
    {
        $reservation = $this->reservation($this->facility(), ['status' => ReservationStatus::Cancelled, 'checked_in_at' => now()->subHour()]);
        try {
            app(ReservationService::class)->checkOut($reservation, $this->admin);
            $this->fail('Cancelled reservation was checked out.');
        } catch (InvalidReservationTransitionException) {
            $this->assertNull($reservation->fresh()->checked_out_at);
        }
    }

    public function test_withdrawal_requires_pending_status_and_guest_request(): void
    {
        foreach ([ReservationStatus::Pending, ReservationStatus::Approved] as $status) {
            $reservation = $this->reservation($this->facility(), ['status' => $status]);
            $this->actingAs($this->admin)->patch(route('admin.reservations.confirm-withdrawal', $reservation))->assertSessionHas('error');
            $this->assertSame($status, $reservation->fresh()->status);
        }
    }

    public function test_archived_facility_does_not_break_customer_admin_or_payment_history(): void
    {
        $facility = $this->facility();
        $reservation = $this->reservation($facility, ['status' => ReservationStatus::Completed]);
        $payment = $this->pendingPayment($reservation);
        $facility->delete();
        $this->actingAs($this->customer)->get(route('customer.reservations.show', $reservation))->assertOk()->assertSee($facility->name);
        $this->get(route('customer.payments.index', $reservation))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.reservations.show', $reservation))->assertOk()->assertSee('(archived)');
        $this->get(route('admin.payments.show', $payment))->assertOk();
    }

    public function test_pending_submissions_cannot_exceed_the_balance(): void
    {
        $reservation = $this->reservation($this->facility());
        $service = app(PaymentService::class);
        $service->createPayment($reservation, $this->customer, 2000, PaymentMethod::GCash);
        try {
            $service->createPayment($reservation, $this->customer, 2000, PaymentMethod::GCash);
            $this->fail('Pending payments exceeded the total.');
        } catch (InvalidPaymentAmountException) {
            $this->assertSame(2000.0, $reservation->fresh()->getPendingVerificationTotal());
        }
        $service->createPayment($reservation, $this->customer, 1000, PaymentMethod::GCash);
        $this->assertFalse($reservation->fresh()->canAcceptGuestPayment());
        $this->actingAs($this->customer)->get(route('customer.payments.index', $reservation))
            ->assertOk()
            ->assertSee('Awaiting verification')
            ->assertSee('No receipt attached')
            ->assertDontSee('Fully paid')
            ->assertDontSee('Paid online');
    }

    public function test_stale_loaded_payment_ledger_cannot_accept_a_second_full_payment(): void
    {
        $reservation = $this->reservation($this->facility())->load('payments');
        $service = app(PaymentService::class);
        $payment = $service->createPayment($reservation, $this->customer, 3000, PaymentMethod::GCash);
        $service->verifyPayment($payment, $this->admin);
        $this->expectException(PaymentNotEligibleException::class);
        $service->createPayment($reservation, $this->customer, 3000, PaymentMethod::GCash);
    }

    public function test_legacy_overpayment_requires_explicit_reconciliation_before_manual_verification(): void
    {
        $reservation = $this->reservation($this->facility());
        $first = $this->pendingPayment($reservation);
        $second = $this->pendingPayment($reservation);
        app(PaymentService::class)->verifyPayment($first, $this->admin);
        $this->actingAs($this->admin)->patch(route('admin.payments.verify', $second))->assertSessionHas('error');
        $this->assertSame(PaymentStatus::Pending, $second->fresh()->status);
        $this->assertSame(3000.0, $reservation->fresh()->getVerifiedTotalPaid());
        $this->get(route('admin.payments.show', $second))->assertOk()->assertSee('Overpayment reconciliation note');

        $this->patch(route('admin.payments.verify', $second), ['overpayment_note' => 'Confirmed duplicate funds received; reconcile PHP 3000 with guest.'])->assertSessionHas('success');
        $this->assertSame(3000.0, $reservation->fresh()->getOverpaymentAmount());
        $this->get(route('admin.reservations.show', $reservation))->assertOk()->assertSee('Overpayment requires reconciliation');
        $this->actingAs($this->customer)->get(route('customer.payments.index', $reservation))->assertOk()->assertSee('Overpayment requires reconciliation');
        $this->assertDatabaseHas('activity_log', ['subject_id' => $second->id, 'event' => 'payment_verified']);
    }

    public function test_gateway_confirmation_is_idempotent_and_preserves_actual_excess_funds(): void
    {
        $reservation = $this->reservation($this->facility());
        $payment = $this->pendingPayment($reservation);
        $payment->update(['reference_number' => 'checkout-regression']);
        $reservation->update(['total_price' => 2000]);
        $service = app(PaymentService::class);
        $service->verifyPaymentViaGateway($payment, 'checkout-regression');
        $service->verifyPaymentViaGateway($payment, 'checkout-regression');
        $this->assertSame(3000.0, $reservation->fresh()->getVerifiedTotalPaid());
        $this->assertSame(1000.0, $reservation->fresh()->getOverpaymentAmount());
        $this->assertSame(1, DB::table('activity_log')->where('subject_type', Payment::class)->where('subject_id', $payment->id)->where('event', 'payment_verified')->count());
    }

    public function test_guest_cannot_reset_another_registration_challenge(): void
    {
        $record = EmailOtpVerification::create([
            'email' => 'other-guest@example.test', 'otp_hash' => password_hash('123456', PASSWORD_BCRYPT),
            'expires_at' => now()->addMinutes(10), 'last_sent_at' => now(),
            'attempt_count' => 0, 'send_count' => 1,
        ]);
        $this->post(route('register.otp.reset'), ['email' => $record->email])->assertRedirect(route('register'));
        $this->assertDatabaseHas('email_otp_verifications', ['id' => $record->id, 'otp_hash' => $record->otp_hash]);
    }

    public function test_admin_cottage_preview_and_reschedule_use_the_selected_session(): void
    {
        $cottage = $this->facility('cottage');
        $cottage->rates()->update(['amount' => 500, 'unit' => 'per session']);
        $session = SwimmingSession::create([
            'name' => 'Day session', 'slug' => 'day-regression', 'starts_at' => '08:00', 'ends_at' => '17:00',
            'adult_entrance_fee' => 100, 'cottage_fee' => 750, 'children_allowed' => true, 'is_active' => true,
        ]);
        [$from, $to] = $session->periodFor(now()->addDays(40)->toDateString());
        $reservation = app(ReservationService::class)->createReservation($cottage, $this->customer, $from, $to, ReservationSource::Online, 2, ['swimming_session_id' => $session->id]);
        $payload = [
            'facility_id' => $cottage->id, 'visit_date' => now()->addDays(42)->toDateString(), 'swimming_session_id' => $session->id,
            'check_in' => now()->addDays(42)->setTime(14, 0)->toDateTimeString(),
            'check_out' => now()->addDays(42)->setTime(18, 0)->toDateTimeString(), 'guestCount' => 2,
            'early_check_in_hours' => 3, 'late_check_out_hours' => 3,
        ];
        $this->actingAs($this->admin)->get(route('admin.reservations.show', $reservation))
            ->assertOk()->assertSee('Swimming session')->assertDontSee('Can they stay longer?');
        $this->postJson(route('admin.reservations.calculate-reschedule', $reservation), $payload)->assertOk()->assertJsonPath('breakdown.total_amount', 750);
        $this->patch(route('admin.reservations.reschedule', $reservation), $payload)->assertSessionHas('error');
        $this->assertTrue($from->equalTo($reservation->fresh()->check_in));
        $practice = app(\App\Services\BookingPracticeService::class);
        [$newFrom, $newTo] = $session->periodFor($payload['visit_date']);
        $practice->proposeChange($reservation, $this->admin, [
            'facility_id' => $cottage->id, 'check_in' => $newFrom->toDateTimeString(),
            'check_out' => $newTo->toDateTimeString(), 'guest_count' => 2, 'swimming_session_id' => $session->id,
        ]);
        $practice->acceptChange($reservation, $this->customer, $reservation->fresh()->booking_workflow['change']['quote_cents']);
        $practice->confirmChange($reservation, $this->admin);
        $reservation->refresh();
        $this->assertSame(['08:00', '17:00', '750.00', 0, 0, $session->id], [
            $reservation->check_in->format('H:i'), $reservation->check_out->format('H:i'), $reservation->total_price,
            $reservation->early_check_in_hours, $reservation->late_check_out_hours, $reservation->swimming_session_id,
        ]);
    }

    public function test_admin_cottage_reschedule_without_session_is_rejected(): void
    {
        $cottage = $this->facility('cottage');
        $reservation = $this->reservation($cottage);
        $this->actingAs($this->admin)->patch(route('admin.reservations.reschedule', $reservation), [
            'facility_id' => $cottage->id, 'check_in' => now()->addDays(45), 'check_out' => now()->addDays(46), 'guestCount' => 2,
        ])->assertSessionHasErrors('swimming_session_id');
        $this->assertTrue($reservation->check_in->equalTo($reservation->fresh()->check_in));
    }

    public function test_account_with_history_stays_signed_in_after_controlled_deletion_rejection(): void
    {
        $reservation = $this->reservation($this->facility());
        $this->actingAs($this->customer)->from(route('profile.edit'))->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect(route('profile.edit'))->assertSessionHasErrorsIn('userDeletion', 'account');
        $this->assertAuthenticatedAs($this->customer);
        $this->assertModelExists($reservation);
        $this->assertModelExists($this->customer);
        $this->get(route('profile.edit'))->assertOk()->assertSee('This account has reservation or other retained history');
    }
}
