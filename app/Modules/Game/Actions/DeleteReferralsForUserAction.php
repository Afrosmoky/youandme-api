<?php

namespace App\Modules\Game\Actions;

use App\Modules\Game\Models\Referral;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Forget every referral the user is part of, whichever side they were on.
 *
 * The row names two people, so this also removes it from the other person's
 * record. Nothing they were paid is taken back — credits live in Rewards — but a
 * referrer who has not opened the app yet loses the half still waiting for them.
 * Accepted: the relation is gone, and so is the person it rewarded them for.
 */
final class DeleteReferralsForUserAction
{
    use AsAction;

    public function handle(int $userId): void
    {
        Referral::query()
            ->where('referrer_user_id', $userId)
            ->orWhere('referred_user_id', $userId)
            ->delete();
    }
}
