<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A 419 used to hand back the framework's own page: "419 | PAGE EXPIRED", in
 * system type, with no way to continue and nothing saying which of the app's
 * screens the person had been on. Someone whose session ended mid-form read a
 * dead end, and the only clue about what to do next was the status code.
 *
 * It is now the app's own page, addressed to whoever hit it: it says the session
 * ended, that the submission was refused rather than half-applied, and which
 * door to sign in through.
 *
 * Signing out is deliberately not this page — see LogoutTest. Both logout routes
 * answer a dead token by signing the person out and sending them to the right
 * sign-in form, so nobody is ever shown this page for asking to leave.
 */
class SessionEndedPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        // Every request in the suite normally skips the CSRF check, so an
        // expired page can only be produced with the app reporting a
        // non-testing environment.
        $this->app['env'] = 'local';
    }

    public function test_a_back_office_request_lands_on_the_staff_page(): void
    {
        $admin = $this->staff();

        $response = $this->actingAs($admin)->post(route('admin.reservations.store'), []);

        $response->assertStatus(419)
            ->assertSee('Your back office session ended')
            ->assertSee('Sign in to the back office')
            ->assertSee(route('admin.login'), false)
            ->assertDontSee('PAGE EXPIRED');

        // Branded, not bare: the resort's own shell around the message.
        $this->assertStringContainsString(asset('images/logo-villa-mercedes.png'), $response->getContent());
        $this->assertStringContainsString(config('resort.short_name'), $response->getContent());
        $this->assertStringContainsString('<title>Session ended', $response->getContent());
    }

    public function test_a_guest_request_lands_on_the_guest_page(): void
    {
        $response = $this->post('/register', []);

        $response->assertStatus(419)
            ->assertSee('Your session ended')
            ->assertSee('Sign in again')
            ->assertSee(route('login'), false)
            ->assertDontSee(route('admin.login'), false)
            ->assertDontSee('PAGE EXPIRED');

        $this->assertStringContainsString('<title>Session ended', $response->getContent());
    }

    public function test_the_refused_submission_changed_nothing(): void
    {
        $admin = $this->staff();

        $counts = [
            'reservations' => Reservation::count(),
            'users' => User::count(),
        ];

        $this->actingAs($admin)
            ->post(route('admin.reservations.store'), ['user_id' => $admin->id])
            ->assertStatus(419);

        // The page promises nothing was changed, so the promise is checked.
        $this->assertSame($counts['reservations'], Reservation::count());
        $this->assertSame($counts['users'], User::count());
    }

    private function staff(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);

        return $admin;
    }
}
