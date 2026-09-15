<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\EmailOtpVerification;
use App\Models\User;
use App\Services\RegistrationOtpService;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Tests\TestCase;

class RegistrationOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        RateLimiter::clear('otp-send-ip:127.0.0.1');
        RateLimiter::clear('otp-verify-ip:127.0.0.1');
    }

    public function test_valid_email_sends_otp_without_exposing_the_code(): void
    {
        Notification::fake();

        $response = $this->from(route('register'))
            ->post(route('register.otp.send'), ['email' => 'guest@example.com']);

        $response->assertRedirect(route('register'));
        $response->assertSessionHas('status', RegistrationOtpService::ELIGIBLE_STATUS);
        $response->assertDontSee(RegistrationOtpService::EMAIL_TAKEN);

        $code = $this->lastRegistrationOtp();

        $this->get(route('register'))
            ->assertOk()
            ->assertDontSee($code)
            ->assertDontSee('otp_hash');

        $record = EmailOtpVerification::query()->where('email', 'guest@example.com')->first();

        $this->assertNotNull($record);
        $this->assertNotSame($code, $record->otp_hash);
        $this->assertTrue(Hash::check($code, $record->otp_hash));
        $this->assertDatabaseMissing('users', ['email' => 'guest@example.com']);
    }

    public function test_invalid_email_is_rejected(): void
    {
        Notification::fake();

        $this->from(route('register'))
            ->post(route('register.otp.send'), ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_existing_registered_email_is_rejected_and_does_not_receive_otp(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->from(route('register'))
            ->post(route('register.otp.send'), ['email' => 'taken@example.com']);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['email' => RegistrationOtpService::EMAIL_TAKEN]);
        $response->assertSessionMissing('status');

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('email_otp_verifications', ['email' => 'taken@example.com']);
        $this->assertFalse(session()->has(RegistrationOtpService::SESSION_PENDING_EMAIL));

        $this->get(route('register'))
            ->assertOk()
            ->assertSee(RegistrationOtpService::EMAIL_TAKEN)
            ->assertSee('Already have an account?')
            ->assertDontSee('Enter your verification code');
    }

    public function test_correct_otp_verifies_and_wrong_otp_is_rejected(): void
    {
        Notification::fake();

        $this->post(route('register.otp.send'), ['email' => 'guest@example.com']);
        $code = $this->lastRegistrationOtp();

        $this->from(route('register'))
            ->post(route('register.otp.verify'), [
                'email' => 'guest@example.com',
                'otp' => '000000',
            ])
            ->assertSessionHasErrors('otp');

        $this->from(route('register'))
            ->post(route('register.otp.verify'), [
                'email' => 'guest@example.com',
                'otp' => $code,
            ])
            ->assertSessionHas('status', 'Email verified.');

        $this->assertTrue(app(RegistrationOtpService::class)->hasVerifiedEmail('guest@example.com'));
    }

    public function test_expired_otp_is_rejected(): void
    {
        Notification::fake();

        $this->post(route('register.otp.send'), ['email' => 'guest@example.com']);
        $code = $this->lastRegistrationOtp();

        EmailOtpVerification::query()->where('email', 'guest@example.com')->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this->from(route('register'))
            ->post(route('register.otp.verify'), [
                'email' => 'guest@example.com',
                'otp' => $code,
            ])
            ->assertSessionHasErrors('otp');
    }

    public function test_otp_cannot_be_reused_after_success(): void
    {
        $code = $this->verifyRegistrationEmail('guest@example.com');

        $this->from(route('register'))
            ->post(route('register.otp.verify'), [
                'email' => 'guest@example.com',
                'otp' => $code,
            ])
            ->assertSessionHasErrors('otp');
    }

    public function test_resend_invalidates_the_previous_otp(): void
    {
        Notification::fake();
        config(['otp.resend_cooldown_seconds' => 0]);

        $this->post(route('register.otp.send'), ['email' => 'guest@example.com']);
        $firstCode = $this->lastRegistrationOtp();

        Notification::fake();
        $this->post(route('register.otp.send'), ['email' => 'guest@example.com']);
        $secondCode = $this->lastRegistrationOtp();

        $this->assertNotSame($firstCode, $secondCode);

        $this->from(route('register'))
            ->post(route('register.otp.verify'), [
                'email' => 'guest@example.com',
                'otp' => $firstCode,
            ])
            ->assertSessionHasErrors('otp');

        $this->from(route('register'))
            ->post(route('register.otp.verify'), [
                'email' => 'guest@example.com',
                'otp' => $secondCode,
            ])
            ->assertSessionHas('status', 'Email verified.');
    }

    public function test_verified_otp_for_one_email_cannot_register_another(): void
    {
        $this->verifyRegistrationEmail('one@example.com');

        $this->from(route('register'))
            ->post('/register', $this->registrationPayload('other@example.com', [
                'name' => 'Other Person',
            ]))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'one@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'other@example.com']);
    }

    public function test_changing_email_destroys_verification_state(): void
    {
        $this->verifyRegistrationEmail('old@example.com');

        $this->post(route('register.otp.reset'), ['email' => 'old@example.com']);

        $this->assertFalse(app(RegistrationOtpService::class)->hasVerifiedEmail('old@example.com'));
        // Reset removes this browser's verification, not another browser's challenge.
        $this->assertDatabaseHas('email_otp_verifications', ['email' => 'old@example.com']);

        $this->from(route('register'))
            ->post('/register', $this->registrationPayload('old@example.com', [
                'name' => 'Old Email',
            ]))
            ->assertSessionHasErrors('email');
    }

    public function test_send_and_verify_throttling_are_enforced(): void
    {
        Notification::fake();
        config([
            'otp.max_sends_per_email' => 1,
            'otp.max_verifies_per_email' => 1,
            'otp.resend_cooldown_seconds' => 0,
        ]);

        $this->post(route('register.otp.send'), ['email' => 'limit@example.com'])->assertSessionHas('status');
        $this->from(route('register'))
            ->post(route('register.otp.send'), ['email' => 'limit@example.com'])
            ->assertSessionHasErrors('email');

        Notification::fake();
        RateLimiter::clear('otp-send-email:second@example.com');
        $this->post(route('register.otp.send'), ['email' => 'second@example.com']);
        $code = $this->lastRegistrationOtp();

        $this->post(route('register.otp.verify'), [
            'email' => 'second@example.com',
            'otp' => '000000',
        ])->assertSessionHasErrors('otp');

        $this->from(route('register'))
            ->post(route('register.otp.verify'), [
                'email' => 'second@example.com',
                'otp' => $code,
            ])
            ->assertSessionHasErrors('otp');
    }

    public function test_ip_send_throttling_is_enforced(): void
    {
        Notification::fake();
        config([
            'otp.max_sends_per_ip' => 1,
            'otp.resend_cooldown_seconds' => 0,
        ]);

        $this->post(route('register.otp.send'), ['email' => 'first@example.com'])->assertSessionHas('status');
        $this->from(route('register'))
            ->post(route('register.otp.send'), ['email' => 'second@example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_resend_cooldown_is_enforced(): void
    {
        Notification::fake();
        config(['otp.resend_cooldown_seconds' => 60]);

        $this->post(route('register.otp.send'), ['email' => 'cool@example.com'])->assertSessionHas('status');
        $this->from(route('register'))
            ->post(route('register.otp.send'), ['email' => 'cool@example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_verification_attempt_limit_is_enforced(): void
    {
        Notification::fake();
        config(['otp.max_verify_attempts' => 1]);

        $this->post(route('register.otp.send'), ['email' => 'attempts@example.com']);
        $code = $this->lastRegistrationOtp();

        $this->post(route('register.otp.verify'), [
            'email' => 'attempts@example.com',
            'otp' => '000000',
        ])->assertSessionHasErrors('otp');

        $this->from(route('register'))
            ->post(route('register.otp.verify'), [
                'email' => 'attempts@example.com',
                'otp' => $code,
            ])
            ->assertSessionHasErrors('otp');
    }

    public function test_final_registration_without_otp_creates_no_user(): void
    {
        $this->from(route('register'))
            ->post('/register', $this->registrationPayload('bypass@example.com', [
                'name' => 'Bypass User',
            ]))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'bypass@example.com']);
        $this->assertGuest();
    }

    public function test_verified_registration_creates_customer_with_verified_email(): void
    {
        $this->verifyRegistrationEmail('jane@example.com');

        $response = $this->post('/register', $this->registrationPayload('jane@example.com', [
            'name' => 'Jane Customer',
            'phone' => '0917 123 4567',
            'role' => Role::Administrator->value,
            'email_verified' => true,
        ]));

        $response->assertRedirect(route('customer.reservations.index'));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'jane@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('0917 123 4567', $user->phone);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole(Role::Customer->value));
        $this->assertFalse($user->hasRole(Role::Administrator->value));
        $this->assertTrue(Hash::check('Password123!', $user->password));

        $record = EmailOtpVerification::query()->where('email', 'jane@example.com')->first();
        $this->assertNotNull($record?->consumed_at);

        $this->post(route('logout'));
        $this->assertGuest();

        Notification::fake();

        $this->from(route('register'))
            ->post(route('register.otp.send'), ['email' => 'jane@example.com'])
            ->assertSessionHasErrors(['email' => RegistrationOtpService::EMAIL_TAKEN])
            ->assertSessionMissing('status');

        Notification::assertNothingSent();
    }

    public function test_failed_mail_delivery_consumes_send_limit_and_creates_nothing(): void
    {
        config([
            'otp.max_sends_per_email' => 1,
            'otp.resend_cooldown_seconds' => 0,
        ]);

        $this->mock(Dispatcher::class, function ($mock): void {
            $mock->shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP down'));
        });

        $this->from(route('register'))
            ->post(route('register.otp.send'), ['email' => 'failmail@example.com'])
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('status');

        $this->assertDatabaseMissing('email_otp_verifications', ['email' => 'failmail@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'failmail@example.com']);

        $this->from(route('register'))
            ->post(route('register.otp.send'), ['email' => 'failmail@example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_registration_form_includes_csrf_token(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('name="_token"', false)
            ->assertSee('Create your account')
            ->assertSee('Already have an account?');
    }
}
