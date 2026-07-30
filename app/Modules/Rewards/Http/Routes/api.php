<?php

use App\Modules\Rewards\Http\Controllers\AdRewardNonceController;
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

    // Rewarded ad, step 1 of 2: authorize one view. The credit itself arrives
    // through the SSV webhook (app layer) — the P6 endpoint that granted it on the
    // client's word is gone, deliberately and without a deprecation window.
    // Throttled per IP: unlike the other reward endpoints this one INSERTS a row
    // per call, so an unthrottled client could pad the table indefinitely. 20/min
    // is far above honest use (five ads a day is the cap) and far below spam.
    Route::post('ad-reward/nonce', [AdRewardNonceController::class, 'store'])
        ->middleware('throttle:20,1');

    // One-time app-rating reward (rewards asking for the prompt — In-App Review
    // reports nothing back). Idempotent, like the share reward.
    Route::post('rating-reward', [RatingRewardController::class, 'store']);
});
