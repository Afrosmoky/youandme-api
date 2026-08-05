<?php

use App\Http\Controllers\Api\V1\AdMobSsvWebhookController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DailyCardController;
use App\Http\Controllers\Api\V1\MemoryController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProgressController;
use App\Http\Controllers\Api\V1\QuestionUnlockController;
use App\Http\Controllers\Api\V1\RedeemController;
use App\Http\Controllers\Api\V1\SocialAuthController;
use Illuminate\Support\Facades\Route;

// App composition root: endpoints that return a couple (register / login /
// social / GET me / PATCH me) orchestrate Auth + Game here. Couple-free auth
// endpoints live in the youandme/auth package; sessions/questions in Game;
// categories in Catalog; memories in Memories — each self-registers its routes.
Route::prefix('v1')->group(function (): void {
    // Same per-IP throttle limits as before the move.
    Route::post('auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:10,1');
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1');

    // AdMob server-side verification. Unauthenticated (Google holds no token) and
    // a GET, because that is how the network delivers it; trust comes from the
    // signature plus our own nonce. In the app layer, not Rewards: granting needs
    // Game's "deck already complete?" answer, which Rewards must not ask for
    // itself. Throttled per IP as a crude flood guard — every rejection is cheap
    // and logged.
    Route::get('webhooks/admob-ssv', [AdMobSsvWebhookController::class, 'handle'])
        ->middleware('throttle:120,1');

    Route::post('auth/google', [SocialAuthController::class, 'google'])
        ->middleware('throttle:10,1');
    Route::post('auth/apple', [SocialAuthController::class, 'apple'])
        ->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [ProfileController::class, 'show']);
        Route::patch('me', [ProfileController::class, 'update']);

        // Session-answer save (peer-combines memory + session). GET /memories
        // (a pure list) is registered by the Memories module.
        Route::post('memories', [MemoryController::class, 'store']);

        // Local-game answer save (P10): no server session to check it against, so
        // the card is authorised by the deck rule instead. Spans Memories (the
        // memory) and Game (the played card), which is why it composes here.
        Route::post('memories/local', [MemoryController::class, 'storeLocal']);

        // Daily-card answer (peer-combines memory + couple/streak). GET /daily-card
        // (a Game aggregate) is registered by the Game module.
        Route::post('daily-card/answer', [DailyCardController::class, 'answer']);

        // Buy one locked card for a credit: debit (Rewards) + entitlement (Game)
        // in one transaction. GET /deck (Game) and GET /rewards (Rewards) are
        // registered by their own modules — only the write spans both.
        Route::post('questions/{questionUlid}/unlock', [QuestionUnlockController::class, 'store']);

        // Promo code: validation + register (Premium) and the deck unlock (Game)
        // in one transaction. Throttled — a code is guessable material, and this
        // is the only endpoint that tells you whether a guess was right.
        Route::post('redeem', [RedeemController::class, 'store'])
            ->middleware('throttle:10,1');

        // Progress map: the card count belongs to Memories, the milestones to
        // Progress, and neither knows the other — so the app joins them.
        Route::get('progress', [ProgressController::class, 'show']);
    });
});
