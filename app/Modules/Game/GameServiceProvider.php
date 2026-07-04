<?php

namespace App\Modules\Game;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class GameServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Wrap in the `api` prefix + middleware group so module routes land at
        // /api/v1/... exactly like the central routes/api.php.
        Route::middleware('api')
            ->prefix('api')
            ->group(fn () => $this->loadRoutesFrom(__DIR__.'/Http/Routes/api.php'));
    }
}
