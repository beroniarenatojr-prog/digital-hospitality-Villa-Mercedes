<?php

namespace Tests;

use App\Enums\GuestGender;
use App\Enums\GuestIdType;
use App\Notifications\Auth\RegistrationOtpNotification;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Notifications\Dispatcher as NotificationDispatcher;
use Illuminate\Contracts\Notifications\Factory as NotificationFactory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

abstract class TestCase extends BaseTestCase
{
    protected function recallerCookieName(string $guard = 'web'): string
    {
        $auth = Auth::guard($guard);

        if (! $auth instanceof SessionGuard) {
            $this->fail("Expected [{$guard}] to use the session guard.");
        }

        return $auth->getRecallerName();
    }

    protected function lastRegistrationOtp(): string
    {
        $code = null;

        Notification::assertSentOnDemand(
            RegistrationOtpNotification::class,
            function (RegistrationOtpNotification $notification) use (&$code): bool {
                $code = $notification->code;

                return true;
            }
        );

        $this->assertNotEmpty($code);

        if (! is_string($code)) {
            $this->fail('Expected a registration OTP to be sent.');
        }

        return $code;
    }

    protected function verifyRegistrationEmail(string $email): string
    {
        Notification::fake();

        $this->post(route('register.otp.send'), ['email' => $email]);

        $code = $this->lastRegistrationOtp();

        $this->post(route('register.otp.verify'), [
            'email' => $email,
            'otp' => $code,
        ]);

        $this->restoreNotifications();

        return $code;
    }

    protected function restoreNotifications(): void
    {
        $manager = new ChannelManager($this->app);

        $this->app->singleton(NotificationDispatcher::class, fn () => $manager);
        $this->app->singleton(NotificationFactory::class, fn () => $manager);
        Notification::clearResolvedInstances();
        Notification::swap($manager);
    }

    protected function seedReservationCatalog(): void
    {
        // Transactional assertions need the catalog without demonstration stays or payments.
        $this->seed([
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\AdminUserSeeder::class,
            \Database\Seeders\FacilityTypeSeeder::class,
            \Database\Seeders\AmenitySeeder::class,
            \Database\Seeders\FacilitySeeder::class,
            \Database\Seeders\RateSeeder::class,
        ]);
    }

    /**
     * Account registration fields after email OTP verification.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function registrationPayload(string $email, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test User',
            'email' => $email,
            'phone' => '09171234567',
            'gender' => GuestGender::Female->value,
            'date_of_birth' => '1995-05-15',
            'address' => 'Barangay Namnama, City of Ilagan, Isabela',
            'nationality' => 'Filipino',
            'valid_id_type' => GuestIdType::PhilSys->value,
            'company' => null,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms_accepted' => '1',
        ], $overrides);
    }

    /**
     * Guest Information Sheet fields shared by walk-in and online booking.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function guestInformationPayload(array $overrides = []): array
    {
        return array_merge([
            'guest_name' => 'Maria Santos',
            'guest_place_of_origin' => 'Ilagan, Isabela',
            'guest_nationality' => 'Filipino',
            'guest_company' => 'Villa Mercedes Tours',
            'guest_valid_id' => GuestIdType::PhilSys->value,
            'guest_gender' => GuestGender::Female->value,
            'guest_phone' => '09171234567',
            'guest_email' => 'maria.santos@example.com',
            'other_guest_names' => [],
            'special_requests' => null,
            'policies_accepted' => '1',
        ], $overrides);
    }
}
