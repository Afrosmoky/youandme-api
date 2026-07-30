<?php

namespace App\Modules\Rewards\Actions;

use App\Modules\Rewards\Models\CoupleDailyAdReward;
use App\Modules\Rewards\Support\AdRewardPolicy;
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
                ->where('count', '<', AdRewardPolicy::DAILY_CAP)
                ->increment('count');

            if ($claimed !== 1) {
                return false;
            }

            // In the same transaction as the increment, so a failed grant rolls the
            // counter back — no "counted but not credited" state (share self-heal).
            GrantCreditsAction::run($coupleId, AdRewardPolicy::CREDITS_PER_AD);

            return true;
        });

        $count = (int) CoupleDailyAdReward::query()
            ->where('couple_id', $coupleId)
            ->where('reward_date', $day)
            ->value('count');

        return new AdRewardResult(
            granted: $granted,
            creditsAwarded: $granted ? AdRewardPolicy::CREDITS_PER_AD : 0,
            remainingToday: max(0, AdRewardPolicy::DAILY_CAP - $count),
        );
    }
}
