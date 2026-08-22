<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class FoundationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_user_can_register_and_receives_customer_role(): void
    {
        $response = $this->post('/register', [
            'name' => 'Jane Customer',
            'email' => 'jane@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('customer.reservations.index'));

        $user = User::query()->where('email', 'jane@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole(Role::Customer->value));
    }

    public function test_user_can_login_with_remember_me(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Password123!'),
        ]);
        $user->assignRole(Role::Customer->value);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!',
            'remember' => 'on',
        ]);

        $response->assertRedirect(route('customer.reservations.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_route_is_protected_by_role_middleware(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);

        $this->actingAs($customer)
            ->get('/admin/dashboard')
            ->assertForbidden();

        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk();
    }

    public function test_user_policy_allows_self_update(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Customer->value);

        $this->assertTrue($user->can('update', $user));
    }

    public function test_authenticated_user_can_upload_valid_file(): void
    {
        Storage::fake('uploads');

        $user = User::factory()->create();
        $user->assignRole(Role::Customer->value);

        $file = UploadedFile::fake()->image('photo.jpg');

        \Illuminate\Support\Facades\Route::post('/uploads', function (\Illuminate\Http\Request $request) { $request->file('file')->store('uploads', 'uploads'); return response()->json(['success' => true]); });

        $response = $this->actingAs($user)->post('/uploads', [
            'file' => $file,
        ]);

        $response->assertOk();
        Storage::disk('uploads')->assertExists('uploads/'.pathinfo($file->hashName(), PATHINFO_BASENAME));
    }

    public function test_activity_log_records_user_registration(): void
    {
        $this->post('/register', [
            'name' => 'Log Test User',
            'email' => 'logtest@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'user_registered',
        ]);
    }

    public function test_roles_exist_after_seeding(): void
    {
        $this->assertDatabaseHas('roles', ['name' => Role::Administrator->value]);
        $this->assertDatabaseHas('roles', ['name' => Role::Customer->value]);
    }
}



