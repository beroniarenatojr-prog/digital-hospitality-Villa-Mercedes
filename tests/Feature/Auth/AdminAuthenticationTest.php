<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_admin_login_screen_can_be_rendered(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Administrator sign in')
            ->assertDontSee('Create one in a minute')
            ->assertDontSee('Create an account');
    }

    public function test_guest_admin_root_redirects_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_signed_in_customer_can_open_admin_login(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);

        $this->actingAs($customer)
            ->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Administrator sign in');

        $this->assertGuest();
    }

    public function test_signed_in_administrator_is_sent_to_the_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);

        $this->actingAs($admin)
            ->get(route('admin.login'))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_administrator_can_sign_in_via_admin_login(): void
    {
        $admin = User::factory()->create([
            'password' => Hash::make('Password123!'),
        ]);
        $admin->assignRole(Role::Administrator->value);

        $response = $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('admin.dashboard', absolute: false));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_customer_cannot_use_admin_login(): void
    {
        $customer = User::factory()->create([
            'password' => Hash::make('Password123!'),
        ]);
        $customer->assignRole(Role::Customer->value);

        $response = $this->from(route('admin.login'))
            ->post(route('admin.login.store'), [
                'email' => $customer->email,
                'password' => 'Password123!',
            ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(trans('auth.failed'), session('errors')->first('email'));
    }

    public function test_administrator_using_customer_login_is_routed_to_admin_dashboard(): void
    {
        $admin = User::factory()->create([
            'password' => Hash::make('Password123!'),
        ]);
        $admin->assignRole(Role::Administrator->value);

        $response = $this->from(route('login'))
            ->post('/login', [
                'email' => $admin->email,
                'password' => 'Password123!',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_guest_admin_routes_redirect_to_admin_login_and_preserve_intended_url(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));

        $admin = User::factory()->create([
            'password' => Hash::make('Password123!'),
        ]);
        $admin->assignRole(Role::Administrator->value);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'Password123!',
        ])->assertRedirect(route('admin.dashboard'));
    }

    public function test_guest_customer_routes_redirect_to_customer_login(): void
    {
        $this->get(route('customer.reservations.index'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_register_route_does_not_exist(): void
    {
        $this->get('/admin/register')->assertNotFound();
        $this->post('/admin/register')->assertNotFound();
    }

    public function test_customer_is_forbidden_from_admin_routes_after_customer_login(): void
    {
        $customer = User::factory()->create([
            'password' => Hash::make('Password123!'),
        ]);
        $customer->assignRole(Role::Customer->value);

        $this->post('/login', [
            'email' => $customer->email,
            'password' => 'Password123!',
        ])->assertRedirect(route('customer.reservations.index', absolute: false));

        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_admin_logout_invalidates_the_session(): void
    {
        $admin = User::factory()->create([
            'password' => Hash::make('Password123!'),
        ]);
        $admin->assignRole(Role::Administrator->value);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'Password123!',
        ]);

        $this->assertAuthenticatedAs($admin);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
