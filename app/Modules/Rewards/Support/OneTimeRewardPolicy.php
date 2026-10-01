<?php

namespace App\Modules\Rewards\Support;

/**
 * The one-time reward numbers, in one place. Product constants (Wiktoria), not
 * technical ones: one-off gestures pay more than a repeatable ad.
 *
 * Extracted from the claim Actions when a second reader appeared — GET /rewards
 * now reports these values, so the mobile app stops keeping its own copy (same
 * move as AdRewardPolicy in P7). Pinned by RewardsEconomyContractTest.
 */
final class OneTimeRewardPolicy
{
    /** Credits granted for calling the native share dialog (once per couple). */
    public const SHARE_REWARD_CREDITS = 5;

    /** Credits granted for asking for the store rating prompt (once per couple). */
    public const RATING_REWARD_CREDITS = 5;
}
