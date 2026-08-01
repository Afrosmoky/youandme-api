<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Memories\Queries\CountMemoriesForCoupleQuery;
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
 * the counter belongs to Memories and the map to Progress, and neither may know
 * the other. The app asks one for a number and hands it to the other.
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

        $totalPlayed = CountMemoriesForCoupleQuery::run($coupleId);

        return response()->json(
            new ProgressResource(GetProgressForCoupleQuery::run($coupleId, $totalPlayed))
        );
    }
}
