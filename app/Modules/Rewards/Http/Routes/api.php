<?php

use App\Modules\Rewards\Http\Controllers\AdRewardController;
use App\Modules\Rewards\Http\Controllers\RatingRewardController;
use App\Modules\Rewards\Http\Controllers\RewardsController;
use App\Modules\Rewards\Http\Controllers\ShareRewardController;
use Illuminate\Support\Facades\Route;

/*
 | Rewards module API routes. Loaded by RewardsServiceProvider wrapped in the
 | `api` prefix + middleware group, so paths resolve to /api/v1/... exactly as
 | before the extraction out of Game.
 */

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    // Balance + what is still earnable. First read endpoint of the module (P7) —
    // before that, credits were write-only.
    Route::get('rewards', [RewardsController::class, 'show']);

    // One-time share reward (rewards the gesture, not a verified share).
    // Idempotent — always 200 {claimed:true}; the grant happens once.
    Route::post('share-reward', [ShareRewardController::class, 'store']);

    // Rewarded ad — repeatable, capped per local day. Always 200: hitting the cap
    // is a business outcome (granted:false), not an error.
    Route::post('ad-reward', [AdRewardController::class, 'store']);

    // One-time app-rating reward (rewards asking for the prompt — In-App Review
    // reports nothing back). Idempotent, like the share reward.
    Route::post('rating-reward', [RatingRewardController::class, 'store']);
});
