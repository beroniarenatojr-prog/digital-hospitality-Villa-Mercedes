<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\GalleryImage;
use App\Models\PagePhoto;
use App\Models\Payment;
use App\Models\Rate;
use App\Models\Reservation;
use App\Models\User;
use App\Observers\PaymentObserver;
use App\Observers\PublicHomeCacheObserver;
use App\Observers\ReservationObserver;
use App\Policies\UserPolicy;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\UserRepository;
use App\Services\Payments\ManualPaymentGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PayMongoGateway;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
    ];

    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);

        $this->app->bind(PaymentGateway::class, function (): PaymentGateway {
            return match (config('payments.gateway')) {
                'paymongo' => new PayMongoGateway,
                default => new ManualPaymentGateway,
            };
        });
    }

    public function boot(): void
    {
        Reservation::observe(ReservationObserver::class);
        Payment::observe(PaymentObserver::class);
        Facility::observe(PublicHomeCacheObserver::class);
        FacilityType::observe(PublicHomeCacheObserver::class);
        GalleryImage::observe(PublicHomeCacheObserver::class);
        PagePhoto::observe(PublicHomeCacheObserver::class);
        Rate::observe(PublicHomeCacheObserver::class);

        Gate::policy(User::class, UserPolicy::class);

        Gate::define('access-admin', fn (User $user): bool => $user->hasRole(Role::Administrator->value));

        Gate::define('access-customer', fn (User $user): bool => $user->hasRole(Role::Customer->value));

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute((int) env('RATE_LIMIT_API', 60))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(10)->by(
                strtolower((string) $request->input('email')).'|'.$request->ip()
            );
        });

        RateLimiter::for('password-email', function (Request $request) {
            return Limit::perMinute(5)->by(
                strtolower((string) $request->input('email')).'|'.$request->ip()
            );
        });

        Event::listen(Login::class, function (Login $event): void {
            if (! $event->remember) {
                return;
            }

            session(['auth.keep' => true]);
            config(['session.lifetime' => (int) config('auth.remember_session_lifetime')]);
        });
    }
}
