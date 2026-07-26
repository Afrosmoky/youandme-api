<?php

namespace App\Modules\Rewards\Http\Controllers;

use App\Modules\Rewards\Actions\ClaimShareRewardAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * POST /share-reward — the one-time credit bonus for sharing the app. Writes only
 * Rewards state (flag + credits), so it lives here, not in Game or the app layer
 * (no peer-combining). The couple comes from the token (never from the client);
 * the response is idempotent — always 200 {claimed:true} (a repeat call is a
 * no-op, not a 409).
 */
final class ShareRewardController
{
    public function store(Request $request): JsonResponse
    {
        $coupleId = $request->user()->active_couple_id;

        // A token whose user has no couple has no reward account to credit — 404,
        // as it was when the controller resolved the Couple with findOrFail.
        if ($coupleId === null) {
            throw new NotFoundHttpException;
        }

        ClaimShareRewardAction::run($coupleId);

        return response()->json(['claimed' => true]);
    }
}
