<?php

namespace Tests\Feature\Auth;

use App\Enums\GuestGender;
use App\Enums\GuestIdType;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee('Create your account');
        $response->assertSee('Send Verification Code');
        $response->assertSee('We’ll check this email first');
        $response->assertSee('Already have an account?');
        $response->assertSee('Sign in');
        $response->assertSee('A peaceful nature farm stay in Ilagan');
        $response->assertSee(config('resort.location'));
    }

    public function test_registration_screen_includes_password_strength_meter_after_verification(): void
    {
        $this->verifyRegistrationEmail('meter@example.com');

        $this->get('/register')
            ->assertOk()
            ->assertSee('data-password-meter', false)
            ->assertSee('Use at least 8 characters.')
            ->assertSee('Mobile number')
            ->assertSee('Sex')
            ->assertDontSee('Gender')
            ->assertSee('Date of birth')
            ->assertSee('Valid ID type')
            ->assertSee('Terms of Use')
            ->assertSee('Privacy Notice');
    }

    public function test_new_users_can_register(): void
    {
        $this->verifyRegistrationEmail('test@example.com');

        $response = $this->post('/register', $this->registrationPayload('test@example.com', [
            'name' => 'Test User',
            'company' => 'Acme Tours',
        ]));

        $this->assertAuthenticated();
        $response->assertRedirect(route('customer.reservations.index', absolute: false));

        $user = User::query()->where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('09171234567', $user->phone);
        $this->assertSame(GuestGender::Female, $user->gender);
        $this->assertSame('1995-05-15', $user->date_of_birth?->toDateString());
        $this->assertSame('Barangay Namnama, City of Ilagan, Isabela', $user->address);
        $this->assertSame('Filipino', $user->nationality);
        $this->assertSame(GuestIdType::PhilSys, $user->valid_id_type);
        $this->assertSame('Acme Tours', $user->company);
        $this->assertNotNull($user->terms_accepted_at);
    }

    public function test_registration_requires_terms_acceptance(): void
    {
        $this->verifyRegistrationEmail('noterms@example.com');

        $this->from('/register')
            ->post('/register', $this->registrationPayload('noterms@example.com', [
                'terms_accepted' => null,
            ]))
            ->assertSessionHasErrors('terms_accepted');

        $this->assertDatabaseMissing('users', ['email' => 'noterms@example.com']);
    }

    public function test_registration_requires_a_valid_mobile_number(): void
    {
        $this->verifyRegistrationEmail('nophone@example.com');

        $this->from('/register')
            ->post('/register', $this->registrationPayload('nophone@example.com', [
                'name' => 'No Phone',
                'phone' => null,
            ]))
            ->assertSessionHasErrors('phone');

        $this->from('/register')
            ->post('/register', $this->registrationPayload('nophone@example.com', [
                'name' => 'No Phone',
                'phone' => '123',
            ]))
            ->assertSessionHasErrors('phone');

        $this->assertDatabaseMissing('users', ['email' => 'nophone@example.com']);
    }

    public function test_terms_and_privacy_pages_are_public(): void
    {
        $this->get(route('terms'))->assertOk()->assertSee('Terms of Use');
        $this->get(route('privacy'))->assertOk()->assertSee('Privacy Notice');
    }
}
