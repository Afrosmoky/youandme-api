<?php

namespace App\Modules\Rewards\Actions;

use App\Modules\Rewards\Models\CoupleReward;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Take credits out of a couple's balance — the mirror of GrantCreditsAction and
 * the single debit path. Takes the couple id as a plain value, so Rewards stays a
 * leaf: it never learns what the credits were spent on (that is Game's
 * entitlement, orchestrated by the app layer in a shared transaction).
 *
 * Returns false when the balance is too low, instead of throwing: "not enough
 * credits" is a business outcome the orchestrator maps to a response, and it is
 * also the answer when the account row does not exist yet (nothing was ever
 * granted → nothing to spend).
 *
 * Race-safe by construction — a CONDITIONAL decrement (credits >= amount →
 * affected == 1), the same shape as the daily ad cap. No read-then-write window,
 * so two concurrent unlocks can never overdraw the balance.
 */
final class SpendCreditsAction
{
    use AsAction;

    public function handle(int $coupleId, int $amount): bool
    {
        $spent = CoupleReward::query()
            ->where('couple_id', $coupleId)
            ->where('credits', '>=', $amount)
            ->decrement('credits', $amount);

        return $spent === 1;
    }
}
