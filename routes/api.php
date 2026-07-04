<?php

use App\Http\Controllers\Api\V1\MemoryController;
use App\Http\Controllers\Api\V1\QuestionController;
use App\Http\Controllers\Api\V1\SessionController;
use Illuminate\Support\Facades\Route;

// Auth endpoints live in the youandme/auth package; /categories lives in the
// Catalog module (App\Modules\Catalog) — both self-register their routes via
// their service providers. Everything below is the not-yet-extracted Game/
// Memories surface.
Route::prefix('v1')->group(function (): void {
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('sessions/start', [SessionController::class, 'start']);
        Route::get('sessions/active', [SessionController::class, 'active']);
        Route::post('sessions/{session:ulid}/end', [SessionController::class, 'end']);
        Route::post('sessions/{session:ulid}/skip-current', [SessionController::class, 'skipCurrent']);

        Route::get('questions/next', [QuestionController::class, 'next']);

        Route::get('memories', [MemoryController::class, 'index']);
        Route::post('memories', [MemoryController::class, 'store']);
    });
});
