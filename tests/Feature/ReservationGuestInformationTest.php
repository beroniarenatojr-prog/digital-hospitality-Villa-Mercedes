<?php

namespace Tests\Feature;

use App\Enums\FacilityType as FacilityTypeEnum;
use App\Enums\GuestGender;
use App\Enums\GuestIdType;
use App\Enums\RateType;
use App\Enums\ReservationSource;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Rate;
use App\Models\Reservation;
use App\Models\User;
use App\Services\PricingService;
use Database\Seeders\FacilityTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationGuestInformationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(FacilityTypeSeeder::class);
    }

    public function test_online_booking_requires_the_guest_information_sheet(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);
        $facility = $this->createBookableFacility();

        $this->actingAs($customer)
            ->from(route('customer.booking.create', $facility))
            ->post(route('customer.reservations.store'), [
                'facility_id' => $facility->id,
                'check_in' => now()->addDays(5)->format('Y-m-d\TH:i'),
                'check_out' => now()->addDays(6)->format('Y-m-d\TH:i'),
                'guestCount' => 2,
            ])
            ->assertRedirect(route('customer.booking.create', $facility))
            ->assertSessionHasErrors([
                'guest_name',
                'guest_place_of_origin',
                'guest_nationality',
                'guest_valid_id',
                'guest_phone',
                'guest_email',
            ]);

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_online_and_walk_in_store_the_same_guest_sheet_fields(): void
    {
        $customer = User::factory()->create([
            'gender' => GuestGender::Female,
        ]);
        $customer->assignRole(Role::Customer->value);
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);
        $facility = $this->createBookableFacility();

        $sheet = $this->guestInformationPayload([
            'guest_name' => 'Maria Santos',
            'other_guest_names' => ['Pedro Santos', '', '', '', ''],
        ]);

        $stay = [
            'facility_id' => $facility->id,
            'check_in' => now()->addDays(8)->format('Y-m-d\TH:i'),
            'check_out' => now()->addDays(9)->format('Y-m-d\TH:i'),
            'guestCount' => 2,
        ];

        $this->actingAs($customer)
            ->post(route('customer.reservations.store'), $stay + $sheet)
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.reservations.store'), array_merge($stay, $sheet, [
                'check_in' => now()->addDays(18)->format('Y-m-d\TH:i'),
                'check_out' => now()->addDays(19)->format('Y-m-d\TH:i'),
            ]))
            ->assertRedirect();

        $online = Reservation::query()->where('reservation_source', ReservationSource::Online->value)->first();
        $walkIn = Reservation::query()->where('reservation_source', ReservationSource::WalkIn->value)->first();

        $this->assertNotNull($online);
        $this->assertNotNull($walkIn);

        foreach ([$online, $walkIn] as $reservation) {
            $this->assertSame('Maria Santos', $reservation->guest_name);
            $this->assertSame('Ilagan, Isabela', $reservation->guest_place_of_origin);
            $this->assertSame('Filipino', $reservation->guest_nationality);
            $this->assertSame('Villa Mercedes Tours', $reservation->guest_company);
            $this->assertSame(GuestIdType::PhilSys, $reservation->guest_valid_id);
            $this->assertSame(GuestGender::Female, $reservation->guest_gender);
            $this->assertSame('09171234567', $reservation->guest_phone);
            $this->assertSame('maria.santos@example.com', $reservation->guest_email);
            $this->assertSame(['Pedro Santos'], $reservation->otherGuestNames());
        }

        $this->assertNotNull($online->policies_accepted_at);
        $this->assertNull($walkIn->policies_accepted_at);
    }

    public function test_online_booking_stores_special_requests_and_requires_policies_agreement(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);
        $facility = $this->createBookableFacility();

        $stay = [
            'facility_id' => $facility->id,
            'check_in' => now()->addDays(5)->format('Y-m-d\TH:i'),
            'check_out' => now()->addDays(6)->format('Y-m-d\TH:i'),
            'guestCount' => 2,
        ];

        $this->actingAs($customer)
            ->from(route('customer.booking.create', $facility))
            ->post(route('customer.reservations.store'), $stay + $this->guestInformationPayload([
                'policies_accepted' => null,
            ]))
            ->assertRedirect(route('customer.booking.create', $facility))
            ->assertSessionHasErrors('policies_accepted');

        $this->assertDatabaseCount('reservations', 0);

        $this->actingAs($customer)
            ->post(route('customer.reservations.store'), $stay + $this->guestInformationPayload([
                'special_requests' => 'Quiet room if possible. Anniversary dinner.',
            ]))
            ->assertRedirect();

        $reservation = Reservation::query()->first();
        $this->assertNotNull($reservation);
        $this->assertSame('Quiet room if possible. Anniversary dinner.', $reservation->special_requests);
        $this->assertNotNull($reservation->policies_accepted_at);
    }

    public function test_walk_in_can_add_other_guest_names_up_to_room_max_pax(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);
        $facility = $this->createBookableFacility(6, 10);

        $companions = [
            'Guest Two',
            'Guest Three',
            'Guest Four',
            'Guest Five',
            'Guest Six',
            'Guest Seven',
            'Guest Eight',
            'Guest Nine',
            'Guest Ten',
        ];

        $this->actingAs($admin)
            ->post(route('admin.reservations.store'), [
                'facility_id' => $facility->id,
                'check_in' => now()->addDays(4)->format('Y-m-d\TH:i'),
                'check_out' => now()->addDays(5)->format('Y-m-d\TH:i'),
                'guestCount' => 2,
            ] + $this->guestInformationPayload([
                'other_guest_names' => $companions,
            ]))
            ->assertRedirect();

        $reservation = Reservation::query()->first();
        $this->assertNotNull($reservation);
        $this->assertSame(10, $reservation->guest_count);
        $this->assertSame($companions, $reservation->otherGuestNames());
    }

    public function test_walk_in_rejects_other_guest_names_beyond_room_max_pax(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);
        $facility = $this->createBookableFacility(6, 10);

        $tooMany = array_map(fn (int $i) => "Guest {$i}", range(2, 12));

        $this->actingAs($admin)
            ->from(route('admin.reservations.index'))
            ->post(route('admin.reservations.store'), [
                'facility_id' => $facility->id,
                'check_in' => now()->addDays(4)->format('Y-m-d\TH:i'),
                'check_out' => now()->addDays(5)->format('Y-m-d\TH:i'),
                'guestCount' => 10,
            ] + $this->guestInformationPayload([
                'other_guest_names' => $tooMany,
            ]))
            ->assertRedirect(route('admin.reservations.index'))
            ->assertSessionHasErrors('other_guest_names');

        $this->assertDatabaseCount('reservations', 0);
    }

    private function createBookableFacility(int $capacity = 2, int $maxCapacity = 4): Facility
    {
        $type = FacilityType::where('slug', FacilityTypeEnum::Room->value)->firstOrFail();

        $facility = Facility::factory()->create([
            'facility_type_id' => $type->id,
            'is_active' => true,
            'status' => 'available',
            'capacity' => $capacity,
            'max_capacity' => $maxCapacity,
        ]);

        Rate::factory()->create([
            'facility_id' => $facility->id,
            'type' => RateType::Base->value,
            'amount' => 2500,
            'is_active' => true,
        ]);

        Rate::factory()->create([
            'facility_id' => $facility->id,
            'type' => RateType::ExtraPerson->value,
            'amount' => 500,
            'is_active' => true,
        ]);

        Rate::factory()->create([
            'facility_id' => $facility->id,
            'name' => PricingService::EARLY_CHECK_IN_RATE_NAME,
            'type' => RateType::AdditionalCharge->value,
            'amount' => 200,
            'unit' => 'per hour',
            'is_active' => true,
        ]);

        Rate::factory()->create([
            'facility_id' => $facility->id,
            'name' => PricingService::LATE_CHECK_OUT_RATE_NAME,
            'type' => RateType::AdditionalCharge->value,
            'amount' => 300,
            'unit' => 'per hour',
            'is_active' => true,
        ]);

        return $facility;
    }
}
