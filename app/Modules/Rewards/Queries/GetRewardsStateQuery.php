<?php

namespace App\Modules\Rewards\Queries;

use App\Modules\Rewards\Models\CoupleDailyAdReward;
use App\Modules\Rewards\Models\CoupleReward;
use App\Modules\Rewards\Support\AdRewardPolicy;
use App\Modules\Rewards\Support\RewardsState;
use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Read a couple's reward account. Pure read (CQS): a couple that has never
 * earned anything has no account row and no bucket — that is a zero balance and
 * a full ad budget, not a reason to create rows.
 *
 * The couple's local date is passed in (resolved by the controller from
 * users.timezone), so the query stays timezone-agnostic — the same split as the
 * daily card and the ad claim.
 */
final class GetRewardsStateQuery
{
    use AsAction;

    public function handle(int $coupleId, CarbonImmutable $localDate): RewardsState
    {
        /** @var CoupleReward|null $account */
        $account = CoupleReward::query()->where('couple_id', $coupleId)->first();

        $adsToday = (int) CoupleDailyAdReward::query()
            ->where('couple_id', $coupleId)
            // Calendar day, never a timestamp comparison — the P4 daily-card lesson.
            ->where('reward_date', $localDate->toDateString())
            ->value('count');

        return new RewardsState(
            // Through the balance query, so "what is the balance" has one reader
            // for both the account screen and the unlock response.
            credits: GetCreditBalanceQuery::run($coupleId),
            shareRewardClaimed: $account?->share_reward_claimed_at !== null,
            ratingRewardClaimed: $account?->rating_reward_claimed_at !== null,
            adsRemainingToday: max(0, AdRewardPolicy::DAILY_CAP - $adsToday),
        );
    }
}
