<?php

use App\Modules\Rewards\Actions\DeleteAdRewardNoncesForUserAction;
use App\Modules\Rewards\Actions\DeleteRewardsForCoupleAction;
use App\Modules\Rewards\Actions\IssueAdRewardNonceAction;
use Illuminate\Support\Facades\DB;

/** @return array<string, int> */
function rewardRowsOfCouple(int $coupleId): array
{
    $counts = [];
    foreach (['couple_rewards', 'couple_daily_ad_rewards', 'ad_reward_nonces'] as $table) {
        $counts[$table] = DB::table($table)->where('couple_id', $coupleId)->count();
    }

    return $counts;
}

test('it deletes the couple reward account, ad counters and nonces', function (): void {
    $user = createUserWithCouple();
    seedAccountFootprint($user);
    $other = createUserWithCouple();
    seedAccountFootprint($other);

    DeleteRewardsForCoupleAction::run($user->active_couple_id);

    expect(array_sum(rewardRowsOfCouple($user->active_couple_id)))->toBe(0)
        ->and(rewardRowsOfCouple($other->active_couple_id))->each->toBeGreaterThan(0);
});

test('it deletes the nonces issued to a user, whichever couple they name', function (): void {
    $user = createUserWithCouple();
    $other = createUserWithCouple();
    IssueAdRewardNonceAction::run($user->active_couple_id, $user->id);
    IssueAdRewardNonceAction::run($other->active_couple_id, $user->id);
    IssueAdRewardNonceAction::run($other->active_couple_id, $other->id);

    DeleteAdRewardNoncesForUserAction::run($user->id);

    expect(DB::table('ad_reward_nonces')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('ad_reward_nonces')->where('user_id', $other->id)->count())->toBe(1);
});
