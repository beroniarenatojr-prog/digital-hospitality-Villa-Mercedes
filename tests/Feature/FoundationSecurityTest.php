<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
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
        $this->verifyRegistrationEmail('jane@example.com');

        $response = $this->post('/register', $this->registrationPayload('jane@example.com', [
            'name' => 'Jane Customer',
        ]));

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
        $response->assertCookie($this->recallerCookieName());
        $this->assertTrue(session('auth.keep'));

        $user->refresh();
        $this->assertNotEmpty($user->remember_token);
    }

    public function test_admin_route_is_protected_by_role_middleware(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);

        $this->actingAs($customer)
            ->get('/admin/dashboard')
            ->assertRedirect(route('admin.login'));

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

        Route::post('/uploads', function (Request $request) {
            $request->file('file')->store('uploads', 'uploads');

            return response()->json(['success' => true]);
        });

        $response = $this->actingAs($user)->post('/uploads', [
            'file' => $file,
        ]);

        $response->assertOk();

        $disk = Storage::disk('uploads');
        $this->assertInstanceOf(FilesystemAdapter::class, $disk);
        $disk->assertExists('uploads/'.pathinfo($file->hashName(), PATHINFO_BASENAME));
    }

    public function test_activity_log_records_user_registration(): void
    {
        $this->verifyRegistrationEmail('logtest@example.com');

        $this->post('/register', $this->registrationPayload('logtest@example.com', [
            'name' => 'Log Test User',
        ]));

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
