<?php

use App\Modules\Rewards\Http\Controllers\AdRewardController;
use App\Modules\Rewards\Http\Controllers\ShareRewardController;
use Illuminate\Support\Facades\Route;

/*
 | Rewards module API routes. Loaded by RewardsServiceProvider wrapped in the
 | `api` prefix + middleware group, so paths resolve to /api/v1/... exactly as
 | before the extraction out of Game.
 */

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    // One-time share reward (rewards the gesture, not a verified share).
    // Idempotent — always 200 {claimed:true}; the grant happens once.
    Route::post('share-reward', [ShareRewardController::class, 'store']);

    // Rewarded ad — repeatable, capped per local day. Always 200: hitting the cap
    // is a business outcome (granted:false), not an error.
    Route::post('ad-reward', [AdRewardController::class, 'store']);
});
