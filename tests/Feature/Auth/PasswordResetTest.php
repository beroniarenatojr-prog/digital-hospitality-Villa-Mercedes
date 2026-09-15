<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\GuestPasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('status', 'We emailed a password reset link. Check your inbox and spam folder.');

        Notification::assertSentTo($user, GuestPasswordResetNotification::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, GuestPasswordResetNotification::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, GuestPasswordResetNotification::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_lightweight_account_password_reset_lands_on_profile(): void
    {
        Notification::fake();

        $user = User::factory()->lightweight()->create([
            'email' => 'guest@example.com',
            'phone' => null,
        ]);

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, GuestPasswordResetNotification::class, function ($notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('profile.edit'))
                ->assertSessionHas('success', 'Add your name and mobile number so the resort can reach you.');

            $this->assertAuthenticatedAs($user->fresh());
            $this->assertFalse($user->fresh()->isLightweight());

            return true;
        });
    }

    public function test_reset_password_screen_prefills_email_from_query_string(): void
    {
        $this->get('/reset-password/example-token?email=guest@example.com')
            ->assertOk()
            ->assertSee('value="guest@example.com"', false);
    }

    public function test_local_log_mailer_shows_a_usable_reset_link(): void
    {
        config(['auth.passwords.users.reveal_link' => true]);

        $user = User::factory()->create();

        $response = $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $user->email]);

        $response->assertRedirect('/forgot-password')
            ->assertSessionHas('password_reset_url')
            ->assertSessionHas('status', 'We emailed a password reset link. Check your inbox and spam folder.');

        $this->get(session('password_reset_url'))
            ->assertOk()
            ->assertSee('Choose a new password')
            ->assertSee('value="'.$user->email.'"', false);
    }
}
