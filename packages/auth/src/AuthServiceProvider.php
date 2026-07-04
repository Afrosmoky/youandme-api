<?php

namespace Youandme\Auth;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Youandme\Auth\Support\AppleTokenVerifier;
use Youandme\Auth\Support\AppleTokenVerifierInterface;
use Youandme\Auth\Support\GoogleTokenVerifier;
use Youandme\Auth\Support\GoogleTokenVerifierInterface;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One interface per provider (Adapter pattern) — see CLAUDE.md P2.
        $this->app->bind(GoogleTokenVerifierInterface::class, GoogleTokenVerifier::class);
        $this->app->bind(AppleTokenVerifierInterface::class, AppleTokenVerifier::class);
    }

    public function boot(): void
    {
        // Wrap in the `api` prefix + middleware group so the package routes land
        // at /api/v1/... exactly like the central routes/api.php (loaded via
        // bootstrap withRouting).
        Route::middleware('api')
            ->prefix('api')
            ->group(fn () => $this->loadRoutesFrom(__DIR__.'/Http/Routes/api.php'));

        // Reset / verification URLs are built inside the User model's
        // notification hooks (which emit domain events for Notifications to send)
        // — no ResetPassword::createUrlUsing needed anymore. See R1 Etap 3.
    }
}
