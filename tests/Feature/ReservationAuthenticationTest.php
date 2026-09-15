<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\PackageComponent;
use App\Models\Rate;
use App\Models\User;
use Database\Seeders\CottageSeeder;
use Database\Seeders\FacilityTypeSeeder;
use Database\Seeders\ResortServicesSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, FacilityTypeSeeder::class, CottageSeeder::class, ResortServicesSeeder::class]);
    }

    private function room(string $type = 'room'): Facility
    {
        $facility = Facility::factory()->create([
            'facility_type_id' => FacilityType::where('slug', $type)->firstOrFail()->id,
            'is_active' => true, 'status' => 'available', 'capacity' => 4, 'max_capacity' => 6,
        ]);
        Rate::factory()->create(['facility_id' => $facility->id, 'type' => 'base', 'amount' => 2500, 'is_active' => true]);

        return $facility;
    }

    private function customer(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Customer->value);

        return $user;
    }

    public function test_public_availability_never_requires_login_or_creates_a_booking(): void
    {
        $room = $this->room();
        $this->get(route('facilities.availability', $room))->assertOk()
            ->assertSee('data-reservation-auth', false)->assertDontSee('Guest information sheet');
        $this->postJson(route('facilities.availability.calculate', $room), [
            'check_in' => now()->addDays(10)->toDateTimeString(),
            'check_out' => now()->addDays(11)->toDateTimeString(), 'guestCount' => 2,
        ])->assertOk()->assertJsonPath('success', true);
        $this->getJson(route('resort.cottages.availability', ['date' => now()->addDays(10)->toDateString(), 'session' => 'day']))->assertOk();
        $this->getJson(route('availability.search', [
            'service_type' => 'room',
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(11)->toDateString(),
            'guests' => 2,
        ]))->assertOk()->assertJsonPath('available', true);
        $this->assertDatabaseCount('reservations', 0);
        $this->assertGuest();
    }

    public function test_guests_cannot_access_reservation_or_retired_magic_link_endpoints(): void
    {
        $room = $this->room();
        $url = route('customer.booking.create', ['facility' => $room, 'guests' => 3]);
        $this->get($url)->assertRedirect(route('login'))->assertSessionHas('url.intended', $url);
        $this->postJson(route('customer.booking.calculate', $room), [])->assertUnauthorized();
        $this->postJson(route('customer.reservations.store'), [])->assertUnauthorized();
        $this->postJson(route('customer.booking.magic-link.send', $room), [])->assertUnauthorized();
        $this->getJson(route('customer.booking.magic-link.consume', 'old-draft'))->assertUnauthorized();
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_login_returns_to_each_service_with_its_selection(): void
    {
        $room = $this->room();
        $hall = $this->room('function_hall');
        $package = $this->room('resort_package');
        PackageComponent::create(['package_facility_id' => $package->id, 'component_facility_id' => $room->id]);
        $cottage = Facility::where('slug', 'cottage-01')->firstOrFail();
        $customer = $this->customer();

        foreach ([$room, $hall, $package, $cottage] as $facility) {
            $selection = $facility->isCottage()
                ? ['date' => now()->addDays(10)->toDateString(), 'session' => 'night', 'guests' => 10]
                : ['arrival' => now()->addDays(10)->setTime(14, 0)->format('Y-m-d H:i'), 'departure' => now()->addDays(11)->setTime(12, 0)->format('Y-m-d H:i'), 'guests' => 3, 'availability_checked' => 1];
            $destination = $this->postJson(route('reservation.intent'), ['facility_id' => $facility->id, ...$selection])->assertOk()->json('redirect');
            $this->assertStringContainsString('/reservation/'.$facility->id, $destination);
            $this->postJson(route('login'), ['email' => $customer->email, 'password' => 'wrong'])->assertUnprocessable()
                ->assertSessionHas('url.intended', $destination);
            $this->postJson(route('login'), ['email' => $customer->email, 'password' => 'password'])->assertOk()->assertJsonPath('redirect', $destination);
            $response = $this->get($destination)->assertOk()->assertSee($facility->name);
            if (! $facility->isCottage()) {
                $response->assertViewHas('prefill', fn ($prefill) => $prefill['check_in'] === $selection['arrival'] && $prefill['check_out'] === $selection['departure'] && $prefill['guest_count'] === 3 && $prefill['availability_checked'] === true);
            } else {
                $response->assertViewHas('selectedSession', fn ($session) => $session->slug === 'night')->assertViewHas('visitDate', $selection['date']);
            }
            $this->post(route('logout'));
        }
    }

    public function test_signup_keeps_selection_through_email_verification(): void
    {
        $cottage = Facility::where('slug', 'cottage-03')->firstOrFail();
        $destination = $this->postJson(route('reservation.intent'), [
            'facility_id' => $cottage->id, 'date' => now()->addDays(8)->toDateString(), 'session' => 'day', 'guests' => 12,
        ])->assertOk()->json('redirect');
        $this->get(route('register'))->assertOk()->assertSee($cottage->name);
        $this->verifyRegistrationEmail('newguest@example.com');
        $this->post(route('register'), $this->registrationPayload('newguest@example.com', [
            'name' => 'New Guest',
        ]))->assertRedirect($destination);
        $this->assertAuthenticated();
        $this->get($destination)->assertOk()->assertSee($cottage->name);
    }

    public function test_intent_cannot_supply_an_external_redirect_or_price(): void
    {
        $room = $this->room();
        $this->postJson(route('reservation.intent'), [
            'facility_id' => $room->id, 'redirect' => 'https://example.com', 'total_price' => 1,
        ])->assertOk()->assertJsonPath('redirect', route('customer.booking.create', $room));
        $room->update(['is_active' => false]);
        $this->postJson(route('reservation.intent'), ['facility_id' => $room->id])->assertNotFound();
    }
}
