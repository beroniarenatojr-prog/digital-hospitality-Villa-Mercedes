<?php

namespace Tests\Feature\Http;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Facility;
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

    public function test_a_guest_can_open_a_signed_arrival_pass_without_signing_in(): void
    {
        $this->get($this->reservation->arrivalPassUrl())
            ->assertOk()
            ->assertSee('Show this at the desk')
            ->assertSee('VM-20260906-AB12CD')
            ->assertSee('AB12CD')
            ->assertSee($this->reservation->guestName())
            ->assertSee($this->reservation->facility->name)
            ->assertSee('Status:')
            ->assertSee('Confirmed');
    }

    public function test_pending_arrival_pass_is_labelled_as_awaiting_approval(): void
    {
        $this->reservation->update([
            'status' => ReservationStatus::Pending->value,
            'guest_valid_id' => \App\Enums\GuestIdType::PhilSys->value,
        ]);

        $this->get($this->reservation->fresh()->arrivalPassUrl())
            ->assertOk()
            ->assertSee('Awaiting approval')
            ->assertSee('This is a reservation request, not a confirmed booking')
            ->assertSee('Pending — awaiting approval')
            ->assertSee('Valid ID type')
            ->assertSee('PhilSys National ID');
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

    public function test_customer_booking_page_offers_the_arrival_pass(): void
    {
        $this->actingAs($this->reservation->user)
            ->get(route('customer.reservations.show', $this->reservation))
            ->assertOk()
            ->assertSee('Show this at the front desk')
            ->assertSee('Open arrival pass');
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
