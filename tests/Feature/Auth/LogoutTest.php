<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Signing out has to work when the session behind it is already gone without
 * turning logout into a CSRF bypass for a live authenticated session.
 *
 * A 419 on a logout is what a person sees when the page they are reading was
 * rendered under a session that has since died: a tab left open past its
 * lifetime, a second tab signing in and rotating the token, a reseeded local
 * database. "Page expired" is the wrong answer to "let me out" — it leaves them
 * signed in with no way to leave. A live session, however, must still present a
 * valid token before it can be changed.
 */
class LogoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_a_missing_token_cannot_log_out_a_signed_in_guest(): void
    {
        $customer = $this->createUser(Role::Customer);

        $this->withCsrfEnforced();

        $this->actingAs($customer)
            ->from(route('customer.reservations.index'))
            ->post('/logout')
            ->assertStatus(419);

        $this->assertAuthenticatedAs($customer);
    }

    public function test_a_missing_token_cannot_log_out_a_signed_in_administrator(): void
    {
        $admin = $this->createUser(Role::Administrator);

        $this->withCsrfEnforced();

        // Deliberately sent with no referer at all: the staff door is decided by
        // the route the form posts to, so it cannot depend on a page the browser
        // may not name — a policy-free referer is exactly when a staff member
        // would otherwise be dropped on the public home page.
        $this->actingAs($admin)
            ->post(route('admin.logout'))
            ->assertStatus(419);

        $this->assertAuthenticatedAs($admin);
    }

    public function test_an_expired_guest_session_gets_a_friendly_logout_result(): void
    {
        $this->withCsrfEnforced();

        $this->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_a_stale_token_is_still_refused_anywhere_but_logout(): void
    {
        $admin = $this->createUser(Role::Administrator);

        $this->withCsrfEnforced();

        // The escape hatch is one path wide. Anywhere else a mismatched token is
        // still a refusal, and the session it arrived on is left untouched.
        $this->actingAs($admin)
            ->post(route('admin.notifications.mark-all-as-read'))
            ->assertStatus(419);

        $this->assertAuthenticatedAs($admin);
    }

    private function createUser(Role $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    /**
     * Every request in the suite normally skips the CSRF check, so a real
     * mismatch can only be produced with the app reporting a non-testing
     * environment.
     */
    private function withCsrfEnforced(): void
    {
        $this->app['env'] = 'local';
    }
}
