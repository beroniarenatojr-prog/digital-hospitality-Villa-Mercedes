<?php

namespace Tests\Feature\Customer;

use App\Enums\Role;
use App\Models\Facility;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BookingMagicLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_retired_email_booking_links_cannot_bypass_authentication(): void
    {
        Notification::fake();
        $facility = Facility::factory()->create();
        $this->postJson(route('customer.booking.magic-link.send', $facility), [])->assertUnauthorized();
        $this->getJson(route('customer.booking.magic-link.consume', 'expired-draft'))->assertUnauthorized();
        Notification::assertNothingSent();
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_signed_in_customers_are_directed_away_from_retired_email_booking_actions(): void
    {
        Notification::fake();
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(Role::Customer->value);
        $facility = Facility::factory()->create();
        $this->actingAs($user)->postJson(route('customer.booking.magic-link.send', $facility), [])->assertGone();
        $this->getJson(route('customer.booking.magic-link.consume', 'expired-draft'))->assertGone();
        Notification::assertNothingSent();
        $this->assertDatabaseCount('reservations', 0);
        $this->assertAuthenticatedAs($user);
    }
}
