<?php

namespace App\Modules\Premium;

use App\Modules\Premium\Console\Commands\MakePromoCodeCommand;
use Illuminate\Support\ServiceProvider;

/**
 * Premium owns promo codes and the register of who redeemed them — monetization,
 * a capability of its own rather than a corner of Rewards.
 *
 * It registers NO routes on purpose: the only endpoint, POST /redeem, spans
 * Premium (validate + record) and Game (unlock the deck), so it lives in the app
 * layer like every other cross-module write. That is precisely what lets a whole
 * new module arrive without adding a single edge to the graph — Premium → ∅.
 */
class PremiumServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->commands([
            MakePromoCodeCommand::class,
        ]);
    }
}
