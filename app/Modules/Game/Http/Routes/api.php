<?php

use App\Modules\Game\Http\Controllers\DailyCardController;
use App\Modules\Game\Http\Controllers\DeckController;
use App\Modules\Game\Http\Controllers\LikedQuestionController;
use App\Modules\Game\Http\Controllers\LocalGameController;
use App\Modules\Game\Http\Controllers\QuestionController;
use App\Modules\Game\Http\Controllers\QuestionLikeController;
use App\Modules\Game\Http\Controllers\SessionController;
use App\Modules\Game\Http\Controllers\WeeklyRitualController;
use Illuminate\Support\Facades\Route;

/*
 | Game module API routes (session lifecycle + next question). Loaded by
 | GameServiceProvider wrapped in the `api` prefix + middleware group, so paths
 | resolve to /api/v1/... exactly as before extraction.
 */

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    // Server-side session (start/skip/end + next card). RESERVED FOR SOLO (etap
    // III) — mobile stops calling these in P11 S3a, they are kept on purpose as
    // the foundation of the solo game. See the SessionController docblock.
    Route::post('sessions/start', [SessionController::class, 'start']);
    Route::get('sessions/active', [SessionController::class, 'active']);
    Route::post('sessions/{session:ulid}/end', [SessionController::class, 'end']);
    Route::post('sessions/{session:ulid}/skip-current', [SessionController::class, 'skipCurrent']);

    Route::get('questions/next', [QuestionController::class, 'next']);

    // A whole playable deck at once, for the local game (P10): the phone
    // sequences it offline, so it cannot ask card by card. Filtered exactly like
    // a session pool — this is where the server says what may be played.
    Route::get('questions/deck', [QuestionController::class, 'deck']);

    // The couple's hearted cards. Declared before the {questionUlid} routes for
    // readability only — they are POST/DELETE, so nothing here shadows anything.
    Route::get('questions/liked', [LikedQuestionController::class, 'index']);

    // Question likes (toggle via two REST verbs). Question addressed by ulid as a
    // plain string param — resolved via Catalog Query in the Action, not by
    // route-model binding, so Game never Eloquent-loads Question.
    Route::post('questions/{questionUlid}/like', [QuestionLikeController::class, 'store']);
    Route::delete('questions/{questionUlid}/like', [QuestionLikeController::class, 'destroy']);

    // Daily card read (aggregate: question + streak state). The answer save lives
    // in the app layer (peer-combines memory + couple).
    Route::get('daily-card', [DailyCardController::class, 'show']);

    // Closed-deck read: which locked cards this couple owns. The unlock itself
    // spans Rewards + Game, so it lives in the app layer.
    Route::get('deck', [DeckController::class, 'show']);

    // Weekly ritual read (aggregate: current ritual + "day X of 7"). Assignment is
    // the Sunday cron; a fresh couple is assigned lazily on first read.
    Route::get('weekly-ritual', [WeeklyRitualController::class, 'show']);

    // Local game (P10): the phone plays the session and reports the cards it
    // dealt afterwards, in one batch. Throttled — a report happens once per
    // session, and this is the one endpoint through which a client could inflate
    // its own progress.
    Route::post('game/local/report', [LocalGameController::class, 'report'])
        ->middleware('throttle:20,1');
});
