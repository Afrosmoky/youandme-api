<?php

namespace Youandme\Notifications;

use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // TODO Etap 1+: bind package services, merge config, register bindings.
    }

    public function boot(): void
    {
        // TODO Etap 1+: wire package routes & migrations once Notifications code is moved here.
        // $this->loadRoutesFrom(__DIR__.'/Http/Routes/api.php');
        // $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
