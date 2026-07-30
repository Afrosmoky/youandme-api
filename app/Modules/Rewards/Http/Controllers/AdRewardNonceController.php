<?php

namespace App\Modules\Rewards\Http\Controllers;

use App\Modules\Rewards\Actions\IssueAdRewardNonceAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * POST /ad-reward/nonce — "I am about to watch a rewarded ad". Replaces P6's
 * POST /ad-reward, which granted the credit on the client's word; the credit now
 * arrives through the SSV webhook and this endpoint only authorizes it.
 *
 * The couple and user come from the token (IDOR — canon §5). The client puts the
 * returned nonce into the ad's custom_data; that is the whole handshake.
 */
final class AdRewardNonceController
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $coupleId = $user->active_couple_id;

        // A token whose user has no couple has no reward account to credit — 404,
        // the same guard as every other Rewards endpoint.
        if ($coupleId === null) {
            throw new NotFoundHttpException;
        }

        return response()->json([
            'nonce' => IssueAdRewardNonceAction::run($coupleId, $user->id),
        ], JsonResponse::HTTP_CREATED);
    }
}
