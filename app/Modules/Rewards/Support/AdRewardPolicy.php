<?php

namespace App\Modules\Rewards\Support;

/**
 * The rewarded-ad numbers, in one place. Product constants (Wiktoria), not
 * technical ones: 1 credit per watched ad, 5 ads per couple per local day.
 *
 * Extracted from ClaimAdRewardAction in P7 because a second reader appeared —
 * GET /rewards reports how many ads are left today, and the cap must not be
 * duplicated there. P7 slice 2 moves the enforcement to the SSV webhook; the
 * numbers stay here.
 */
final class AdRewardPolicy
{
    /** Credits granted for one watched ad. */
    public const CREDITS_PER_AD = 1;

    /** Rewarded ads a couple may be paid for in one local day. */
    public const DAILY_CAP = 5;
}
