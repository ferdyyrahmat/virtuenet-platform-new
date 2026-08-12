<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(fn (User $user): ?bool => $user->isDeveloper() ? true : null);
        Gate::define('viewPulse', fn (User $user): bool => $user->isAdmin());

        Event::listen(Login::class, function (Login $event): void {
            if (session()->has('impersonation')) {
                return;
            }

            $via = session()->pull('auth_via', 'password');
            $description = $via === 'lark' ? 'User logged in via Lark SSO' : 'User logged in';

            audit_log($description, 'auth.login', 'auth', [
                'guard' => $event->guard,
                'login_method' => $via,
            ], $event->user);
        });
    }
}
