<?php

namespace Tests\Feature\Http;

use App\Enums\GuestIdType;
use App\Enums\PaymentStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\Reservation\ReservationCreatedMail;
use App\Notifications\Reservation\ReservationCreatedNotification;
use App\Notifications\Reservation\ReservationStatusChangedMail;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArrivalPassTest extends TestCase
{
    use RefreshDatabase;

    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);

        $this->reservation = Reservation::factory()->create([
            'user_id' => $customer->id,
            'facility_id' => Facility::factory()->create(['is_active' => true])->id,
            'status' => ReservationStatus::Approved->value,
            'reservation_code' => 'VM-20260906-AB12CD',
            'check_in' => now()->addDays(3)->setTime(14, 0),
            'check_out' => now()->addDays(5)->setTime(12, 0),
        ]);
    }

    /**
     * The state the gate exists for: staff verified the proof, and this is what
     * the guest is left holding.
     */
    private function verifiedDeposit(float $amount = 1000, float $required = 1000): Reservation
    {
        $this->reservation->update(['deposit_required' => $required]);

        Payment::factory()->create([
            'reservation_id' => $this->reservation->id,
            'amount' => $amount,
            'status' => PaymentStatus::Verified->value,
        ]);

        return $this->reservation->fresh();
    }

    public function test_a_guest_can_open_a_signed_arrival_pass_without_signing_in(): void
    {
        $reservation = $this->verifiedDeposit();

        $this->get($reservation->arrivalPassUrl())
            ->assertOk()
            ->assertSee('Show this at the desk')
            ->assertSee('VM-20260906-AB12CD')
            ->assertSee('AB12CD')
            ->assertSee($reservation->guestName())
            ->assertSee($reservation->facility->name)
            ->assertSee('Status:')
            ->assertSee('Confirmed');
    }

    /**
     * A saved pass link outlives the booking's state, so the page has to answer
     * for every status — and the answer for a request that is still waiting is
     * not a slip the desk would have to disbelieve.
     */
    public function test_a_pending_request_with_a_deposit_is_told_the_deposit_is_not_verified(): void
    {
        $this->reservation->update([
            'status' => ReservationStatus::Pending->value,
            'deposit_required' => 1000,
        ]);

        $this->get($this->reservation->fresh()->arrivalPassUrl())
            ->assertOk()
            ->assertSee('Not confirmed yet')
            ->assertSee('the 50% deposit QR and proof upload open then')
            ->assertDontSee('Show this at the desk')
            ->assertDontSee('VM-20260906-AB12CD');
    }

    public function test_a_pending_request_without_a_deposit_is_told_it_is_still_with_staff(): void
    {
        $this->reservation->update([
            'status' => ReservationStatus::Pending->value,
            'guest_valid_id' => GuestIdType::PhilSys->value,
        ]);

        $this->get($this->reservation->fresh()->arrivalPassUrl())
            ->assertOk()
            ->assertSee('Waiting for the resort')
            ->assertSee('needs a staff quotation and approval')
            ->assertDontSee('Show this at the desk')
            ->assertDontSee('VM-20260906-AB12CD')
            // Guest details belong to the pass, and there is no pass yet.
            ->assertDontSee('Valid ID type');
    }

    /**
     * Confirmation alone stopped earning a pass: a record with no deposit — a
     * walk-in, a booking made before the deposit workflow — is confirmed without
     * any money having moved, and the desk cannot tell the two apart from a slip.
     */
    public function test_a_confirmed_booking_without_a_verified_payment_shows_no_pass(): void
    {
        $this->reservation->update(['status' => ReservationStatus::Approved->value]);

        $this->get($this->reservation->fresh()->arrivalPassUrl())
            ->assertOk()
            ->assertSee('Payment not recorded yet')
            ->assertDontSee('Show this at the desk')
            ->assertDontSee('VM-20260906-AB12CD');
    }

    public function test_a_confirmed_booking_with_a_short_deposit_shows_no_pass(): void
    {
        $reservation = $this->verifiedDeposit(amount: 400, required: 1000);

        $this->get($reservation->arrivalPassUrl())
            ->assertOk()
            ->assertSee('Payment not recorded yet')
            ->assertDontSee('Show this at the desk');
    }

    public function test_a_cancelled_booking_has_no_pass_document(): void
    {
        $this->verifiedDeposit();

        $this->reservation->update(['status' => ReservationStatus::Cancelled->value]);

        $this->get($this->reservation->fresh()->arrivalPassUrl())
            ->assertOk()
            ->assertSee('Cancelled')
            ->assertSee('no active check in pass')
            ->assertDontSee('Show this at the desk')
            ->assertDontSee('VM-20260906-AB12CD');
    }

    public function test_an_unsigned_arrival_pass_is_rejected(): void
    {
        $this->get(route('reservations.arrival-pass', $this->reservation))
            ->assertForbidden();
    }

    public function test_an_invalid_signature_is_rejected(): void
    {
        $this->get(route('reservations.arrival-pass', [
            'reservation' => $this->reservation->reservation_code,
            'signature' => 'not-a-real-signature',
        ]))->assertForbidden();
    }

    public function test_customer_booking_page_offers_the_arrival_pass_once_it_is_paid(): void
    {
        // The record answers one question at a time, so the code and the printable
        // pass live on its Arrival facet rather than in the middle of the stay.
        $reservation = $this->verifiedDeposit();

        $this->actingAs($reservation->user)
            ->get(route('customer.reservations.show', [
                'reservation' => $reservation,
                'view' => 'arrival',
            ]))
            ->assertOk()
            ->assertSee('Show this at the front desk')
            ->assertSee('Open check in pass');
    }

    public function test_customer_booking_page_explains_the_missing_pass_before_it_is_paid(): void
    {
        $this->reservation->update(['status' => ReservationStatus::Approved->value, 'deposit_required' => 1000]);

        $this->actingAs($this->reservation->user)
            ->get(route('customer.reservations.show', [
                'reservation' => $this->reservation,
                'view' => 'arrival',
            ]))
            ->assertOk()
            ->assertSee('Payment not recorded yet')
            ->assertDontSee('Show this at the front desk')
            ->assertDontSee('Open check in pass');
    }

    /**
     * The card on the guest's own reservations list carries the pass link, so it
     * answers to the same gate as the Arrival tab — a booking that has not been
     * paid for is not advertised as a pass the guest could already show.
     */
    public function test_the_reservations_list_offers_no_pass_before_payment(): void
    {
        $this->reservation->update(['status' => ReservationStatus::Approved->value, 'deposit_required' => 1000]);

        $this->actingAs($this->reservation->user)
            ->get(route('customer.reservations.index'))
            ->assertOk()
            ->assertSee($this->reservation->reservation_code)
            ->assertDontSee('Check in pass');

        $this->verifiedDeposit(amount: 1000, required: 1000);

        $this->actingAs($this->reservation->user)
            ->get(route('customer.reservations.index'))
            ->assertOk()
            ->assertSee('Check in pass');
    }

    public function test_online_guest_notifications_include_mail(): void
    {
        $created = new ReservationCreatedMail($this->reservation);
        $this->assertSame(['mail'], $created->via($this->reservation->user));

        $approved = new ReservationStatusChangedMail($this->reservation);
        $this->assertSame(['mail'], $approved->via($this->reservation->user));
    }

    public function test_admin_and_walk_in_notifications_do_not_email_staff(): void
    {
        $adminNotice = new ReservationCreatedNotification($this->reservation, true);
        $this->assertSame(['database'], $adminNotice->via($this->reservation->user));

        $this->reservation->update(['reservation_source' => ReservationSource::WalkIn->value]);
        $walkIn = new ReservationCreatedNotification($this->reservation->fresh(), false);
        $this->assertSame(['database'], $walkIn->via($this->reservation->user));
    }
}
