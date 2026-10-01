<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Game\Models\Couple;
use App\Modules\Game\Queries\GetDeckStateForCoupleQuery;
use App\Modules\Rewards\Queries\GetRewardsStateQuery;
use App\Modules\Rewards\Support\AdRewardPolicy;
use App\Modules\Rewards\Support\OneTimeRewardPolicy;
use App\Support\CardUnlockPrice;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * GET /rewards — the couple's balance, what it can still earn, what a card costs
 * and how many closed cards are left to spend it on.
 *
 * App composition root since the bulk unlock: the response now peer-combines
 * Rewards (balance, claims, reward values), the app-layer card price and Game's
 * closed deck — the {user, couple} rule. The path, middleware and the original
 * fields are the same as when Rewards served it alone; released builds read them,
 * so everything added here is additive only.
 *
 * The couple comes from the token (IDOR — canon §5) and its local date is
 * resolved here from users.timezone, so the Query takes a plain date.
 */
final class RewardsController
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $coupleId = $user->active_couple_id;

        // A token whose user has no couple has no reward account — 404, the same
        // guard as every other Rewards endpoint.
        if ($coupleId === null) {
            throw new NotFoundHttpException;
        }

        $state = GetRewardsStateQuery::run($coupleId, CarbonImmutable::now($user->timezone)->startOfDay());

        // Same reader as GET /deck, so the two screens can never disagree on how
        // many closed cards are left.
        $deck = GetDeckStateForCoupleQuery::run(Couple::findOrFail($coupleId));

        return response()->json([
            'credits' => $state->credits,
            'share_reward_claimed' => $state->shareRewardClaimed,
            'rating_reward_claimed' => $state->ratingRewardClaimed,
            'share_reward_credits' => OneTimeRewardPolicy::SHARE_REWARD_CREDITS,
            'rating_reward_credits' => OneTimeRewardPolicy::RATING_REWARD_CREDITS,
            'unlock_cost_credits' => CardUnlockPrice::CREDITS,
            'locked_remaining' => $deck->lockedRemaining(),
            'ads' => [
                'remaining_today' => $state->adsRemainingToday,
                'daily_cap' => AdRewardPolicy::DAILY_CAP,
                'credits_per_ad' => AdRewardPolicy::CREDITS_PER_AD,
            ],
        ]);
    }
}
