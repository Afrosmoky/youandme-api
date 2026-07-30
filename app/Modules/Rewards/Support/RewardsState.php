<?php

namespace App\Modules\Rewards\Support;

/**
 * A couple's reward account as the client sees it: the balance, the two one-time
 * claims and today's ad budget. Everything the "moje nagrody" screen needs in one
 * read — P7 is the first release where the user sees the currency at all.
 *
 * A plain value object (not a spatie Data): produced by GetRewardsStateQuery and
 * consumed by the Rewards controller, it never crosses a module boundary — same
 * as AdRewardResult.
 */
final readonly class RewardsState
{
    public function __construct(
        public int $credits,
        public bool $shareRewardClaimed,
        public bool $ratingRewardClaimed,
        public int $adsRemainingToday,
    ) {}
}
