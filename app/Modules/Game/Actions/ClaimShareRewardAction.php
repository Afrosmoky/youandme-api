<?php

namespace App\Modules\Game\Actions;

use App\Modules\Game\Models\Couple;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Claim the one-time share reward for a couple. We reward the gesture (calling
 * the native share dialog), not a verified share — sharing is unverifiable by
 * design. Idempotent and race-safe: the flag is claimed with an atomic
 * conditional UPDATE (whereNull → affected == 1), and only that first claim
 * grants cards, so a repeated call is a no-op with no double grant.
 *
 * Wrapped in a transaction so the flag and the grant move together: if the grant
 * fails, the claim rolls back and a retry is possible (no "flag set, no cards"
 * state) — same self-heal as the referral first-open trigger.
 */
final class ClaimShareRewardAction
{
    /**
     * Cards unlocked by sharing. A product number (placeholder 5, to confirm with
     * Wiktoria) — not a technical constant; changing it is a one-value edit.
     */
    private const SHARE_REWARD_CARDS = 5;

    use AsAction;

    public function handle(Couple $couple): Couple
    {
        DB::transaction(function () use ($couple): void {
            $claimed = Couple::query()
                ->whereKey($couple->getKey())
                ->whereNull('share_reward_claimed_at')
                ->update(['share_reward_claimed_at' => now()]);

            if ($claimed === 1) {
                GrantCardsAction::run($couple, self::SHARE_REWARD_CARDS);
            }
        });

        return $couple;
    }
}
