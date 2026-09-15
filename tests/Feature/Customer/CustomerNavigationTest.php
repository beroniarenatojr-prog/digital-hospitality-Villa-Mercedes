<?php

namespace Tests\Feature\Customer;

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
            ->assertSee('Home')
            ->assertSee('Login')
            ->assertSee('Register')
            ->assertSee(route('register'), false)
            ->assertSeeInOrder(['Home', 'Services', 'Resort & Pools', 'Gallery', 'FAQs', 'Reviews', 'About', 'Login', 'Register'])
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

    public function test_account_pages_use_reservations_as_home_base(): void
    {
        $this->actingAs($this->customer())
            ->get(route('customer.reservations.index'))
            ->assertOk()
            ->assertSee(config('resort.name').' — My reservations')
            ->assertSee('Browse the resort')
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
        $selectionUrl = route('customer.reservations.new', [
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
            ->assertDontSee(config('resort.name').' — home');
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

    public function test_profile_and_inquiries_use_account_navigation(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee(config('resort.name').' — My reservations')
            ->assertSee('Browse the resort');

        $this->actingAs($customer)
            ->get(route('customer.inquiries.index'))
            ->assertOk()
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
