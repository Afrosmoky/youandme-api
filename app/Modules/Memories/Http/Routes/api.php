<?php

use App\Modules\Memories\Http\Controllers\MemoryController;
use Illuminate\Support\Facades\Route;

/*
 | Memories module API routes — the couple's own history (list, heart, edit,
 | remove). POST /memories (the session-answer save, which peer-combines memory +
 | session) is registered in the app layer (routes/api.php).
 |
 | Memories are addressed by ulid ({memory:ulid}), like sessions: the client never
 | sees the int key, and the binding also hides soft-deleted rows.
 */

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('memories', [MemoryController::class, 'index']);
    // Detail read: the card screen, reached from the list or from an anniversary
    // deep link (where the memory is far past the first cursor page).
    Route::get('memories/{memory:ulid}', [MemoryController::class, 'show']);
    Route::patch('memories/{memory:ulid}', [MemoryController::class, 'update']);
    Route::delete('memories/{memory:ulid}', [MemoryController::class, 'destroy']);

    // Hearting as two idempotent verbs rather than one blind toggle — a retried
    // request cannot undo what the first one did (the P5 likes pattern).
    Route::put('memories/{memory:ulid}/favorite', [MemoryController::class, 'favorite']);
    Route::delete('memories/{memory:ulid}/favorite', [MemoryController::class, 'unfavorite']);
});
