<?php

namespace App\Modules\Rewards\Actions;

use App\Modules\Rewards\Models\CoupleReward;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Claim the one-time app-rating reward for a couple. We reward the GESTURE (the
 * client asked the OS for the review prompt), not the rating: In-App Review is
 * fire-and-forget — Apple/Google never tell us whether the user rated, or even
 * whether the prompt was shown. There is no callback and no server-side check to
 * add later; this is unverifiable by nature, exactly like share (and unlike the
 * rewarded ad, which IS verifiable via SSV — deferred to P7, not absent).
 *
 * Idempotent and race-safe, the share pattern 1:1: the account row is created
 * first (without it the conditional UPDATE would match zero rows and the grant
 * would silently never happen), the flag is claimed with an atomic conditional
 * UPDATE (whereNull → affected == 1), and only that first claim grants credits.
 *
 * The flag lives on couple_rewards, so "once per account" means once per COUPLE.
 * Inherited from share; revisit together with pairing (P10) and the reward
 * economy (P7) — canon §7 "user vs couple keying".
 *
 * Wrapped in a transaction so the flag and the grant move together: if the grant
 * fails, the claim rolls back and a retry is possible (no "flag set, no credits"
 * state).
 */
final class ClaimRatingRewardAction
{
    /**
     * Credits unlocked by asking for the rating prompt. A product number
     * (placeholder 5, to confirm with Wiktoria) — the same as share: one-off
     * gestures pay more than a repeatable ad (1, capped at 5 a day).
     */
    private const RATING_REWARD_CREDITS = 5;

    use AsAction;

    public function handle(int $coupleId): void
    {
        CoupleReward::query()->firstOrCreate(['couple_id' => $coupleId]);

        DB::transaction(function () use ($coupleId): void {
            $claimed = CoupleReward::query()
                ->where('couple_id', $coupleId)
                ->whereNull('rating_reward_claimed_at')
                ->update(['rating_reward_claimed_at' => now()]);

            if ($claimed === 1) {
                GrantCreditsAction::run($coupleId, self::RATING_REWARD_CREDITS);
            }
        });
    }
}
