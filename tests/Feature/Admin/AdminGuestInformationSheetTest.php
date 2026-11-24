<?php

namespace Tests\Feature\Admin;

use App\Enums\GuestGender;
use App\Enums\GuestIdType;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGuestInformationSheetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Administrator->value);

        $this->customer = User::factory()->create([
            'name' => 'Maria Santos',
            'email' => 'maria.santos@example.com',
            'phone' => '09171234567',
            'gender' => GuestGender::Female,
            'address' => 'Ilagan, Isabela',
            'nationality' => 'Filipino',
            'valid_id_type' => GuestIdType::PhilSys,
            'company' => 'Villa Mercedes Tours',
        ]);
        $this->customer->assignRole(Role::Customer->value);

        $this->facility = Facility::firstOrFail();
    }

    public function test_admin_can_download_a_filled_sheet_for_an_approved_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved,
            'reservation_code' => 'VM-20260917-SHEET01',
            'check_in' => '2026-10-10 14:00:00',
            'guest_name' => 'Maria Santos',
            'guest_place_of_origin' => 'Ilagan, Isabela',
            'guest_nationality' => 'Filipino',
            'guest_company' => 'Villa Mercedes Tours',
            'guest_valid_id' => GuestIdType::PhilSys,
            'guest_gender' => GuestGender::Female,
            'guest_phone' => '09171234567',
            'guest_email' => 'maria.santos@example.com',
            'other_guest_names' => [
                ['name' => 'Ana Santos', 'valid_id' => null],
                ['name' => 'Lito Santos', 'valid_id' => null],
            ],
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reservations.guest-information-sheet', $reservation));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition', 'attachment; filename=guest-information-sheet-'.$reservation->reservation_code.'.pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_customer_cannot_download_the_admin_sheet(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved,
        ]);

        $response = $this->actingAs($this->customer)->get(route('admin.reservations.guest-information-sheet', $reservation));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_open_the_same_sheet_inline_for_printing(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved,
            'reservation_code' => 'VM-20260917-PRINT1',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reservations.guest-information-sheet', [
            'reservation' => $reservation,
            'print' => 1,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition', 'inline; filename=guest-information-sheet-'.$reservation->reservation_code.'.pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertNotNull($reservation->fresh()->guest_sheet_control_number);
    }

    public function test_sheet_is_not_available_for_unapproved_reservations(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Pending,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reservations.guest-information-sheet', $reservation));

        $response->assertNotFound();
    }
}
