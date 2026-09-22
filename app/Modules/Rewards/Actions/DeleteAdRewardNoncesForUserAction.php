<?php

namespace App\Modules\Rewards\Actions;

use App\Modules\Rewards\Models\AdRewardNonce;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Delete the ad nonces issued to a user. A nonce names the user as well as the
 * couple (ad_reward_nonces.user_id is a foreign key), so the couple sweep alone
 * is not enough to let the users row go.
 */
final class DeleteAdRewardNoncesForUserAction
{
    use AsAction;

    public function handle(int $userId): void
    {
        AdRewardNonce::query()->where('user_id', $userId)->delete();
    }
}
