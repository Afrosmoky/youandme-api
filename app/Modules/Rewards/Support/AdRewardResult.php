<?php

namespace App\Modules\Rewards\Support;

/**
 * Outcome of one rewarded-ad claim. A plain value object (not a spatie Data): it
 * is produced by ClaimAdRewardAction and consumed by the Rewards controller —
 * it never crosses a module boundary. Same shape of decision as Game's DailyCard
 * / RitualWeek support objects.
 *
 * Hitting the daily cap is a normal business outcome, not an error: granted is
 * false, creditsAwarded is 0, and the endpoint still answers 200.
 */
final readonly class AdRewardResult
{
    public function __construct(
        public bool $granted,
        public int $creditsAwarded,
        public int $remainingToday,
    ) {}
}
