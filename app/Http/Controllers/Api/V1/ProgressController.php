<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Game\Queries\CountPlayedCardsForCoupleQuery;
use App\Modules\Progress\Http\Resources\ProgressResource;
use App\Modules\Progress\Queries\GetProgressForCoupleQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * GET /progress — the progress map: how many cards the couple has played, which
 * stages they have reached, and what they are playing towards.
 *
 * App composition root, for the same reason the milestone listener lives here:
 * the counter belongs to Game and the map to Progress, and neither may know the
 * other. The app asks one for a number and hands it to the other.
 *
 * The number is the couple's played set (P10), the same one the listener checks
 * thresholds against — the read and the write must never disagree about what
 * "played" means, so they ask the identical Query.
 *
 * The couple comes from the token, never from the input (IDOR).
 */
final class ProgressController
{
    public function show(Request $request): JsonResponse
    {
        $coupleId = $request->user()->active_couple_id;

        if ($coupleId === null) {
            throw new NotFoundHttpException;
        }

        $totalPlayed = CountPlayedCardsForCoupleQuery::run($coupleId);

        return response()->json(
            new ProgressResource(GetProgressForCoupleQuery::run($coupleId, $totalPlayed))
        );
    }
}
