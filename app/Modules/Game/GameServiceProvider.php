<?php

namespace App\Modules\Game;

use Illuminate\Support\ServiceProvider;

class GameServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // TODO Etap 1+: bind module services, merge config, register bindings.
    }

    public function boot(): void
    {
        // TODO Etap 1+: wire module routes & migrations once Game code is moved here.
        // $this->loadRoutesFrom(__DIR__.'/Http/Routes/api.php');
        // $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
