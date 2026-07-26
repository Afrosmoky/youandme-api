<?php

namespace App\Modules\Rewards\Actions;

use App\Modules\Rewards\Models\CoupleDailyAdReward;
use App\Modules\Rewards\Support\AdRewardResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Claim the reward for watching one rewarded ad. The first REPEATABLE bonus in
 * the project: idempotency is a daily counter against a cap, not a one-time flag
 * (share/rating). Hitting the cap is a no-op, not an error.
 *
 * We do not verify that the ad was actually watched — server-side verification is
 * deferred to P7, where credits become visible and worth spoofing (canon §8). In
 * P6 the call itself means "watched".
 *
 * Race-safe without a read-then-write window: the cap is enforced by a
 * CONDITIONAL increment (count < cap → affected == 1). On a concurrent claim at
 * the cap boundary, Postgres serializes the two updates on the row lock — the
 * first sees count < cap and grants, the second re-evaluates against the
 * committed count, matches nothing and grants nothing.
 *
 * The couple's local day is passed in (resolved by the controller from
 * users.timezone), so this stays timezone-agnostic and trivial to unit-test —
 * the same split as the daily card.
 */
final class ClaimAdRewardAction
{
    /**
     * Credits per watched ad. A product number (placeholder 1, to confirm with
     * Wiktoria) — not a technical constant.
     */
    private const AD_REWARD_CREDITS = 1;

    /**
     * Rewarded ads per couple per local day. A product number (placeholder 5, to
     * confirm with Wiktoria) — the whole anti-abuse story in P6, since nothing
     * verifies the view.
     */
    private const AD_REWARD_DAILY_CAP = 5;

    use AsAction;

    public function handle(int $coupleId, CarbonImmutable $localDate): AdRewardResult
    {
        // Calendar day, never a timestamp comparison — the P4 daily-card lesson.
        $day = $localDate->toDateString();

        CoupleDailyAdReward::query()->firstOrCreate([
            'couple_id' => $coupleId,
            'reward_date' => $day,
        ]);

        $granted = DB::transaction(function () use ($coupleId, $day): bool {
            $claimed = CoupleDailyAdReward::query()
                ->where('couple_id', $coupleId)
                ->where('reward_date', $day)
                ->where('count', '<', self::AD_REWARD_DAILY_CAP)
                ->increment('count');

            if ($claimed !== 1) {
                return false;
            }

            // In the same transaction as the increment, so a failed grant rolls the
            // counter back — no "counted but not credited" state (share self-heal).
            GrantCreditsAction::run($coupleId, self::AD_REWARD_CREDITS);

            return true;
        });

        $count = (int) CoupleDailyAdReward::query()
            ->where('couple_id', $coupleId)
            ->where('reward_date', $day)
            ->value('count');

        return new AdRewardResult(
            granted: $granted,
            creditsAwarded: $granted ? self::AD_REWARD_CREDITS : 0,
            remainingToday: max(0, self::AD_REWARD_DAILY_CAP - $count),
        );
    }
}
