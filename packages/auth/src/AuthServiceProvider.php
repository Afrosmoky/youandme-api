<?php

namespace Youandme\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Youandme\Auth\Models\User;
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

        // The mobile client handles the actual reset form. Prefer a custom
        // scheme deep link (jaity://...) so the email opens the app directly;
        // fall back to an APP_URL link when no scheme is configured (web).
        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            $query = 'reset-password?token='.$token
                .'&email='.urlencode($user->getEmailForPasswordReset());

            $scheme = config('app.mobile_deep_link_scheme');

            return $scheme
                ? $scheme.'://'.$query
                : config('app.url').'/'.$query;
        });
    }
}
