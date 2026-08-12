<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Observers\PaymentObserver;
use App\Observers\ReservationObserver;
use App\Policies\UserPolicy;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\UserRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
    }

    public function boot(): void
    {
        Reservation::observe(ReservationObserver::class);
        Payment::observe(PaymentObserver::class);

        Gate::policy(User::class, UserPolicy::class);

        Gate::define('access-admin', fn (User $user): bool => $user->hasRole(Role::Administrator->value));

        Gate::define('access-customer', fn (User $user): bool => $user->hasRole(Role::Customer->value));

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute((int) env('RATE_LIMIT_API', 60))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute((int) env('RATE_LIMIT_LOGIN', 5))
                ->by($request->input('email').'|'.$request->ip());
        });
    }
}
