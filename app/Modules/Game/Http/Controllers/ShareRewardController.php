<?php

namespace App\Modules\Game\Http\Controllers;

use App\Modules\Game\Actions\ClaimShareRewardAction;
use App\Modules\Game\Models\Couple;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /share-reward — the one-time card bonus for sharing the app. Writes only
 * couples columns (flag + card_balance), so it stays in Game, not the app layer
 * (no peer-combining). The couple comes from the token; the response is
 * idempotent — always 200 {claimed:true} (a repeat call is a no-op, not a 409).
 */
final class ShareRewardController
{
    public function store(Request $request): JsonResponse
    {
        $couple = Couple::findOrFail($request->user()->active_couple_id);

        ClaimShareRewardAction::run($couple);

        return response()->json(['claimed' => true]);
    }
}
