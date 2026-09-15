<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function customer(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::Customer->value);

        return $user;
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Welcome back');
        $response->assertSee('Sign in to your account');
        $response->assertSee('Create one in a minute');
        $response->assertSee('Keep me signed in');
        $response->assertSee('Sign in');
        $response->assertSee('Forgot password?');
        $response->assertSee('A peaceful nature farm stay in Ilagan');
        $response->assertSee(config('resort.location'));
        $response->assertDontSee('Access my bookings');
        $response->assertDontSee('browse rooms without signing in');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = $this->customer();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('customer.reservations.index', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = $this->customer();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = $this->customer();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_remember_me_sets_a_recaller_cookie_and_token(): void
    {
        $user = $this->customer();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('customer.reservations.index', absolute: false));
        $response->assertCookie($this->recallerCookieName());
        $this->assertTrue(session('auth.keep'));

        $user->refresh();

        $this->assertNotEmpty($user->remember_token);
    }

    public function test_login_without_remember_me_does_not_set_a_recaller_cookie(): void
    {
        $user = $this->customer();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('customer.reservations.index', absolute: false));
        $response->assertCookieMissing($this->recallerCookieName());
        $this->assertFalse((bool) session('auth.keep'));
    }

    public function test_remember_me_keeps_the_user_signed_in_after_the_session_expires(): void
    {
        $user = $this->customer();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => 'on',
        ]);

        $recaller = $this->recallerCookieName();
        $cookie = $response->getCookie($recaller);

        $this->assertNotNull($cookie);
        $this->assertNotEmpty($cookie->getValue());

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->withCookie($recaller, $cookie->getValue())
            ->get(route('dashboard'))
            ->assertRedirect(route('customer.reservations.index', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(session('auth.keep'));
    }
}
