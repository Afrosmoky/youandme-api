<?php

namespace App\Modules\Rewards\Http\Controllers;

use App\Modules\Rewards\Queries\GetRewardsStateQuery;
use App\Modules\Rewards\Support\AdRewardPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * GET /rewards — the couple's balance and what it can still earn. New in P7:
 * until now credits grew silently, with no way to read them.
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

        return response()->json([
            'credits' => $state->credits,
            'share_reward_claimed' => $state->shareRewardClaimed,
            'rating_reward_claimed' => $state->ratingRewardClaimed,
            'ads' => [
                'remaining_today' => $state->adsRemainingToday,
                'daily_cap' => AdRewardPolicy::DAILY_CAP,
            ],
        ]);
    }
}
