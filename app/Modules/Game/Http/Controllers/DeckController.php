<?php

namespace App\Modules\Game\Http\Controllers;

use App\Modules\Game\Http\Resources\DeckResource;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Queries\GetDeckStateForCoupleQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * GET /deck — which cards of the closed deck this couple owns. A Game aggregate
 * read (own entitlement table + Catalog's is_locked through an existing edge), so
 * the controller stays in the module; the credits balance is a peer concern and
 * lives at GET /rewards, which the client fetches alongside.
 *
 * The couple comes from the token, never from the input (IDOR — canon §5).
 */
final class DeckController
{
    public function show(Request $request): JsonResponse
    {
        $coupleId = $request->user()->active_couple_id;

        if ($coupleId === null) {
            throw new NotFoundHttpException;
        }

        $couple = Couple::findOrFail($coupleId);

        return response()->json(
            new DeckResource(GetDeckStateForCoupleQuery::run($couple))
        );
    }
}
