<?php

namespace App\Providers;

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
        // Auth-owned URL builders (ResetPassword / VerifyEmail) moved to
        // Youandme\Auth\AuthServiceProvider in R1 Etap 1.
    }
}
