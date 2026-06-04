<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
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
