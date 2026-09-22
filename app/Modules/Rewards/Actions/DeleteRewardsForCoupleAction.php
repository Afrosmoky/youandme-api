<?php

namespace App\Modules\Rewards\Actions;

use App\Modules\Rewards\Models\AdRewardNonce;
use App\Modules\Rewards\Models\CoupleDailyAdReward;
use App\Modules\Rewards\Models\CoupleReward;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Delete a couple's reward account, its daily ad counters and the ad nonces
 * issued for it — Rewards's part of deleting an account.
 *
 * A nonce still waiting for its AdMob callback is deleted with the rest; the
 * callback then finds no nonce and is rejected like any unknown one.
 */
final class DeleteRewardsForCoupleAction
{
    use AsAction;

    public function handle(int $coupleId): void
    {
        AdRewardNonce::query()->where('couple_id', $coupleId)->delete();
        CoupleDailyAdReward::query()->where('couple_id', $coupleId)->delete();
        CoupleReward::query()->where('couple_id', $coupleId)->delete();
    }
}
