<?php

use App\Modules\Game\Http\Controllers\DailyCardController;
use App\Modules\Game\Http\Controllers\QuestionController;
use App\Modules\Game\Http\Controllers\SessionController;
use App\Modules\Game\Http\Controllers\WeeklyRitualController;
use Illuminate\Support\Facades\Route;

/*
 | Game module API routes (session lifecycle + next question). Loaded by
 | GameServiceProvider wrapped in the `api` prefix + middleware group, so paths
 | resolve to /api/v1/... exactly as before extraction.
 */

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::post('sessions/start', [SessionController::class, 'start']);
    Route::get('sessions/active', [SessionController::class, 'active']);
    Route::post('sessions/{session:ulid}/end', [SessionController::class, 'end']);
    Route::post('sessions/{session:ulid}/skip-current', [SessionController::class, 'skipCurrent']);

    Route::get('questions/next', [QuestionController::class, 'next']);

    // Daily card read (aggregate: question + streak state). The answer save lives
    // in the app layer (peer-combines memory + couple).
    Route::get('daily-card', [DailyCardController::class, 'show']);

    // Weekly ritual read (aggregate: current ritual + "day X of 7"). Assignment is
    // the Sunday cron; a fresh couple is assigned lazily on first read.
    Route::get('weekly-ritual', [WeeklyRitualController::class, 'show']);
});
