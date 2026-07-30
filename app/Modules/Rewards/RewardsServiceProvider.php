<?php

namespace App\Modules\Rewards;

use App\Modules\Rewards\Console\Commands\PruneAdRewardCountersCommand;
use App\Modules\Rewards\Console\Commands\PruneAdRewardNoncesCommand;
use App\Modules\Rewards\Support\AdMobSignatureVerifier;
use App\Modules\Rewards\Support\AdMobSignatureVerifierInterface;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RewardsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Adapter over Google's verifier keys — bound to its interface so tests
        // (and any future ad network) swap it without touching the Actions. Same
        // convention as the social token verifiers in the Auth package.
        $this->app->bind(AdMobSignatureVerifierInterface::class, AdMobSignatureVerifier::class);

        $this->commands([
            PruneAdRewardCountersCommand::class,
            PruneAdRewardNoncesCommand::class,
        ]);
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
