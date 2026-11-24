<?php

namespace Tests\Feature\Customer;

use App\Enums\FacilityStatus;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(Role::Customer->value);

        return $user;
    }

    public function test_guest_landing_page_keeps_marketing_home_nav(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(config('resort.name').' — home')
            ->assertSee(config('resort.short_name'))
            ->assertSee('Nature Farm &amp; Resort', false)
            ->assertSee('Home')
            ->assertSee('Login')
            ->assertSee('Register')
            ->assertSee(route('register'), false)
            ->assertSeeInOrder(['Home', 'Services', 'Resort & Pools', 'Gallery', 'About', 'Login', 'Register'])
            ->assertSee('Toggle navigation menu')
            ->assertDontSee('-translate-y-full', false)
            ->assertDontSee('Browse the resort');
    }

    public function test_signed_in_customer_keeps_marketing_nav_on_public_pages(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->get(route('home'))
            ->assertOk()
            ->assertSee(config('resort.name').' — home')
            ->assertSee('Home')
            ->assertSee('Gallery')
            ->assertSee('My reservations')
            ->assertDontSee('Browse the resort');

        $this->actingAs($customer)
            ->get(route('rooms'))
            ->assertOk()
            ->assertSee(config('resort.name').' — home')
            ->assertDontSee('Browse the resort');

        $this->actingAs($customer)
            ->get(route('reserve-space'))
            ->assertRedirect(route('rooms'));
    }

    public function test_customer_home_is_service_first_and_reservations_remain_reachable(): void
    {
        $this->actingAs($this->customer())
            ->get(route('customer.home'))
            ->assertOk()
            ->assertSee('What would you like to reserve?')
            ->assertSee('Step 1: Select category')
            ->assertSee('Rooms')
            ->assertSee('Cottages')
            ->assertSee('Resort packages')
            ->assertSee('Function halls')
            ->assertSee('See available spaces')
            ->assertDontSee('Start an inquiry')
            ->assertSee(route('customer.grouped-booking.workspace', ['type' => 'room']), false)
            ->assertSee(route('customer.reservations.index'), false)
            ->assertSee('aria-label="Account"', false)
            ->assertSee('account-navigation', false)
            ->assertDontSee(config('resort.name').' — home');
    }

    public function test_account_pages_use_reservations_as_home_base(): void
    {
        $this->actingAs($this->customer())
            ->get(route('customer.reservations.index'))
            ->assertOk()
            ->assertSee(config('resort.name').' — My reservations')
            ->assertSee(config('resort.short_name'))
            ->assertSee('Nature Farm &amp; Resort', false)
            ->assertSee('Browse the resort')
            ->assertSee('aria-label="Account"', false)
            ->assertSee('account-navigation', false)
            ->assertSee('My reservations')
            ->assertSee('New reservation')
            ->assertDontSee('My inquiries')
            ->assertDontSee(config('resort.name').' — home');
    }

    public function test_customer_facility_detail_keeps_account_navigation(): void
    {
        $facility = Facility::factory()->create(['is_active' => true]);
        $facility->facilityType->update(['name' => 'Room', 'slug' => 'room']);
        $facility->load('facilityType');

        $detailUrl = route('customer.facilities.show', [
            'facility' => $facility,
            'check_in' => '2026-10-12',
            'check_out' => '2026-10-13',
            'guests' => 2,
        ]);
        $selectionUrl = route('customer.grouped-booking.workspace', [
            'type' => $facility->facilityType->slug,
            'check_in' => '2026-10-12',
            'check_out' => '2026-10-13',
            'guests' => 2,
        ]);

        $this->actingAs($this->customer())
            ->get($detailUrl)
            ->assertOk()
            ->assertSee(config('resort.name').' — My reservations')
            ->assertSee('Browse the resort')
            ->assertSee(route('customer.reservations.index'), false)
            ->assertSee($selectionUrl)
            ->assertSee('My reservations')
            ->assertSee('Add to booking')
            ->assertSee(route('customer.grouped-booking.workspace', [
                'facility_id' => $facility->id,
                'check_in' => '2026-10-12',
                'check_out' => '2026-10-13',
                'guests' => 2,
            ]))
            ->assertDontSee(config('resort.name').' — home');
    }

    public function test_new_reservation_cards_open_account_details_before_booking(): void
    {
        // Bookable on purpose: these tests hand the picker dates, and a space that
        // cannot be booked for them is left out by the availability filter.
        $facility = Facility::factory()->create([
            'is_active' => true,
            'status' => FacilityStatus::Available->value,
        ]);
        $facility->facilityType->update(['name' => 'Room', 'slug' => 'room']);
        $facility->load('facilityType');
        $detailUrl = route('customer.facilities.show', [
            'facility' => $facility,
            'check_in' => '2026-10-12',
            'check_out' => '2026-10-13',
            'guests' => 2,
        ]);

        $this->actingAs($this->customer())
            ->get(route('customer.reservations.new', [
                'type' => 'room',
                'check_in' => '2026-10-12',
                'check_out' => '2026-10-13',
                'guests' => 2,
            ]))
            ->assertOk()
            ->assertSee('View details')
            ->assertSee($detailUrl)
            ->assertDontSee(route('customer.booking.create', $facility), false);
    }

    public function test_new_reservation_picker_filters_by_the_dates_it_carries(): void
    {
        $facility = Facility::factory()->create([
            'is_active' => true,
            'status' => FacilityStatus::Available->value,
        ]);
        $facility->facilityType->update(['name' => 'Room', 'slug' => 'room']);
        $facility->load('facilityType');

        $this->actingAs($this->customer())
            ->get(route('customer.reservations.new', [
                'type' => 'room',
                'check_in' => '2026-10-12',
                'check_out' => '2026-10-13',
                'guests' => 2,
            ]))
            ->assertOk()
            // The dates the guest brought are the filter, and the page says which
            // list it is showing rather than asking for them all over again.
            ->assertSee('Spaces free for your dates')
            ->assertSee('value="2026-10-12"', false)
            ->assertSee('value="2026-10-13"', false)
            ->assertSee('value="2"', false)
            // They can be adjusted right here...
            ->assertSee('name="check_in"', false)
            ->assertSee('name="check_out"', false)
            ->assertSee('name="guests"', false)
            // ...and they still travel to the space page, which forwards them on.
            ->assertSee(route('customer.facilities.show', [
                'facility' => $facility,
                'check_in' => '2026-10-12',
                'check_out' => '2026-10-13',
                'guests' => 2,
            ]));

        // A guest who has brought nothing is asked, not told — and the full
        // catalog is still what they see.
        $this->actingAs($this->customer())
            ->get(route('customer.reservations.new', ['type' => 'room']))
            ->assertOk()
            ->assertSee('Check what is free first')
            ->assertDontSee('Spaces free for your dates')
            ->assertSee($facility->name);
    }

    public function test_public_facility_detail_keeps_public_breadcrumbs(): void
    {
        $facility = Facility::factory()->create(['is_active' => true]);

        $this->get(route('facilities.show', $facility))
            ->assertOk()
            ->assertSee(route('home'), false)
            ->assertSee('Home')
            ->assertDontSee(route('customer.reservations.new', ['type' => $facility->facilityType->slug]), false);
    }

    public function test_profile_keeps_account_navigation(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee(config('resort.name').' — My reservations')
            ->assertSee('aria-label="Account"', false)
            ->assertSee('Browse the resort');

        $this->actingAs($customer)
            ->get(route('customer.reservations.index'))
            ->assertOk()
            ->assertSee('aria-label="Account"', false)
            ->assertSee('Browse the resort')
            ->assertDontSee(config('resort.name').' — home');
    }

    public function test_administrators_keep_marketing_nav_on_the_public_site(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);

        $this->actingAs($admin)
            ->get(route('home'))
            ->assertOk()
            ->assertSee(config('resort.name').' — home')
            ->assertSee('Home')
            ->assertDontSee('Browse the resort');
    }
}
