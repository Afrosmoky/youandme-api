<?php

namespace App\Modules\Rewards\Http\Controllers;

use App\Modules\Rewards\Actions\ClaimRatingRewardAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * POST /rating-reward — the one-time credit bonus for asking the OS to show the
 * app-rating prompt. The client calls this right after requestReview(); there is
 * nothing to wait for, since In-App Review has no callback.
 *
 * The couple comes from the token; the response is idempotent — always 200
 * {claimed:true} (a repeat call is a no-op, not a 409), so the client never has
 * to track whether it already claimed.
 */
final class RatingRewardController
{
    public function store(Request $request): JsonResponse
    {
        $coupleId = $request->user()->active_couple_id;

        // A token whose user has no couple has no reward account to credit — 404,
        // the same guard as the share and ad rewards.
        if ($coupleId === null) {
            throw new NotFoundHttpException;
        }

        ClaimRatingRewardAction::run($coupleId);

        return response()->json(['claimed' => true]);
    }
}
