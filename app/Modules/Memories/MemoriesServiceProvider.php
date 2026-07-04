<?php

namespace App\Modules\Memories;

use Illuminate\Support\ServiceProvider;

class MemoriesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // TODO Etap 1+: bind module services, merge config, register bindings.
    }

    public function boot(): void
    {
        // TODO Etap 1+: wire module routes & migrations once Memories code is moved here.
        // $this->loadRoutesFrom(__DIR__.'/Http/Routes/api.php');
        // $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
