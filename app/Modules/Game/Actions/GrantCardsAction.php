<?php

namespace App\Modules\Game\Actions;

use App\Modules\Game\Models\Couple;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Add cards to a couple's balance — the single write path for every bonus
 * (referral now, share in Slice 3, purchases in P7). Atomic increment via SQL
 * (couples.card_balance = card_balance + amount), so concurrent grants do not
 * lose updates. Returns the refreshed couple.
 */
final class GrantCardsAction
{
    use AsAction;

    public function handle(Couple $couple, int $amount): Couple
    {
        $couple->increment('card_balance', $amount);

        return $couple;
    }
}
