<?php

namespace App\Modules\Rewards\Http\Controllers;

use App\Modules\Rewards\Actions\ClaimAdRewardAction;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * POST /ad-reward — credits for one watched rewarded ad, capped per local day.
 * The couple comes from the token, never from the client: with a repeatable,
 * unverified bonus, a client-supplied couple id would let anyone credit any
 * couple (canon §5; §3(a) says otherwise and is wrong).
 *
 * The couple's local date is resolved here from users.timezone and passed to the
 * Action as a plain date, so the Action stays timezone-agnostic — same split as
 * the daily card.
 *
 * Reaching the cap answers 200 with granted:false — it is a normal business
 * outcome ("come back tomorrow"), not a client error.
 */
final class AdRewardController
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $coupleId = $user->active_couple_id;

        // A token whose user has no couple has no reward account to credit — 404,
        // the same guard as the share reward.
        if ($coupleId === null) {
            throw new NotFoundHttpException;
        }

        $result = ClaimAdRewardAction::run($coupleId, CarbonImmutable::now($user->timezone)->startOfDay());

        return response()->json([
            'granted' => $result->granted,
            'credits_awarded' => $result->creditsAwarded,
            'remaining_today' => $result->remainingToday,
        ]);
    }
}
