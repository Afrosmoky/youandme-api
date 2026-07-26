<?php

namespace App\Modules\Rewards\Actions;

use App\Modules\Rewards\Models\CoupleReward;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Claim the one-time share reward for a couple. We reward the gesture (calling
 * the native share dialog), not a verified share — sharing is unverifiable by
 * design. Idempotent and race-safe: the flag is claimed with an atomic
 * conditional UPDATE (whereNull → affected == 1), and only that first claim
 * grants credits, so a repeated call is a no-op with no double grant.
 *
 * The account row is created first (firstOrCreate): without it the conditional
 * UPDATE would match zero rows and the grant would silently never happen.
 *
 * Wrapped in a transaction so the flag and the grant move together: if the grant
 * fails, the claim rolls back and a retry is possible (no "flag set, no credits"
 * state) — same self-heal as the referral first-open trigger.
 */
final class ClaimShareRewardAction
{
    /**
     * Credits unlocked by sharing. A product number (placeholder 5, to confirm with
     * Wiktoria) — not a technical constant; changing it is a one-value edit.
     */
    private const SHARE_REWARD_CREDITS = 5;

    use AsAction;

    public function handle(int $coupleId): void
    {
        CoupleReward::query()->firstOrCreate(['couple_id' => $coupleId]);

        DB::transaction(function () use ($coupleId): void {
            $claimed = CoupleReward::query()
                ->where('couple_id', $coupleId)
                ->whereNull('share_reward_claimed_at')
                ->update(['share_reward_claimed_at' => now()]);

            if ($claimed === 1) {
                GrantCreditsAction::run($coupleId, self::SHARE_REWARD_CREDITS);
            }
        });
    }
}
